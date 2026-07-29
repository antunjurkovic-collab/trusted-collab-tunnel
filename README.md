# Collaboration Content Transfer (TCT) — Draft-03 WordPress Reference

TCT lets a publisher expose deterministic, machine-facing JSON for selected
WordPress pages without replacing the human-facing website. Each participating
page advertises its machine-facing counterpart, while a JSON catalog lets a
client discover the available representations and their current validator
hints. Strong ETags and ordinary conditional HTTP requests let a client avoid
retransferring unchanged content.

Version `3.0.0-alpha.6` implements the
[published Collaboration Content Transfer Internet-Draft revision
03](https://datatracker.ietf.org/doc/draft-jurkovikj-collab-tunnel/03/). It is
a non-stable reference implementation for governed experimental testing, not a
production release, crawler authorization system, content-use policy, or the
older Draft-02 plugin generation.

## How TCT Works

```text
Human-facing page (C-URL)
    └── rel="alternate" ──> deterministic JSON representation (M-URL)

Origin root
    └── rel="index" ─────> machine sitemap (M-Sitemap)
                               └── lists C-URL, M-URL and optional ETag hint

Client revisit
    └── If-None-Match ───> 304 Not Modified when the M-URL is unchanged
```

The WordPress adapter derives the machine-facing content from saved post
source rather than request-specific theme rendering. TCT defines the delivery
and validation mechanism; it does not grant a bot permission to crawl or use
the content.

## Quick Start

Use a disposable or explicitly governed WordPress installation:

1. Confirm 64-bit PHP 8.1 or newer, WordPress 6.0 or newer, and the PHP
   `mbstring` extension.
2. Download `trusted-collab-tunnel-3.0.0-alpha.6.zip` from the
   [Alpha.6 GitHub prerelease](https://github.com/antunjurkovic-collab/trusted-collab-tunnel/releases/tag/tct-wordpress-v3.0.0-alpha.6).
   Do not substitute GitHub's automatically generated source archives.
3. In WordPress, select **Plugins → Add New Plugin → Upload Plugin**, upload
   the ZIP, and activate it.
4. Publish or select a publicly viewable post or page.
5. Open `/llm-sitemap.json` and then open one of the listed `mUrl` values.
6. Select **Settings → TCT Compatibility** and run the Deployment Doctor.

A normal identity response is exact `application/json`, carries a quoted
strong `"sha256-..."` ETag, and includes `Content-Digest`. The Doctor also
checks discovery, conditional requests, response stability, method handling,
identity negotiation, and one selected M-URL.

A Doctor pass is bounded evidence for the sampled resources, deployment,
vantage, and time. It is not a universal compatibility or production-support
claim. After extracting the package on an external Windows machine, an
additional validator can be run only against a disposable installation:

```powershell
& ./scripts/validate-live.ps1 -BaseUrl 'https://disposable.example'
```

## Protocol and Code Map

- The protocol specification is the published individual
  [Draft-03 Internet-Draft](https://datatracker.ietf.org/doc/draft-jurkovikj-collab-tunnel/03/).
- Framework-independent protocol DTOs, validation, canonicalization, ETags,
  digests, conditional matching, and identity negotiation are under
  [`src/Draft03/`](src/Draft03/).
- WordPress extraction, exposure policy, routing, caching, discovery, and
  response adapters are under [`includes/`](includes/).
- Deployment findings and checkpoint evidence are under [`docs/`](docs/).

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
- PHP 8.1 and WordPress 6.0 are the declared alpha floors. The disposable
  minimum/current matrix is recorded in
  [`docs/ALPHA2_CHECKPOINT_PLAN_AND_EVIDENCE.md`](docs/ALPHA2_CHECKPOINT_PLAN_AND_EVIDENCE.md).
  This does not establish general production support. Every selected origin,
  WordPress cache, proxy, and CDN delivery path must be validated independently.
- The built-in WordPress adapter does not emit floating-point values or
  explicit PHP object instances. The generic encoder currently relies on
  `serialize_precision=-1` for ECMAScript-compatible float digits and does not
  preserve the identity of empty or sequential-key PHP objects. Custom
  float/object extensions and standalone reuse of the encoder are outside the
  accepted Alpha.6 surface.

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

Cache compatibility is governed by the documentation-first
[`TCT Draft-03 Cache Interoperability Contract`](docs/CACHE_INTEROPERABILITY_CONTRACT.md).
Its read-only Checkpoint 1 Deployment Doctor is implemented internally and is
accepted by the project owner, packaged in alpha.5, and retained in alpha.6
for deployment diagnostics. Cache adapters, purges, validated shared-caching
mode, and provider support claims remain unapproved and unimplemented.
The required runtime, automated-access, response-preservation, cache, and
two-vantage deployment envelope—and the recommended controlled-local then
clean-public W3 Total Cache investigation—are defined in
[`docs/DEPLOYMENT_ENVELOPE_AND_W3TC_TEST_PLAN.md`](docs/DEPLOYMENT_ENVELOPE_AND_W3TC_TEST_PLAN.md).
The completed controlled Alpha.6/W3TC `2.10.3` matrix, exact strict
pretty- and plain-permalink configurations, negative defaults, lifecycle,
large-response, and restart evidence are recorded in
[`docs/W3TC_ALPHA6_CONTROLLED_DOCKER_EVIDENCE.md`](docs/W3TC_ALPHA6_CONTROLLED_DOCKER_EVIDENCE.md).
The separately authorized public Namecheap/LiteSpeed checkpoint, including
its host-compatible exclusion array and explicit provider-wide limitations,
is recorded in
[`docs/NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md`](docs/NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md).

Receipts require a runtime `TCT_RECEIPT_HMAC_KEY` of at least 32 bytes. API
keys can be supplied at runtime through comma-separated `TCT_API_KEYS`, or
entered once in the TCT admin UI and persisted only as SHA-256 digests.
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

The alpha.6 package includes this script for an external Windows rerun. Extract
the package locally before running it; the WordPress administrator page does
not execute the script on the server.

After a clean commit, `scripts/build-package.ps1` creates two independently
assembled ZIPs with fixed entry metadata, requires byte identity, retains one
artifact, and writes its SHA-256 sidecar. The ZIP contains a source/draft/file
digest manifest. Alpha.5 alignment and packaging evidence is recorded in
[`docs/ALPHA5_PUBLICATION_ALIGNMENT_PLAN_AND_EVIDENCE.md`](docs/ALPHA5_PUBLICATION_ALIGNMENT_PLAN_AND_EVIDENCE.md).
Alpha.6 path-prefix repair and packaging evidence is recorded in
[`docs/ALPHA6_PATH_PREFIX_REPAIR_PLAN_AND_EVIDENCE.md`](docs/ALPHA6_PATH_PREFIX_REPAIR_PLAN_AND_EVIDENCE.md).
The separately tested Pantheon public-delivery lane is characterized
without a provider-wide claim in
[`docs/PANTHEON_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](docs/PANTHEON_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md).
The disposable 64-bit Wasmer lifecycle and its larger-representation
compression counterexample are recorded in
[`docs/WASMER_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](docs/WASMER_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md).
The disposable TasteWP lane and its controlled-artifact comparison are
recorded without a provider-wide claim in
[`docs/TASTEWP_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](docs/TASTEWP_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md).
The same-host W3 Total Cache enabled/disabled comparison and the independently
observed InfinityFree free-hosting browser-challenge boundary are recorded in
[`docs/INFINITYFREE_W3TC_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](docs/INFINITYFREE_W3TC_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md).
The reproducible Playground CLI pass and the independently reproduced
WordPress URL-path-prefix routing blocker are recorded in
[`docs/PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md`](docs/PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md).

## Release Lineage

The immutable alpha.1 reconstruction is tagged
`tct-wordpress-v3.0.0-alpha.1-internal`. Alpha.2 introduced the additive,
substantially reworked internal Draft-03 generation pinned in
[`docs/DRAFT03_INTERNAL_BASELINE.md`](docs/DRAFT03_INTERNAL_BASELINE.md).
Alpha.3 retained its exact protocol behavior and `tct_v03_alpha2` cache
namespace while clarifying cache administration. Alpha.4 retained that wire
generation and added the bounded, administrator-initiated, read-only
Deployment Doctor. Alpha.5 pins the exact posted `-03` text, aligns public
metadata, and closes two fail-closed M-Sitemap extension boundaries.

Alpha.6 is published as a GitHub prerelease for experimental testing. It
repairs alpha.5's known pretty post/page M-URL failure on WordPress
installations served below a URL path such as `/subsite`, without changing the
Draft-03 representation generation. Root and path-prefixed installations
remain separate deployment lanes that must be validated. See
[`docs/DRAFT03_PUBLISHED_BASELINE.md`](docs/DRAFT03_PUBLISHED_BASELINE.md) and
the [exact Alpha.6 release](https://github.com/antunjurkovic-collab/trusted-collab-tunnel/releases/tag/tct-wordpress-v3.0.0-alpha.6).

Do not advertise Alpha.6 as production-ready, universally cache-compatible,
WordPress.org stable, or independently interoperable merely because its source
suite passes. Run both the site-initiated Deployment Doctor and independent
external validator against each selected deployment; their vantages are
separate evidence.

## Licensing and IPR

Repository code is GPL-2.0-or-later. [`PATENTS.md`](PATENTS.md) points to the
associated public IETF IPR disclosure without adding to or replacing its
terms.
