# Trusted Collaboration Tunnel — Internal Draft-03 Reference

Version `3.0.0-alpha.3` is a private, non-stable WordPress reference
implementation for the unpublished Collaboration Content Transfer Draft-03.
It is not a production release and is not the older remotely published
Draft-02 plugin.

The immutable alpha.1 reconstruction is tagged
`tct-wordpress-v3.0.0-alpha.1-internal`. Alpha.2 introduced the additive,
substantially reworked internal Draft-03 generation pinned in
[`docs/DRAFT03_INTERNAL_BASELINE.md`](docs/DRAFT03_INTERNAL_BASELINE.md).
Alpha.3 retains its exact protocol behavior and `tct_v03_alpha2` cache
namespace; it only clarifies the cache-administration control.

## Core Surface

- Generic M-Sitemap discovery from the origin root using
  `Link: rel="index"; type="application/json"`.
- HTTP and HTML `rel="alternate"` discovery from an exposable C-URL.
- M-URL `rel="canonical"` and exact revision-specific `rel="profile"` links.
- Exact URI-valued JSON profiles for M-URLs and M-Sitemaps.
- M-Sitemap format version `2`.
- RFC 8785 JCS identity response bytes.
- Strong `"sha256-<64-lowercase-hex>"` ETags computed over those exact bytes.
- `Content-Digest` for identity `200` responses.
- RFC 9110 weak comparison for `If-None-Match`, including lists and wildcard.
- Consistent `GET`, `HEAD`, `304`, `405`, and identity-negotiation behavior.
- Catalog ETag hints derived from the same current/cached M-URL identity bytes.

The pure protocol foundation is isolated under `src/Draft03/`. WordPress
extraction, routing, exposure policy, caching, and headers remain adapters
under `includes/`.

## Deliberate Boundaries

- M-Sitemap Index is not implemented. A catalog above the configured item or
  byte ceiling fails closed with `503` rather than becoming unbounded.
- Only published, publicly viewable, non-password-protected singular content
  passes the default exposure policy. Products, attachments, the posts archive
  placeholder, and the WooCommerce shop placeholder are excluded by default.
- Access-control plugins can impose stronger rules with
  `tct_post_is_exposable`; they must do so before this alpha is used on a
  protected site.
- The plugin serves identity bytes only. It rejects a request that explicitly
  forbids the identity coding and sets `Vary: Accept-Encoding` plus
  `no-transform`.
- Canonicalization is bounded by depth, node, key, string, and total identity
  bytes. Sitemap queries and item counts are bounded independently.
- PHP 8.1 and WordPress 6.0 are the declared internal floors. The internal
  disposable minimum/current matrix is recorded in
  [`docs/ALPHA2_CHECKPOINT_PLAN_AND_EVIDENCE.md`](docs/ALPHA2_CHECKPOINT_PLAN_AND_EVIDENCE.md).
  This does not claim public support; a selected production origin, proxy, and
  CDN configuration must be validated independently.

## Non-Core Experiments

Policy descriptors, usage receipts, statistics, change feeds, `llms.txt`,
manifest, cache administration, and shortcodes are deployment experiments.
They do not establish TCT conformance, authorization, licensing, publisher
intent, or enforceable policy. Request-time statistics, write-path change
records, and receipt emission are disabled by default.

The cache-administration invalidation action advances only the plugin's
internal representation-cache epoch. It leaves historical transient rows to
expire, rebuilds the new generation on demand, and does not purge browser,
LiteSpeed, reverse-proxy, or CDN caches.

Future cache compatibility is governed by the documentation-first
[`TCT Draft-03 Cache Interoperability Contract`](docs/CACHE_INTEROPERABILITY_CONTRACT.md).
Its read-only Checkpoint 1 Deployment Doctor is implemented internally and is
stopped for evidence review. It has not been packaged or deployed. Cache
adapters, purges, validated shared-caching mode, and provider support claims
remain unapproved and unimplemented.

Receipts require a runtime `TCT_RECEIPT_HMAC_KEY` of at least 32 bytes. API
keys can be supplied at runtime through comma-separated `TCT_API_KEYS`, or
entered once in the internal admin UI and persisted only as SHA-256 digests.
Legacy alpha.1 plaintext option values are not consulted.

## Representation Transformation

The WordPress adapter reads saved post source rather than request-context
theme rendering. It recursively follows Gutenberg `innerContent` child
placement, includes Classic/freeform markup, strips HTML tags, decodes HTML
entities, inserts logical line boundaries for block markup, and preserves case
and meaningful internal whitespace. The resulting publisher-selected
plain-text value includes the title, a blank line, and the extracted body.
Unresolved dynamic blocks contribute no text unless a deployment supplies the
deterministic `tct_resolve_dynamic_block_text` filter.

This transformation is intentionally part of the selected M-URL
representation; TCT itself does not claim it is lossless.

## Validation

Development dependencies are test-only:

```powershell
composer install
composer test
& ./scripts/validate-draft03-static.ps1
```

The unit suite includes the complete finite and nonfinite RFC 8785 Appendix B
number table, exact IEEE-754 inputs, UTF-16 key ordering, deterministic Node
`JSON.stringify` parity probes, Draft-03 Appendix C identity evidence,
conditional-request parsing, schema rejection, resource boundaries,
Gutenberg recursion, exposure policy, and hashed key behavior.

The additive Checkpoint 1 suite also covers safe raw-byte WordPress transport,
same-origin redirects, deterministic outcomes, strict report-size parity,
pre-allocation JSON depth/node checks, gzip expansion, owner-scoped evidence,
and constrained-memory subprocess completion.

Run the live validator only against a disposable installation:

```powershell
& ./scripts/validate-live.ps1 -BaseUrl 'https://disposable.example'
```

After a clean commit, `scripts/build-package.ps1` creates two independently
assembled ZIPs with fixed entry metadata, requires byte identity, retains one
artifact, and writes its SHA-256 sidecar. The ZIP contains a source/draft/file
digest manifest.

## Installation

Copy the runtime package to `wp-content/plugins/trusted-collab-tunnel/` and
activate it. Default core resources are:

- `/llm-sitemap.json`
- `/{canonical}/llm/`

Do not publish alpha.3 or describe it as conforming to a published `-03`
revision until the exact pinned draft text is submitted without a
wire-affecting change and legal, public-facing metadata, and the selected
deployment path receive separate review.
