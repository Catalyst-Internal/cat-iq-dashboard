# github-dashboard

Single-pane live view of activity across multiple GitHub repos. Reads from the GitHub REST + GraphQL APIs directly from the browser, no backend.

Public, read-only, deployed to Vercel.

**Live: https://github-dashboard-chi.vercel.app**

## Tabs

- **overview** — repo cards with milestone progress, latest release, CI status, stars/forks, total commits, last commit
- **issues** — open issues across all tracked repos, filterable by label (`feature`, `bug`, `agent`, `infra`)
- **activity** — combined timeline of issues, PRs, commits, and releases
- **board** — GitHub Projects v2 column counts per board (requires `read:project` scope)

Auto-refreshes every 60 seconds.

## Local dev

```bash
nvm use 20
cp .env.example .env.local
# fill in VITE_GITHUB_TOKEN, VITE_GITHUB_OWNER, VITE_GITHUB_REPOS
npm install
npm run dev
```

## Token scopes

Generate a fine-grained or classic PAT with:

- `public_repo` — REST endpoints
- `read:project` — GraphQL Projects v2

Fine-grained PATs need: Contents (read), Issues (read), Metadata (read), Pull requests (read), and Projects (read) at the org level.

## Deploy (Vercel)

Connect the repo to Vercel. Set the same three env vars in the Vercel project settings. Auto-deploys on push to `main`.
