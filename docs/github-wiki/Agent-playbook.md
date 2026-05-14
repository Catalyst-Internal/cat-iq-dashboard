# Agent playbook — Catalyst-Internal/github-dashboard

Paste into https://github.com/Catalyst-Internal/github-dashboard/wiki/Agent-playbook

## Before you edit

1. https://github.com/Catalyst-Internal/github-dashboard/blob/main/AGENTS.md
2. https://github.com/Catalyst-Internal/github-dashboard/blob/main/VERSIONING.md

## Label taxonomy

| Label | Meaning |
|-------|---------|
| `semver:patch` | Bug fix, style, dep patch |
| `semver:minor` | New widget or filter |
| `semver:major` | Breaking dashboard contract |

## PR checklist

- [ ] Branch naming
- [ ] Conventional Commits
- [ ] `CHANGELOG.md` when user-visible
- [ ] `npm run lint` and `npm run build`

## Backlinks

- [Wiki home](Home)
- [Releases and tags](Releases-and-tags)
- [CI and build](CI-and-build)
