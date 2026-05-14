---
wo_origin: WO-076
schema_version: 1
---

# VERSIONING.md

## Repo

**Repo:** github-dashboard
**Current version:** 0.0.0
**Schema version:** 1

---

## Version scheme

`v[major].[minor].[patch]`

### Major

Breaking change to tracked repo list contract, dashboard layout that invalidates saved views, or GitHub API usage that requires new OAuth scopes for all operators.

### Minor

New panel, new filter, new tracked repo, non-breaking dependency upgrades.

### Patch

Bug fix, styling, parser fix, dep bump without user-visible behavior change.

---

## Gate rules

| Bump | Approver |
|------|----------|
| Major | Human always |
| Minor | human |
| Patch | Ren always |

---

## Origin flags

Every versioned file carries:

- `origin: canon-down` — written via repo pipeline (standard)
- `origin: vault-up` — written by Vera to vault, pending repo promotion

Companion field: `canon_sync: synced | pending`

Ren sets `origin: canon-down` and `canon_sync: synced` when canonicalizing a vault-up file, and bumps patch.

---

## Schema version bumps

When the versioning standard (`Work Brain/Claude/OS/versioning-standard.md`) is updated, Ren runs a patch sweep to update `schema_version` in this file across all repos. Ren-autonomous patch operation per gate rules.

---

## Companion artifacts

- [CHANGELOG.md](CHANGELOG.md) — release log
- [AGENTS.md](AGENTS.md) — agent workflow

---

*Governed by the Claude OS Versioning Standard. Last updated for agent workflow rollout.*
