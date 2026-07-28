# Changelog

This file records versioned WordPress reference checkpoints. A checkpoint does
not imply production support, general host/cache compatibility, or independent
interoperability.

## 3.0.0-alpha.6 — URL-path-prefix routing repair

- Removed the configured WordPress home-path prefix exactly once before
  reconstructing a C-URL from a pretty M-URL request.
- Added exact root, `/subsite`, Playground-style scope, segment-boundary,
  control-character, and 8 KiB request-path regression coverage.
- Applied the same relative-path recognition before WordPress 404 handling.
- Preserved the configured base path in external-validator report metadata.
- Retained the exact Draft-03 schemas, JSON identity bytes, ETag and digest
  algorithms, cache namespace, and identity-only response model.

## 3.0.0-alpha.5 — published Draft-03 release candidate

- Pinned the exact source identity of posted
  `draft-jurkovikj-collab-tunnel-03`.
- Replaced private/unpublished product metadata with explicit non-stable public
  reference status.
- Rejected the M-Sitemap-Index-only `sitemaps` member in an M-Sitemap.
- Prevented `tct_sitemap_document` from rewriting certified `version`,
  `profile`, or validator-bearing `items`; unknown top-level extension members
  remain permitted and certified.
- Retained the identity-only response model, routes, representation cache
  namespace, and read-only Deployment Doctor.

## 3.0.0-alpha.4 — internal diagnostic test package

- Retained the exact alpha.3 Draft-03 wire behavior, schemas, routes, cache
  namespace, and cache epoch.
- Added the bounded, administrator-initiated, read-only Deployment Doctor and
  secret-free JSON evidence export.
- Added safe raw-byte public-path validation with bounded gzip and JSON
  processing.
- Included the Doctor runtime and external validator in the deterministic
  package allowlist.
- Added owner-scoped, one-hour diagnostic report storage without cache,
  provider, route, or deployment mutation.

## 3.0.0-alpha.3 — internal test package

- Retained the exact alpha.2 Draft-03 wire behavior, schemas, profiles,
  identity bytes, validators, routes, and `tct_v03_alpha2` cache namespace.
- Clarified that cache-epoch invalidation is an internal, lazy representation
  generation switch rather than an external cache purge.
- Added an explicit confirmation before invalidation and clearer current-cache
  status and completion notices.

## 3.0.0-alpha.2 — internal

- Pinned the reworked 2026-07-23 Draft-03 source and its SHA-256 identity.
- Isolated pure Draft-03 DTO, schema, JCS, identity, negotiation, and
  conditional-request code under `src/Draft03/`.
- Replaced short profile tokens with exact revision-specific profile URIs.
- Added complete RFC 8785 Appendix B and generated Node parity evidence.
- Made JCS response bytes authoritative for strong ETag, Content-Digest,
  Content-Length, cached identity, and sitemap validator hints.
- Added bounded canonicalization and bounded sitemap generation.
- Applied one public/non-password exposure decision to endpoint, catalog, and
  C-URL discovery.
- Replaced persistent plaintext API-key matching with digest/env-backed
  verification.
- Moved experimental change recording to the write path and kept all telemetry
  extensions opt-in.
- Moved experimental receipt secrets to runtime configuration and made
  receipt-bearing M-URL responses private/non-storeable.
- Added deterministic clean-commit package construction and embedded evidence
  manifests.
- Unslashed WordPress-normalized `If-None-Match` values before parsing them.
- Added an exact query-style M-URL route for plain-permalink sites and
  invalidated cached URL identities when permalink structure changes.
- Deferred C-URL discovery headers until WordPress query state is available.
- Prevented displayed PHP diagnostics from contaminating protocol response
  bytes while leaving diagnostic logging under site policy.
- Added raw-byte live validation, compression-advertisement probes, and
  disposable minimum/current WordPress integration evidence.

## 3.0.0-alpha.1 — frozen internal reconstruction

- Preserved the complete earlier internal Draft-03 alignment package at tag
  `tct-wordpress-v3.0.0-alpha.1-internal`.
- This checkpoint remains historical and is not redirected to alpha.2.
