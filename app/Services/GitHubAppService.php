<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

class GitHubAppService
{
    private const API_BASE = 'https://api.github.com';

    private const ACCEPT = 'application/vnd.github+json';

    private const INSTALLATION_TOKEN_TTL_MINUTES = 55;

    public function get(string $path, array $query = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : self::API_BASE.$path;
        $merged = [];
        $first = true;

        do {
            $response = $this->request('GET', $url, $first ? $query : []);
            $first = false;
            $data = $response->json();

            if (! is_array($data)) {
                return [];
            }

            if ($this->isList($data)) {
                $merged = array_merge($merged, $data);
            } else {
                return $data;
            }

            $url = $this->parseNextUrl($response->header('Link'));
        } while ($url !== null);

        return $merged;
    }

    public function getJson(string $path, array $query = []): array
    {
        $response = $this->request('GET', self::API_BASE.$path, $query);

        return $response->json() ?? [];
    }

    /**
     * @return array|null Decoded JSON or null when GitHub returns 404.
     */
    public function getJsonAllow404(string $path, array $query = []): ?array
    {
        $response = Http::withHeaders($this->defaultHeaders())
            ->withToken($this->installationToken(), 'token')
            ->get(self::API_BASE.$path, $query);

        if ($response->status() === 404) {
            return null;
        }

        $this->throwUnlessSuccess($response, [200]);

        return $response->json();
    }

    public function post(string $path, array $body = []): array
    {
        $response = Http::withHeaders($this->defaultHeaders())
            ->withToken($this->installationToken(), 'token')
            ->post(self::API_BASE.$path, $body);

        $this->throwUnlessSuccess($response, [200, 201]);

        return $response->json() ?? [];
    }

    public function graphql(string $query, array $variables = []): array
    {
        $response = Http::withHeaders($this->defaultHeaders())
            ->withToken($this->installationToken(), 'token')
            ->post(self::API_BASE.'/graphql', [
                'query' => $query,
                'variables' => (object) $variables,
            ]);

        $this->throwUnlessSuccess($response, [200]);

        return $response->json() ?? [];
    }

    public function getRawContent(string $owner, string $repo, string $path, ?string $ref = null): ?string
    {
        $url = $this->contentsUrl($owner, $repo, $path);
        $query = $ref !== null ? ['ref' => $ref] : [];

        $response = Http::withHeaders(array_merge($this->defaultHeaders(), [
            'Accept' => 'application/vnd.github.raw',
        ]))
            ->withToken($this->installationToken(), 'token')
            ->get($url, $query);

        if ($response->status() === 404) {
            return null;
        }

        $this->throwUnlessSuccess($response, [200]);

        return $response->body();
    }

    public function installationToken(): string
    {
        $installationId = config('github.installation_id');
        if (! $installationId) {
            throw new RuntimeException('GITHUB_APP_INSTALLATION_ID is not set.');
        }

        return Cache::remember(
            'github.installation_token.'.$installationId,
            now()->addMinutes(self::INSTALLATION_TOKEN_TTL_MINUTES),
            function () use ($installationId) {
                $jwt = $this->createJwt();
                $response = Http::withHeaders($this->defaultHeaders())
                    ->withToken($jwt, 'Bearer')
                    ->post(self::API_BASE.'/app/installations/'.$installationId.'/access_tokens');

                $this->throwUnlessSuccess($response, [201]);
                $token = $response->json('token');
                if (! is_string($token) || $token === '') {
                    throw new RuntimeException('GitHub installation token response missing token.');
                }

                return $token;
            }
        );
    }

    private function contentsUrl(string $owner, string $repo, string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        $encoded = implode('/', array_map(rawurlencode(...), $segments));

        return self::API_BASE.'/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/contents/'.$encoded;
    }

    private function request(string $method, string $url, array $query = []): Response
    {
        $response = Http::withHeaders($this->defaultHeaders())
            ->withToken($this->installationToken(), 'token')
            ->send($method, $url, ['query' => $query]);

        $this->throwUnlessSuccess($response, [200]);

        return $response;
    }

    private function createJwt(): string
    {
        $appId = (string) config('github.app_id');
        $pem = (string) config('github.private_key');
        if ($appId === '' || $pem === '') {
            throw new RuntimeException('GITHUB_APP_ID and GITHUB_APP_PRIVATE_KEY must be set.');
        }

        $pem = str_replace('\\n', "\n", $pem);

        $signer = new Sha256;
        $key = InMemory::plainText($pem);

        $config = Configuration::forAsymmetricSigner($signer, $key, $key);

        $now = new DateTimeImmutable;

        $token = $config->builder()
            ->issuedBy($appId)
            ->issuedAt($now->modify('-30 seconds'))
            ->expiresAt($now->modify('+9 minutes'))
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    private function defaultHeaders(): array
    {
        return [
            'Accept' => self::ACCEPT,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    private function parseNextUrl(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }

        foreach (explode(',', $link) as $chunk) {
            if (preg_match('/<([^>]+)>\s*;\s*rel="next"/', trim($chunk), $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function isList(array $data): bool
    {
        if ($data === []) {
            return true;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }

    private function throwUnlessSuccess(Response $response, array $allowed): void
    {
        if (! in_array($response->status(), $allowed, true)) {
            throw new RuntimeException(
                'GitHub API error '.$response->status().': '.$response->body()
            );
        }
    }
}
