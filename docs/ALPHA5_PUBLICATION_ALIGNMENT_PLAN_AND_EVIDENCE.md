# Alpha.5 Publication Alignment Plan and Evidence

Status: release gate and selected-site diagnostic complete

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

- Release source commit:
  `ca8d2c0e426f4bbd1a4e939544236f1de3d44b0f`.
- `composer test`: **121 tests, 3,422 assertions, all passed**.
- Static acceptance: **43 checks, all passed**.
- PHP syntax: passed for all **74** non-vendor PHP source and test files.
- Focused sitemap/document fixtures: **14 tests, 38 assertions, all passed**.
- `git diff --check`: passed.
- Existing exact JCS, Appendix C, representation, conditional-request,
  resource-boundary, extraction, exposure, and Deployment Doctor vectors all
  remained accepted.
- The only core behavior changes are fail-closed handling of an invalid
  M-Sitemap `sitemaps` member and extension-filter attempts to rewrite
  adapter-owned sitemap core members.

### Reproducible Package

- Artifact: `dist/trusted-collab-tunnel-3.0.0-alpha.5.zip`.
- Artifact bytes: **97,901**.
- SHA-256:
  `e68985cb8e315385052d0632d745969e759bd53d5bc5e70649891fc9b5833869`.
- Two independently assembled builds from the release source commit were
  byte-identical.
- Archive entries: **64** — 63 committed files and one generated manifest.
- Manifest values:
  - plugin version: `3.0.0-alpha.5`;
  - source commit:
    `ca8d2c0e426f4bbd1a4e939544236f1de3d44b0f`;
  - draft revision: `draft-jurkovikj-collab-tunnel-03`; and
  - draft source SHA-256:
    `d106c6b10fad897b434834d74682bf093e66a0a5aea1dbf116691e301ef692fd`.
- The required Doctor runtime and bounded external validator are present.
- No `tests/`, `vendor/`, or nested `dist/` entry is present.

### Disposable Actual-ZIP Check

The retained ZIP was installed with `--force` and activated from the archive
on an isolated WordPress 7.0.2 / PHP 8.3 Apache lane. Plugin source was not
bind-mounted. Activation and `wp plugin get` reported `3.0.0-alpha.5`.

| Permalinks | Public outcome | Requests | Samples | Checks | Failed | Response bytes |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| Pretty | pass | 16 | 3 | 97 | 0 | 78,990 |
| Plain | pass | 16 | 3 | 97 | 0 | 78,291 |

Both lanes proved exact profile values, JCS/body validators, digests,
content lengths, sitemap hint parity, discovery and canonical links,
conditional `304`, `HEAD`, unsafe method handling, identity-only gzip
advertisement stability, identity-forbidden `406`, and bounded completion.

All dedicated test containers, volumes, and the Docker network were removed
after validation.

### Selected-Site Diagnostic

The project owner installed alpha.5 on `https://llmpages.org` and ran the
administrator-initiated Deployment Doctor. The rendered alpha.5 controller
included the alpha.5 external-rerun instruction.

- Started: `2026-07-28T10:59:50+00:00`.
- Completed: `2026-07-28T10:59:56+00:00`.
- Overall public delivery path: **fail**.
- Origin implementation: **not tested**.
- WordPress cache integration: **not tested**.

The ordinary identity path retained exact Draft-03 structure, JCS bytes,
strong ETags, Content-Digest, Content-Length, discovery links, sitemap hint
parity, conditional `304`, `HEAD`, unsafe-method handling, and repeated
stability. The mandatory failures were confined to:

- delivered `Cache-Control` without `no-transform`;
- a request advertising gzip receiving `Content-Encoding: gzip`;
- the intermediary weakening the identity ETag for that coded response; and
- a sitemap request forbidding identity receiving `200` instead of `406`.

The Doctor reported only diagnostic signals—LiteSpeed server software,
Cloudflare response server, Cloudflare `DYNAMIC` cache status, and LiteSpeed
`X-Turbo-Charged-By`. Detection is not causal attribution.

The bounded external validator independently reproduced the result:

```text
schema: tct-external-validator-report-v1
requests: 16
sampled_murls: 3
checks: 97
failed: 21
response_bytes: 111222
elapsed_seconds: 13.745
exit: 1
```

Its 21 failures include deterministic cascades from the same three delivery
conditions: missing `no-transform`, unexpected gzip/weak-validator behavior,
and failure to preserve identity-forbidden `406`.

No secret-free administrator JSON export was copied into this repository.
The owner should retain the serializer-produced export privately; this packet
does not fabricate or reconstruct it from the rendered page.

## Stop Condition

Completion of this evidence packet does not publish the package, push the
branch, deploy it to `llmpages.org`, authorize a Worker generation, or open any
other product slice. A separate project-owner authorization on 2026-07-28
opened only the gated review of the optional Draft-03 Worker edge adapter.
