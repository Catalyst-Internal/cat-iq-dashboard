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
        if ($appId === '') {
            throw new RuntimeException('GITHUB_APP_ID must be set.');
        }

        $pem = $this->loadGithubAppPrivateKeyPem();

        $signer = new Sha256;
        $key = InMemory::plainText($pem);

        $config = Configuration::forAsymmetricSigner($signer, $key, $key);

        // GitHub requires numeric Unix exp in the future on *their* clock. Use epoch
        // instants (UTC) and GitHub's recommended skew: iat 60s in the past, exp up
        // to 10m ahead — see https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-json-web-token-jwt-for-a-github-app
        $now = time();
        $iat = new DateTimeImmutable('@'.(string) ($now - 60));
        $exp = new DateTimeImmutable('@'.(string) ($now + 600));

        $token = $config->builder()
            ->issuedBy($appId)
            ->issuedAt($iat)
            ->expiresAt($exp)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    /**
     * GitHub App PEM in env is often mangled on PaaS (newlines, quotes). Prefer
     * GITHUB_APP_PRIVATE_KEY_BASE64 (base64 of the full .pem file) on Laravel Cloud.
     */
    private function loadGithubAppPrivateKeyPem(): string
    {
        $b64 = trim((string) config('github.private_key_base64'));
        if ($b64 !== '') {
            $b64 = preg_replace('/\s+/', '', $b64);
            $decoded = base64_decode($b64, true);
            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('GITHUB_APP_PRIVATE_KEY_BASE64 is not valid base64.');
            }

            return $this->normalizePemLineEndings($decoded);
        }

        $pem = trim((string) config('github.private_key'));
        if ($pem === '') {
            throw new RuntimeException('Set GITHUB_APP_PRIVATE_KEY or GITHUB_APP_PRIVATE_KEY_BASE64.');
        }

        if ((str_starts_with($pem, '"') && str_ends_with($pem, '"'))
            || (str_starts_with($pem, "'") && str_ends_with($pem, "'"))) {
            $pem = substr($pem, 1, -1);
        }

        return $this->normalizePemLineEndings($pem);
    }

    private function normalizePemLineEndings(string $pem): string
    {
        $pem = str_replace(['\\n', "\r\n", "\r"], "\n", $pem);

        return rtrim($pem, "\n")."\n";
    }

    /**
     * Safe diagnostics for Cloud/support: no PEM or key material is printed.
     *
     * @return array{
     *     source: 'base64'|'pem'|'none',
     *     base64_env_length?: int,
     *     decoded_length?: int,
     *     pem_first_line: string|null,
     *     openssl_loadable: bool,
     *     lcobucci_loadable: bool,
     *     error?: string|null
     * }
     */
    public function diagnoseGithubAppPrivateKey(): array
    {
        $b64Raw = trim((string) config('github.private_key_base64'));
        $pemRaw = trim((string) config('github.private_key'));
        $source = $b64Raw !== '' ? 'base64' : ($pemRaw !== '' ? 'pem' : 'none');

        $out = [
            'source' => $source,
            'pem_first_line' => null,
            'openssl_loadable' => false,
            'lcobucci_loadable' => false,
            'error' => null,
        ];

        if ($source === 'base64') {
            $out['base64_env_length'] = strlen(preg_replace('/\s+/', '', $b64Raw));
        }

        try {
            $pem = $this->loadGithubAppPrivateKeyPem();
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();

            return $out;
        }

        $out['decoded_length'] = strlen($pem);
        $lines = explode("\n", trim($pem));
        $out['pem_first_line'] = $lines[0] ?? null;

        $k = @openssl_pkey_get_private($pem);
        $out['openssl_loadable'] = $k !== false;

        try {
            $signer = new Sha256;
            $key = InMemory::plainText($pem);
            Configuration::forAsymmetricSigner($signer, $key, $key);
            $out['lcobucci_loadable'] = true;
        } catch (\Throwable) {
            $out['lcobucci_loadable'] = false;
        }

        return $out;
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
