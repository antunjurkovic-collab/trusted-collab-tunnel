# Draft-03 Internal Conformance Baseline

The normative source for the `3.0.0-alpha.2` wire generation, retained
unchanged by the `3.0.0-alpha.3` cache-administration test package, is:

`DN/internal-drafts/IETF Drafts/publication-candidates-2026-07-23/draft-jurkovikj-collab-tunnel-03.md`

Source SHA-256:

`F1B2A1C9C50293C0DF5936F58BABB5F4FAFA895510A4228CA73C71698D2A6159`

This source was prepared on 2026-07-23 and is not yet a published
Internet-Draft. Its revision-specific profile URIs are treated as opaque
internal identifiers until the exact `-03` revision is published.

The implementation must not silently follow later edits to a file carrying the
same draft name. A changed source hash requires a new internal alpha checkpoint
and a complete conformance run.

## Scope

The core conformance surface is:

- M-Sitemap discovery.
- C-URL/M-URL bidirectional discovery.
- M-URL JSON envelope.
- M-Sitemap JSON catalog.
- RFC 8785 JCS serialization.
- SHA-256 strong ETags.
- RFC 9110 conditional GET and HEAD behavior.
- Identity-representation validator hints.

M-Sitemap Index support is optional for this checkpoint. Policy descriptors,
usage receipts, statistics, change feeds, and `llms.txt` remain non-core
deployment extensions and cannot establish Draft-03 conformance.

The declared internal floors are PHP 8.1 and WordPress 6.0. Public compatibility
and production support remain unclaimed until the disposable integration
matrix is complete.

## Implementation Decisions

- The reference serves only the identity representation. It rejects requests
  that explicitly make identity unacceptable rather than delegating content
  coding to an uncontrolled WordPress/PHP output path.
- M-Sitemap Index remains deferred. The single M-Sitemap fails closed above
  10,000 items or 16 MiB by default.
- JCS serialization fails closed above depth 64, 100,000 JSON nodes, 16 KiB
  keys, 4 MiB strings, or 16 MiB total identity output.
- Products and attachments are excluded by default. Published status and
  WordPress's public-viewability predicate are necessary but may not reflect
  third-party membership or paywall rules; those deployments must strengthen
  `tct_post_is_exposable`.
- Unknown extension members remain part of the certified JCS value. A filtered
  M-URL cannot change `canonical_url` away from the emitted canonical Link
  target.
- The alpha.2 wire generation, retained by alpha.3, never reads alpha.1 body,
  validator, or plaintext-key cache/config fields. Its cache namespace and
  epoch are distinct from alpha.1.
