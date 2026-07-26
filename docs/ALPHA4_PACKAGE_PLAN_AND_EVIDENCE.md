# Alpha.4 Diagnostic Package Plan and Evidence

Status: release gate passed; ready for selected-site installation

Decision authority: project owner

Authorization date: 2026-07-26

## Purpose

Package the accepted Checkpoint 1 Deployment Doctor as the private,
non-stable `3.0.0-alpha.4` diagnostic test generation. The first selected
installation is `llmpages.org`.

Alpha.4 is not a protocol generation change. It retains the alpha.3 routes,
schemas, profile URIs, identity bytes, validators, `tct_v03_alpha2` cache
namespace, and current cache epoch.

## Authorized Changes

- alpha.4 plugin and package metadata;
- explicit deterministic package inclusion of
  `includes/Compatibility/*.php` and
  `src/Compatibility/Doctor/*.php`;
- packaged `scripts/validate-live.ps1` for an external Windows rerun;
- source, package-integrity, disposable WordPress, and administrator-action
  validation;
- one new alpha.4 ZIP and SHA-256 sidecar; and
- diagnostic installation and testing on `llmpages.org`.

## Excluded Changes

- protocol, route, schema, identity, ETag, digest, or cache-generation changes;
- cache adapters or provider-specific recipes;
- browser, origin, proxy, CDN, WordPress, or plugin setting mutation;
- external cache purges;
- production or public support claims; and
- Checkpoint 2 implementation.

## Release Gate

1. Source regression and static suites pass.
2. Every tracked Doctor runtime file is present in the package selection.
3. No test, fixture, development dependency, credential, or existing
   `dist/` artifact is present in the package.
4. Two clean builds from one committed source checkpoint are byte-identical.
5. The manifest identifies the exact source commit and every packaged file.
6. The actual ZIP installs and activates without a source bind mount on the
   disposable minimum/current WordPress and PHP matrix.
7. Pretty and plain permalink lanes preserve the existing protocol checks.
8. Administrator run and evidence export remain nonce-protected,
   owner-scoped, bounded, and non-storeable.
9. Rollback to alpha.3 requires no CID, receipt, route, or cache migration.

## Evidence

### Source

- Release source commit:
  `bb018d6788ab890b8b087d8e025c4802995a2911`.
- `composer test`: **118 tests, 3,417 assertions, all passed**.
- Static acceptance: **41 checks, all passed**.
- PHP syntax: passed for every source PHP file outside test-only `vendor/`.
- Frozen protocol comparison: no `src/Draft03/*.php` or root
  `includes/*.php` implementation changed from alpha.3.

### Reproducible Package

- Artifact:
  `dist/trusted-collab-tunnel-3.0.0-alpha.4-internal.zip`.
- Artifact bytes: **98,359**.
- SHA-256:
  `f97459f26944d776f16ba8b5bb21f4d05aad7edac3aaf390b8d237f464651990`.
- Two independently assembled builds from the release source commit were
  byte-identical.
- Archive entries: **64** — 63 committed files and one generated manifest.
- All **26** Compatibility/Doctor runtime PHP files are present.
- The bounded external validator is present.
- No `tests/`, `vendor/`, or `dist/` entry is present.
- Manifest plugin version and source commit equal alpha.4 and the release
  source commit.

### Actual-ZIP Disposable Matrix

The retained ZIP was installed with `--force` and activated from the archive.
The plugin source was not bind-mounted.

| Lane | Permalinks | Public outcome | Requests | Samples | Checks |
| --- | --- | --- | ---: | ---: | ---: |
| WordPress 6.0.11 / PHP 8.1.34 | pretty | pass | 18 | 3 | 52 |
| WordPress 6.0.11 / PHP 8.1.34 | plain | pass | 18 | 3 | 52 |
| WordPress 7.0.2 / PHP 8.3.32 | pretty | pass | 18 | 3 | 52 |
| WordPress 7.0.2 / PHP 8.3.32 | plain | pass | 18 | 3 | 52 |

Every report identified:

```text
schema: tct-deployment-doctor-report-v1
plugin_version: 3.0.0-alpha.4
origin_implementation: not_tested
wordpress_cache_integration: not_tested
public_delivery_path: pass
```

The accepted Checkpoint 1 administrator HTTP-action and export evidence
remains applicable: alpha.4 changes only the explanatory external-rerun text
inside the controller. The nonce/capability checks, run action, owner-scoped
storage, random job identifier, export action, authoritative serializer,
`no-store`, and exact `Content-Length` paths are unchanged. Their WordPress
compatibility tests passed in the alpha.4 source suite.

All disposable containers, volumes, and the dedicated Docker network were
removed after validation.

### Selected-Site Expectation

The first llmpages.org run is expected to report public-delivery failures for
the previously observed Cloudflare/LiteSpeed transformed lane. A bounded,
truthful `fail` is a successful diagnostic result. The initial alpha.4 test
must not change Cloudflare, LiteSpeed, WordPress, cache-plugin, route, or epoch
settings.

### Rollback

Reinstalling the retained alpha.3 ZIP with `--force`, or deactivating and
removing alpha.4, is sufficient. Alpha.4 creates only owner/job-scoped
`tct_doctor_*` transients with a one-hour TTL. It does not migrate protocol
state, advance the cache epoch, change the cache namespace, or require
historical identity recomputation.

## Verdict

The private alpha.4 diagnostic package passed its bounded release gate and is
appropriate for installation on llmpages.org. This verdict does not authorize
Checkpoint 2 or establish public/production support.
