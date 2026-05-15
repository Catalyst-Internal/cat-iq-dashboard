# AGENTS.md — Cat IQ Dashboard

Instructions for AI agents and humans working in **Catalyst-Internal/cat-iq-dashboard** (Laravel).

## Before you ship

- Run `php artisan test` (or at minimum `php artisan migrate` + smoke the two Livewire pages).
- Run `vendor/bin/pint --dirty` if you changed PHP formatting.
- Do not commit `.env`, PEM keys, or `auth.json` (Flux Pro).

## GitHub App

- Service layer: `App\Services\GitHubAppService` (JWT, installation token cache, REST/GraphQL, raw file content).
- Webhook: `POST /webhooks/github` — signature middleware only; no basic auth. Deliveries are appended to `github_webhook_events` for audit.
- Org sync: `php artisan github:sync-org` uses `GITHUB_ORG` (default `catalyst-internal`).

## Product rules

- `ROADMAP.md` on the default branch is parsed by `SyncRoadmapJob`; missing file is logged and skipped.
- Wiki sync clones `{repo}.wiki.git` with the installation token; failures are logged.
- Repo detail page loads workflow runs and open PRs from GitHub with a short TTL cache (see `RepoDetail`).

## Cloud

See [docs/LARAVEL-CLOUD.md](docs/LARAVEL-CLOUD.md) for workers, scheduler, deploy hooks, and Flux credentials.
