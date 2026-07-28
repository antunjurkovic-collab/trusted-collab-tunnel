=== Collaboration Content Transfer (TCT) — Draft-03 Reference ===
Contributors: antunjurkovic
Tags: http, json, etag, sitemap
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 3.0.0-alpha.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Non-stable WordPress reference implementation for published Collaboration
Content Transfer Internet-Draft revision 03.

== Description ==

This experimental alpha exposes publisher-selected machine-facing JSON
representations, a bounded JSON M-Sitemap, strong representation ETags,
Content-Digest metadata, bidirectional Web links, and conditional GET/HEAD
behavior.

It uses exact URI-valued Draft-03 profiles and sends the same RFC 8785 JCS
identity bytes used to derive each ETag.

This is not a production release. M-Sitemap Index, a general WordPress support
matrix, and independent interoperability evidence remain outside this checkpoint.
Policy, receipts, telemetry, changes, llms.txt, and manifest resources are
non-core experiments disabled or separated from the core contract.

== Changelog ==

= 3.0.0-alpha.6 =
* Removes the configured WordPress home-path prefix exactly once before
  reconstructing a C-URL from a pretty M-URL request.
* Supports root, subdirectory, and Playground-style scope path boundaries with
  an 8 KiB fail-closed request-path ceiling.
* Preserves the configured base path in external-validator report metadata.
* Retains Draft-03 JSON, identity, ETag, digest, schema, and cache semantics.

= 3.0.0-alpha.5 =
* Pins the exact source of the posted Collaboration Content Transfer revision 03.
* Aligns public plugin metadata with the published draft.
* Rejects the M-Sitemap-Index-only sitemaps member in an M-Sitemap.
* Prevents extension filters from rewriting certified sitemap core members or
  current M-URL ETag hints.
* Retains identity-only delivery, existing routes, and the tct_v03_alpha2 cache
  namespace.

= 3.0.0-alpha.4 =
* Retains the alpha.3 Draft-03 wire behavior, routes, and cache generation.
* Adds the bounded, administrator-initiated, read-only Deployment Doctor.
* Exports owner-scoped, secret-free diagnostic JSON without changing caches,
  providers, routes, or infrastructure.
* Packages the bounded external public-path validator for separate execution.

= 3.0.0-alpha.3 =
* Retains the alpha.2 Draft-03 wire behavior and cache namespace.
* Clarifies lazy internal cache-generation invalidation, external-cache
  boundaries, and confirmation before advancing the epoch.

= 3.0.0-alpha.2 =
* Pins the substantially reworked 2026-07-23 internal Draft-03 source.
* Replaces short profile tokens with exact revision-specific profile URIs.
* Uses an audited JCS encoder with complete RFC 8785 Appendix B evidence.
* Derives response bytes, strong ETags, digests, and sitemap hints from one
  certified identity representation.
* Adds RFC-aware If-None-Match parsing and consistent GET/HEAD behavior.
* Adds one fail-closed exposure policy across endpoints, catalogs, and links.
* Replaces plaintext API-key lookup with digest/env-backed verification.
* Adds versioned exact body/validator caches and bounded canonical/catalog
  resource handling.
* Adds deterministic package construction with a source/draft digest manifest.

= 3.0.0-alpha.1 =
* Frozen reconstruction of the earlier internal Draft-03 alignment package.
