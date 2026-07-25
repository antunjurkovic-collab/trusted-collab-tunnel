# Changelog

This file records private WordPress reference checkpoints. It does not imply
publication, production support, or conformance to an Internet-Draft that has
not been submitted.

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

## 3.0.0-alpha.1 — frozen internal reconstruction

- Preserved the complete earlier internal Draft-03 alignment package at tag
  `tct-wordpress-v3.0.0-alpha.1-internal`.
- This checkpoint remains historical and is not redirected to alpha.2.
