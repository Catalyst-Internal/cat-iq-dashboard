# Cat IQ Dashboard

**Laravel Cloud POC** for org-wide GitHub visibility. Canonical integration target: [`cat-iq-website` `apps/backend/`](https://github.com/Catalyst-Internal/cat-iq-website) (monorepo copy lives in local `Catalyst IQ Website/apps/backend/`).

Internal Laravel dashboard for **catalyst-internal** — repo status, milestones, roadmap (`ROADMAP.md`), and GitHub wikis. Stack: Laravel 11, Livewire 3, Flux UI, Tailwind CSS 4, Postgres (Laravel Cloud) or SQLite locally.

## Local setup

1. `cp .env.example .env` and set `APP_KEY` (`php artisan key:generate`).
2. Configure GitHub App env vars (`GITHUB_*`) and dashboard basic auth (`DASHBOARD_AUTH_*`). For local dev you may leave `DASHBOARD_AUTH_*` empty to skip basic auth (only when `APP_ENV=local`).
3. `composer install` then `npm install` then `npm run build` (or `npm run dev`).
4. `touch database/database.sqlite` (if using SQLite) and `php artisan migrate`.
5. Run `php artisan github:sync-org` with valid GitHub App credentials, then `php artisan queue:work` to process `SyncRepoJob` / child jobs.

## Deploy (Laravel Cloud)

See [docs/LARAVEL-CLOUD.md](docs/LARAVEL-CLOUD.md) for queue worker, scheduler, deploy commands (`php artisan migrate --force`, `php artisan github:sync-org`), webhook URL, and Flux private Composer auth if you use Flux Pro.

## Webhook

`POST /webhooks/github` — CSRF-exempt, `X-Hub-Signature-256` verified. Point the GitHub App webhook to `https://<your-cloud-host>/webhooks/github`.

## Agents

See [AGENTS.md](AGENTS.md) for repository workflow expectations.
