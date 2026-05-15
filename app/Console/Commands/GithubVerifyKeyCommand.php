<?php

namespace App\Console\Commands;

use App\Services\GitHubAppService;
use Illuminate\Console\Command;

class GithubVerifyKeyCommand extends Command
{
    protected $signature = 'github:verify-key';

    protected $description = 'Check GitHub App private key env (safe: does not print secrets).';

    public function handle(GitHubAppService $github): int
    {
        $this->line('APP_ENV: '.config('app.env'));
        $this->line('GITHUB_APP_ID set: '.(config('github.app_id') ? 'yes' : 'no'));
        $this->line('GITHUB_APP_INSTALLATION_ID set: '.(config('github.installation_id') ? 'yes' : 'no'));
        $this->line('GITHUB_APP_PRIVATE_KEY set: '.(trim((string) config('github.private_key')) !== '' ? 'yes' : 'no'));
        $this->line('GITHUB_APP_PRIVATE_KEY_BASE64 set: '.(trim((string) config('github.private_key_base64')) !== '' ? 'yes' : 'no'));
        $this->newLine();

        $d = $github->diagnoseGithubAppPrivateKey();

        $this->line('Key source used: '.$d['source']);
        if (isset($d['base64_env_length'])) {
            $this->line('Base64 env length (whitespace stripped): '.$d['base64_env_length']);
        }
        if (isset($d['decoded_length'])) {
            $this->line('Decoded PEM length (bytes): '.$d['decoded_length']);
        }
        if ($d['pem_first_line']) {
            $this->line('PEM first line: '.$d['pem_first_line']);
        }
        $this->line('openssl_pkey_get_private: '.($d['openssl_loadable'] ? 'OK' : 'FAIL'));
        $this->line('lcobucci InMemory + signer config: '.($d['lcobucci_loadable'] ? 'OK' : 'FAIL'));

        if (! empty($d['error'])) {
            $this->newLine();
            $this->error('Load failed: '.$d['error']);

            return self::FAILURE;
        }

        if (! $d['openssl_loadable'] || ! $d['lcobucci_loadable']) {
            $this->newLine();
            $this->error('Key material loads but OpenSSL or JWT library rejected it. Try PKCS#8 conversion (see docs/LARAVEL-CLOUD.md).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Private key format looks valid. If sync still fails, check APP_ID / INSTALLATION_ID and GitHub API responses in logs.');

        return self::SUCCESS;
    }
}
