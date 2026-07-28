# Pantheon Alpha.5 Public Delivery Evidence

Status: tested public-delivery lane incompatible; documentation only

Evidence date: 2026-07-28

## Scope

This packet characterizes one disposable installation:

- plugin generation: WordPress `3.0.0-alpha.5`;
- public base URL:
  `https://dev-sswp-portable.pantheonsite.io`;
- M-Sitemap:
  `https://dev-sswp-portable.pantheonsite.io/llm-sitemap.json`;
- observed WordPress version: `7.0.2`; and
- observed public path: nginx and Pantheon Global CDN/Varnish signals.

The classification applies only to this exact public-delivery lane at the
recorded time. It is not a provider-wide statement about every Pantheon
service tier, Advanced Global CDN configuration, custom domain, region, or
future platform version. Provider signals remain diagnostic and do not prove
which component caused an outcome.

No origin-only bypass was independently observed. WordPress cache integration
was not separately tested.

## Deployment Doctor Before Cache Clearing

The owner ran the bounded, administrator-initiated Deployment Doctor:

- started: `2026-07-28T11:30:30+00:00`;
- completed: `2026-07-28T11:30:33+00:00`;
- overall public delivery path: **fail**;
- origin implementation: **not tested**; and
- WordPress cache integration: **not tested**.

The public path retained exact Draft-03 JSON structure, identity bytes,
Content-Digest, Content-Length, profile and canonical links, catalog hints,
`no-transform`, identity selection, and unsafe-method behavior. Mandatory
failures included weak public ETags, coded gzip-advertised responses,
identity-prohibited requests returning `200`, and dependent conditional,
HEAD, discovery, and stability findings.

## Deployment Doctor After Cache Clearing

The owner explicitly cleared the Pantheon cache and reran the same Doctor:

- started: `2026-07-28T12:52:59+00:00`;
- completed: `2026-07-28T12:53:01+00:00`;
- overall public delivery path: **fail**;
- origin implementation: **not tested**; and
- WordPress cache integration: **not tested**.

Cache clearing did not close the mandatory public-delivery failures.

Consistent successful observations included:

- valid canonical Draft-03 M-Sitemap and M-URL structures;
- exact Content-Digest and Content-Length;
- no Content-Encoding when identity was selected;
- `Vary` containing `Accept-Encoding`;
- `Cache-Control` containing `no-transform`;
- exact profile and canonical links;
- catalog hints derived from the certified identity; and
- `405` plus `Allow: GET, HEAD` for the tested unsafe method.

The stable primary failure conditions were:

1. every sampled strong identity ETag was delivered in weak form;
2. advertising gzip selected a gzip-coded response despite the delivered
   `no-transform` directive; and
3. a request explicitly prohibiting identity received `200` instead of
   `406`.

The Doctor also reported conditional, HEAD, and repeated-stability failures
because the selected public validator no longer matched the required strong
identity validator. Root discovery was absent in the Doctor's observed
response even after cache clearing.

The post-clear diagnostic signals were:

- server software `nginx/1.31.0`;
- response server `nginx`;
- `Via: 1.1 varnish`;
- response age `0`; and
- `X-Cache: HIT`.

These are observations, not causal attribution.

## Independent External Observation

A bounded external alpha.5 validator run on 2026-07-28 produced:

```text
schema: tct-external-validator-report-v1
requests: 16
sampled_murls: 3
checks: 97
failed: 18
response_bytes: 75864
elapsed_seconds: 4.928
exit: 1
```

It independently reproduced weak ETags, gzip coding, and failure to return
identity-prohibited `406`. It observed root discovery, conditional `304`, and
M-URL `HEAD` succeeding with the public weak validator in its request
context. That divergence proves only that public observations varied by
request context and/or time. It does not identify whether cache, POP, or
another layer caused the difference, and it does not close the strict
strong-validator failure.

The duplicate delivered `Vary: Accept-Encoding` values passed the required
membership check and are not classified as a correctness blocker.

## Classification

Under the support labels defined by
[`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md),
this exact tested public-delivery lane is:

**Incompatible**

The WordPress generation produced certifiable canonical content, digests,
lengths, links, and identity responses, but the observed public layer did not
preserve the required selected-representation and strong-validator semantics.

This does not authorize:

- a Pantheon-specific plugin branch;
- weakening Draft-03 ETag requirements;
- coded TCT variants;
- cache-busting cookies;
- an MU plugin;
- Pantheon API or VCL changes;
- a Cloudflare Worker in front of Pantheon;
- provider support claims; or
- any adapter implementation.

A future Pantheon lane starts only if the provider identifies a supported,
reliable control for preserving strong ETags, honoring `no-transform`, and
handling the original client's identity prohibition. It requires a separate
documentation-first contract and new disposable evidence.

## Relevant Provider Documentation

Pantheon documents that:

- Global CDN is present for every site and public requests first encounter its
  edge:
  <https://docs.pantheon.io/guides/global-cdn>;
- gzip is enabled by default at the platform:
  <https://docs.pantheon.io/guides/frontend-performance/code-css>; and
- response headers can bypass cache storage, while ordinary customers cannot
  manually edit the standard Global CDN VCL:
  <https://docs.pantheon.io/cache-control>.

These references explain relevant platform boundaries but do not replace the
recorded wire evidence or establish causation.

## Evidence Privacy

No administrator credentials, cookies, authorization headers, account IDs,
private origin information, or raw response bodies are recorded. No
secret-free administrator JSON export was supplied to this checkout, so this
packet summarizes the rendered bounded report and does not fabricate an
export.

## Stop

This is a documentation-only characterization. No plugin, package, cache,
Pantheon setting, provider adapter, route, deployment, or artifact changed.
