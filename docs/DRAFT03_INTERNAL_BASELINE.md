# Draft-03 Internal Conformance Baseline

The normative source for the `3.0.0-alpha.2` internal implementation is:

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

The internal support floor is PHP 8.1. Public WordPress-version support remains
unclaimed until the disposable integration matrix is complete.
