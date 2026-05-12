# Plan: Fix stale SSH URL references

**Origin:** After switching git strategy from SSH to HTTPS cloning (v1.9.0), some docs and tests still reference the old `git@github.com:` SSH format.

**Date:** 2026-05-13

---

## Problem

Three non-plan files still reference SSH URL format for `GIT_REPO`:

1. `docs/configuration.md:79` — says "SSH format (e.g., `git@github.com:user/repo.git`)"
2. `website/src/content/docs/reference/configuration.mdx:77` — says "Use SSH format for the repo URL"
3. `tests/Unit/StrategyTest.php:118,140` — test data uses `git@github.com:user/repo.git`

Plan files (`docs/plans/`) are historical records and should NOT be changed.

## Changes

| # | File | Change |
|---|------|--------|
| F1 | `docs/configuration.md` | Change SSH format reference to HTTPS |
| F2 | `website/src/content/docs/reference/configuration.mdx` | Same |
| F3 | `tests/Unit/StrategyTest.php` | Update test data URLs to HTTPS format |

## Execution Order (TDD)

1. **F3** — Update test URLs (tests should still pass since validation doesn't check URL format)
2. **F1 + F2** — Update docs
3. Verify tests pass
