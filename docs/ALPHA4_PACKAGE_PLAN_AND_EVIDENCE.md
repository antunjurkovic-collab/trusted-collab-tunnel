# Alpha.4 Diagnostic Package Plan and Evidence

Status: packaging implementation in progress

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

To be completed after the committed source is packaged and the actual ZIP is
validated.

