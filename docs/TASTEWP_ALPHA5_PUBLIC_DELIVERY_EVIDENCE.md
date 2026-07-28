# TasteWP Alpha.5 Public Delivery Evidence

Status: tested public-delivery lane incompatible; documentation only

Evidence date: 2026-07-28

## Scope

This packet characterizes one disposable installation:

- plugin generation: WordPress `3.0.0-alpha.5`;
- public base URL:
  `https://overtakestranger.s3-tastewp.com`;
- M-Sitemap:
  `https://overtakestranger.s3-tastewp.com/llm-sitemap.json`;
- observed WordPress version: `7.0.2`; and
- observed public-path signals: `TasteWP-S3 Official/3.0.0 (nginx fork)` and
  Cloudflare.

The classification applies only to this exact public-delivery lane at the
recorded time. It is not a provider-wide statement about every TasteWP
generation, service tier, region, custom domain, or future platform version.
Public-path signals do not prove which component caused an outcome.

No origin-only bypass was independently observed. WordPress cache integration
was not separately tested.

## Administrator Deployment Doctor

The owner ran the bounded, administrator-initiated Deployment Doctor:

- started: `2026-07-28T16:45:28+00:00`;
- completed: `2026-07-28T16:45:33+00:00`;
- overall public delivery path: **fail**;
- origin implementation: **not tested**; and
- WordPress cache integration: **not tested**.

Successful observations included:

- generic root discovery;
- canonical Draft-03 M-Sitemap and M-URL structures;
- exact identity-body Content-Digest values;
- no Content-Encoding when identity was requested;
- `Vary` containing `Accept-Encoding`;
- `Cache-Control` containing `no-transform`;
- exact profile and canonical links;
- sitemap catalog hints derived from the certified identity;
- conditional `304`; and
- `405` plus `Allow: GET, HEAD` for the tested unsafe method.

The stable mandatory failure conditions were:

1. public `200` and `HEAD` responses changed the required strong identity ETag
   to a weak ETag;
2. public responses omitted Content-Length;
3. advertising gzip selected a gzip-coded response while retaining metadata
   derived from the identity representation; and
4. a request explicitly prohibiting identity received `200` instead of
   `406`.

The Doctor also reported dependent ETag, HEAD, and repeated-stability
failures. Conditional `304` responses carried the strong plugin-generated
ETag while ordinary `200` and `HEAD` responses carried its weak form. That
request-method divergence does not satisfy the required public validator
contract.

The diagnostic signals were:

- server software `TasteWP-S3 Official/3.0.0 (nginx fork)`;
- response server `cloudflare`; and
- Cloudflare cache status `DYNAMIC`.

These are observations, not causal attribution. In particular, `DYNAMIC`
does not prove that Cloudflare or another public-path component left the
response unchanged.

## Independent External Observation

The packaged alpha.5 validator independently reproduced the result:

```text
schema: tct-external-validator-report-v1
requests: 16
sampled_murls: 3
checks: 97
failed: 28
response_bytes: 78922
elapsed_seconds: 5.761
exit: 1
```

It observed:

- all four identity responses had canonical JSON and an exact identity-body
  Content-Digest;
- all four public identity ETags were weak;
- all four public identity responses omitted Content-Length;
- the M-Sitemap and selected compression-probe M-URL became gzip-coded when
  gzip was advertised;
- the coded responses retained identity-derived validator and digest
  metadata rather than metadata for the coded representation;
- all three M-URL sitemap hints still identified the certified plugin
  identity and therefore no longer matched the weakened public ETag;
- conditional requests returned `304`, but their ETag differed from the
  ordinary public `200` validator form; and
- identity prohibition returned `200`.

Several of the 28 failed checks are deterministic consequences of those four
primary delivery conditions rather than 28 independent defects.

## Raw Header Confirmation

Four additional read-only M-Sitemap requests at approximately
`2026-07-28T16:52:54+00:00` confirmed:

- identity GET: `200`, no Content-Encoding, weak ETag, chunked transfer, no
  Content-Length;
- gzip-advertised GET: `200`, `Content-Encoding: gzip`, the same weak
  identity-derived ETag and Content-Digest, chunked transfer, no
  Content-Length;
- identity-prohibited GET: `200`, not `406`; and
- identity HEAD: `200`, weak ETag, no Content-Length.

Every response still carried `no-transform`. This does not identify whether
the origin server, hosting middleware, Cloudflare, or another layer selected
or modified the coded response.

## Controlled Artifact Comparison

The exact retained alpha.5 ZIP had already been installed and activated on an
isolated WordPress `7.0.2`, PHP `8.3`, Apache lane with no external
cache/CDN. Source was not bind-mounted.

| Permalinks | Requests | Samples | Checks | Failed | Outcome |
| --- | ---: | ---: | ---: | ---: | --- |
| Pretty | 16 | 3 | 97 | 0 | pass |
| Plain | 16 | 3 | 97 | 0 | pass |

That controlled public path retained exact strong ETags, Content-Digest,
Content-Length, identity-only gzip-advertisement behavior,
identity-prohibited `406`, conditional `304`, HEAD metadata, discovery,
profiles, links, and catalog-hint parity.

The comparison supports the conclusion that alpha.5 can operate correctly
when the delivery environment preserves its response contract. It does not,
by itself, prove which TasteWP public-path component caused the observed
differences.

The authoritative controlled package evidence remains in
[`ALPHA5_PUBLICATION_ALIGNMENT_PLAN_AND_EVIDENCE.md`](ALPHA5_PUBLICATION_ALIGNMENT_PLAN_AND_EVIDENCE.md).

## Classification

Under the labels in
[`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md),
this exact tested public-delivery lane is:

**Incompatible**

The WordPress generation produced canonical content and exact
identity-derived metadata, but the observed public path did not preserve
Draft-03's required selected-representation, strong-validator, length, and
identity-negotiation behavior.

This does not authorize:

- a TasteWP-specific plugin branch;
- weakening Draft-03 ETag or identity requirements;
- coded TCT variants;
- cache-busting cookies or query parameters;
- TasteWP or Cloudflare configuration/API changes;
- a provider adapter;
- an Alpha.6 implementation; or
- provider-wide support or incompatibility claims.

## Artifact and Evidence Privacy

No packaged alpha.5 file changed. The retained ZIP remains:

```text
dist/trusted-collab-tunnel-3.0.0-alpha.5.zip
SHA-256 e68985cb8e315385052d0632d745969e759bd53d5bc5e70649891fc9b5833869
```

No administrator credentials, cookies, authorization headers, account IDs,
private origin information, or raw response bodies are recorded. No
secret-free administrator JSON export was supplied to this checkout, so this
packet summarizes the rendered bounded report and independent public
observations without fabricating an export.

## Stop

This is a documentation-only characterization. No plugin, package, provider
setting, cache adapter, route, deployment, or retained artifact changed.
