# AGENTS.md — GitHub dashboard

Instructions for AI agents and anyone committing to **Catalyst-Internal/github-dashboard**. Semver meaning and approver gates live in [`VERSIONING.md`](VERSIONING.md); this file is **workflow only**.

---

## Read contract (before edits)

1. **This file** (`AGENTS.md`).
2. **[`VERSIONING.md`](VERSIONING.md)** if you touch releases, tags, UI contracts, or version fields.
3. **[`README.md`](README.md)** for local dev.

---

## Branch hygiene

- **Default branch:** `main`.
- **Branch from** `main` and **merge or rebase from `main`** before opening a PR when work spans more than a day.
- **Naming:** `feat/<kebab-slug>`, `fix/<kebab-slug>`, `chore/<kebab-slug>`, `docs/<kebab-slug>`. No spaces. One concern per branch.
- **Do not** push long-lived personal branches to `origin`; use a PR or draft PR for backup.

---

## Commits

- **Conventional Commits:** `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`, `revert` with optional scope, imperative subject, ~72 char subject line.
- **Map to semver** only through [`VERSIONING.md`](VERSIONING.md).
- **Optional template:** `git config commit.template docs/git-commit-template.txt` (see [`docs/git-commit-template.txt`](docs/git-commit-template.txt)).

---

## Changelog

- If the change is **user-visible** or affects **runtime contracts**, add an **`[Unreleased]`** entry in [`CHANGELOG.md`](CHANGELOG.md) in the **same PR** as the code.

---

## Tags and labels

- **Git tags** `vX.Y.Z`: only when a release is intentional and [`VERSIONING.md`](VERSIONING.md) gates are satisfied. Agents do **not** create tags unless the task explicitly says so.
- **GitHub labels:** `semver:patch`, `semver:minor`, `semver:major` when possible. [Agent playbook](https://github.com/Catalyst-Internal/github-dashboard/wiki/Agent-playbook).

---

## Verify (deterministic)

No GitHub Actions yet; run lint and build locally:

```bash
npm install
npm run lint
npm run build
```

**CI:** None yet.

---

## Tidying before you request review

- [ ] No tokens or secrets in tracked files.
- [ ] `CHANGELOG.md` / `VERSIONING.md` / `package.json` updated when required by [`VERSIONING.md`](VERSIONING.md).

---

## GitHub Wiki (deep links)

| Page | URL |
|------|-----|
| Wiki home | https://github.com/Catalyst-Internal/github-dashboard/wiki |
| Agent playbook | https://github.com/Catalyst-Internal/github-dashboard/wiki/Agent-playbook |
| Releases and tags | https://github.com/Catalyst-Internal/github-dashboard/wiki/Releases-and-tags |
| CI and build | https://github.com/Catalyst-Internal/github-dashboard/wiki/CI-and-build |

Raw `VERSIONING.md` on `main`: https://github.com/Catalyst-Internal/github-dashboard/blob/main/VERSIONING.md
