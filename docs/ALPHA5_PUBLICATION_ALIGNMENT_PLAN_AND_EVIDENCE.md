# Alpha.5 Publication Alignment Plan and Evidence

Status: implementation complete; release evidence pending

Decision authority: project owner

Authorization date: 2026-07-28

## Purpose

Prepare one non-stable WordPress `3.0.0-alpha.5` GitHub release candidate
against the exact posted Collaboration Content Transfer revision 03. This
slice follows the separately reviewed Public Coherence Checkpoint and does not
authorize Worker, client, MCP, AST, Semantic Validator, libdualnative, or
production deployment work.

## Authorized Changes

- public, non-stable alpha.5 plugin and package metadata;
- exact posted `-03` source identity in the baseline and package manifest;
- direct requirement-by-requirement review because the exact prepublication
  source recorded for alpha.2-alpha.4 is not retained locally;
- fail-closed rejection of `sitemaps` in an M-Sitemap;
- protection of adapter-owned sitemap `version`, `profile`, and current ETag
  hints from extension-filter rewrites;
- public alpha.5 package naming;
- full source/static/package validation; and
- one deterministic ZIP plus SHA-256 sidecar from a clean release source
  commit.

## Excluded Changes

- M-URL or default M-Sitemap wire redesign;
- profile, JCS, ETag, digest, route, or cache-namespace changes;
- M-Sitemap Index implementation;
- cache adapters, provider mutation, or external purge behavior;
- Worker implementation;
- agent client, crawler, MCP, or ingestion implementation;
- AST or Semantic Validator reference implementation;
- libdualnative changes;
- WordPress.org stable publication;
- production or universal compatibility claims; and
- automatic migration from the legacy Draft-02 generation.

## Release Gate

1. Focused conformance tests pass.
2. The complete PHP unit suite and static acceptance suite pass.
3. Every non-vendor PHP source file passes syntax validation.
4. Existing accepted in-budget and default-wire vectors remain unchanged.
5. The package is built only from a clean committed source checkpoint.
6. The manifest identifies alpha.5, the exact source commit, posted revision
   `-03`, its Markdown SHA-256, and every packaged file.
7. Two independent ZIP assemblies from that commit are byte-identical.
8. The retained ZIP contains the required runtime and external validator and
   excludes tests, development dependencies, credentials, and prior artifacts.
9. Installation from the actual ZIP receives proportional disposable
   WordPress/PHP verification.
10. Stop with evidence; publishing or deploying remains a separate action.

## Evidence

### Pre-change Baseline

- Branch base: `d07bafec4194be0622361c442dd34e1d5b514d77`.
- `composer test`: **118 tests, 3,417 assertions, all passed**.
- Static acceptance: **41 checks, all passed**.
- Focused post-correction fixtures: **14 tests, 38 assertions, all passed**.

### Release Source

Pending clean source commit and complete acceptance results.

### Reproducible Package

Pending clean-commit package build and archive inspection.

### Disposable Actual-ZIP Check

Pending proportional validation.

## Stop Condition

Completion of this evidence packet does not publish the package, push the
branch, deploy it to `llmpages.org`, authorize a Worker generation, or open any
other product slice.
