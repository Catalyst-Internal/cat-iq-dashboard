# Laravel Cloud — Cat IQ Dashboard

## Build / deploy commands

Suggested order under **Settings → Deployments → Deploy commands** (or Build commands):

1. `composer install --no-dev --optimize-autoloader` (ensure [Flux Pro auth](https://fluxui.dev/docs/installation) runs first if applicable: `composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"`).
2. `php artisan migrate --force`
3. `npm ci && npm run build`
4. `php artisan github:sync-org` (optional on every deploy; also available on a schedule via `routes/console.php`)

## Queue worker

Under the environment’s **compute** settings, enable a process that runs:

`php artisan queue:work database --sleep=3 --tries=3`

Use the same `QUEUE_CONNECTION` as `.env` (`database` is the default in `.env.example`; Redis is fine if you provision it).

## Scheduler

Laravel Cloud can run the scheduler cron. Ensure the platform scheduler invokes `php artisan schedule:run` every minute. This app schedules `github:sync-org` every six hours in `routes/console.php` (adjust as needed).

## Git binary (wiki sync)

`SyncWikiJob` runs `git clone` in `sys_get_temp_dir()`. Confirm `git` is available in the Cloud runtime (test one deploy early).

## Webhook URL

After deploy, set the GitHub App webhook to:

`https://<your-laravel-cloud-host>/webhooks/github`

Each delivery is recorded in `github_webhook_events` (event name, optional action, optional `X-GitHub-Delivery`, linked `repository_id` when the payload includes `repository`).

## GitHub App private key on Laravel Cloud

If `github:sync-org` fails with `InvalidKeyProvided` / `DECODER routines::unsupported`, the PEM in `GITHUB_APP_PRIVATE_KEY` is usually corrupted (newlines stripped, extra quotes, etc.).

**Recommended:** set **`GITHUB_APP_PRIVATE_KEY_BASE64`** to the base64 encoding of the **entire** `.pem` file (one line, no spaces):

```bash
# macOS
base64 -i ./your-github-app.private-key.pem | tr -d '\n'

# GNU/Linux
base64 -w0 ./your-github-app.private-key.pem
```

Paste the output into Cloud as `GITHUB_APP_PRIVATE_KEY_BASE64`. Leave `GITHUB_APP_PRIVATE_KEY` empty or remove it to avoid the wrong value winning.

After changing env vars on Cloud, run **`php artisan config:clear`** (or redeploy) so Laravel does not use a cached config.

If `github:sync-org` returns **401** with *`exp` must be a numeric value representing the future time*, the Cloud VM clock is usually **slow** relative to GitHub. This app issues JWTs with GitHub’s recommended **`iat` = now − 60s** and **`exp` = now + 10m** (UTC epoch). If it still fails, open a Laravel Cloud ticket to confirm **NTP** on the runtime.

**Diagnose on the server:** deploy the latest app, then run **`php artisan github:verify-key`**. It prints which env vars are set, the PEM’s first line only, and whether OpenSSL + lcobucci accept the key—without dumping secrets.

If it still fails, convert PKCS#1 → PKCS#8 and base64 that file:

```bash
openssl pkcs8 -topk8 -inform PEM -outform PEM -nocrypt -in app.pem -out app-pkcs8.pem
base64 -i app-pkcs8.pem | tr -d '\n'
```

## Environment variables

Mirror Cloud values locally for debugging: `GITHUB_APP_ID`, `GITHUB_APP_INSTALLATION_ID`, `GITHUB_APP_PRIVATE_KEY` or `GITHUB_APP_PRIVATE_KEY_BASE64`, `GITHUB_WEBHOOK_SECRET`, `DASHBOARD_AUTH_USER`, `DASHBOARD_AUTH_PASSWORD`, database credentials, `APP_KEY` (never regenerate on Cloud if already set).
