=== Trusted Collaboration Tunnel — Internal Draft-03 Reference ===
Contributors: antunjurkovic
Tags: http, json, etag, sitemap
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 3.0.0-alpha.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private, non-stable WordPress reference implementation for the unpublished
Collaboration Content Transfer Draft-03.

== Description ==

This internal alpha exposes publisher-selected machine-facing JSON
representations, a bounded JSON M-Sitemap, strong representation ETags,
Content-Digest metadata, bidirectional Web links, and conditional GET/HEAD
behavior.

It uses exact URI-valued Draft-03 profiles and sends the same RFC 8785 JCS
identity bytes used to derive each ETag.

This is not a production release. M-Sitemap Index, a public WordPress support
matrix, and public Draft-03 conformance remain outside this checkpoint.
Policy, receipts, telemetry, changes, llms.txt, and manifest resources are
non-core experiments disabled or separated from the core contract.

== Changelog ==

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
