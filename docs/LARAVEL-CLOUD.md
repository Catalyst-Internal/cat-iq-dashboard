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

## Environment variables

Mirror Cloud values locally for debugging: `GITHUB_APP_ID`, `GITHUB_APP_INSTALLATION_ID`, `GITHUB_APP_PRIVATE_KEY`, `GITHUB_WEBHOOK_SECRET`, `DASHBOARD_AUTH_USER`, `DASHBOARD_AUTH_PASSWORD`, database credentials, `APP_KEY` (never regenerate on Cloud if already set).
