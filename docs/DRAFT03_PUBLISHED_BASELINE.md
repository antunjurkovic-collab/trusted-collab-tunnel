# Published Draft-03 Conformance Baseline

Status: normative baseline for WordPress `3.0.0-alpha.5`

Recorded: 2026-07-28

## Canonical Publication

- Datatracker:
  <https://datatracker.ietf.org/doc/draft-jurkovikj-collab-tunnel/03/>
- IETF archive:
  <https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html>
- Document name: `draft-jurkovikj-collab-tunnel-03`
- Document date and posting date: 2026-07-27

The exact submitted Markdown source used for this checkpoint has SHA-256:

`D106C6B10FAD897B434834D74682BF093E66A0A5AEA1DBF116691E301EF692FD`

The corresponding retained publication artifacts have these SHA-256 values:

| Artifact | SHA-256 |
| --- | --- |
| Markdown source | `d106c6b10fad897b434834d74682bf093e66a0a5aea1dbf116691e301ef692fd` |
| XML | `26fed4ea9396c8d01d5c7c0a4cce05873d6380ff7244717dc213fb809fc2819f` |
| Plain text | `8bca94f24920a38cf8a8f5a34296c5588842d92b6c8d712e60bb7b91a20d6c87` |
| HTML | `056952749f472dae245efa671176ec41502366cd311c894836f19bbe3a3df26d` |
| PDF | `288cb4eb8324ce2a52915644ff2917ef940722621199f3bc6323225c22276eee` |

The package manifest pins the Markdown source identity. Implementations must
not silently follow a later revision or a changed file carrying the same name.

## Relationship to the Internal Baseline

Alpha.2 through alpha.4 were reviewed against the prepublication source
recorded in
[`DRAFT03_INTERNAL_BASELINE.md`](DRAFT03_INTERNAL_BASELINE.md), whose recorded
SHA-256 is
`F1B2A1C9C50293C0DF5936F58BABB5F4FAFA895510A4228CA73C71698D2A6159`.

That exact prepublication source file is not retained in the current checkout,
so alpha.5 does **not** claim source identity or infer compatibility from the
shared draft filename. The posted `-03` requirements were audited directly
against the implementation and public tests. That audit found and closed two
narrow fail-closed gaps:

1. an M-Sitemap now rejects the M-Sitemap-Index-only top-level `sitemaps`
   member; and
2. the `tct_sitemap_document` extension filter can add unknown top-level
   members but cannot rewrite certified `version`, `profile`, or `items`,
   including current M-URL ETag hints.

Default unfiltered M-URL and M-Sitemap values, routes, profile identifiers,
identity-only response selection, cache namespace, and validator algorithms
are unchanged.

## Alpha.5 Core Scope

- generic M-Sitemap discovery from the origin root;
- C-URL/M-URL bidirectional discovery;
- M-URL JSON representation;
- M-Sitemap JSON catalog version 2;
- RFC 8785 JCS identity response bytes;
- strong SHA-256 representation ETags;
- `Content-Digest` for identity `200` responses;
- `If-None-Match`, `GET`, `HEAD`, `304`, `405`, and identity-negotiation
  behavior; and
- M-Sitemap ETag hints derived from exact current M-URL identity bytes.

## Deliberate Boundaries

- The optional M-Sitemap Index profile is not implemented. A catalog exceeding
  configured item or byte limits fails closed.
- The reference offers only the identity representation. TCT permits this
  subset; coded variants are not required.
- Unknown members are retained in the certified JSON value, but extensions
  cannot rewrite adapter-owned sitemap core members.
- Policy descriptors, usage receipts, statistics, change feeds, `llms.txt`,
  manifests, shortcodes, and cache administration are non-core experiments.
- The Deployment Doctor observes the public path but does not configure or
  purge WordPress, server, reverse-proxy, or CDN caches.
- Alpha.5 is a non-stable GitHub release candidate. It does not establish
  universal host/cache compatibility, a production support matrix, or
  independent interoperability.
- Alpha.5 does not support a WordPress public home URL below a path prefix
  such as `/subsite`. Its non-home pretty M-URL fallback can include that
  prefix twice during C-URL reconstruction and return `404`. The isolated
  evidence is recorded in
  [`PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md`](PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md).
