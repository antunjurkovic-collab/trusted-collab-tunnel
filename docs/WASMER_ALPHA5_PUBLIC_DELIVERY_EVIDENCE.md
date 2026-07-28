# Wasmer Alpha.5 Public Delivery Evidence

Status: tested 64-bit public-delivery lane incompatible for larger fresh
representations; documentation only

Evidence date: 2026-07-28

## Scope

This packet characterizes disposable WordPress installations created through
Wasmer hosting:

- plugin generation: `3.0.0-alpha.5`;
- passing-activation public base URL:
  `https://wordpress-llmpages64.wasmer.app`;
- M-Sitemap:
  `https://wordpress-llmpages64.wasmer.app/llm-sitemap.json`;
- observed WordPress version: `7.0.2`;
- observed PHP version: `8.3.21`; and
- observed public-path signal: `PHPix/0.2.2`.

The classification applies only to the exact tested public-delivery lane at
the recorded time. It is not a provider-wide statement about every Wasmer
service tier, runtime architecture, region, custom domain, or future platform
version. Public-path signals do not prove which component caused an outcome.

No origin-only bypass was independently observed. WordPress cache integration
was not separately tested.

## Runtime Architecture Boundary

An initial installation created with Wasmer's default 32-bit selection stopped
at the activation guard. Alpha.5 requires:

- PHP `8.1` or newer;
- a 64-bit PHP integer domain (`PHP_INT_SIZE >= 8`); and
- the `mbstring` extension.

The operator then created a separate 64-bit installation. Alpha.5 activated
there without changing or bypassing the guard. The 32-bit observation is an
implementation compatibility boundary, not a statement that TCT as a protocol
requires a particular processor architecture.

## Initial Bounded Pass

The operator ran the administrator-initiated Deployment Doctor:

- started: `2026-07-28T13:05:07+00:00`;
- completed: `2026-07-28T13:05:09+00:00`;
- overall public delivery path: **pass**;
- origin implementation: **not tested**; and
- WordPress cache integration: **not tested**.

All mandatory bounded checks selected by that run passed. A separate external
alpha.5 validator reproduced the clean state:

```text
schema: tct-external-validator-report-v1
ok: true
requests: 16
sampled_murls: 3
checks: 97
failed: 0
response_bytes: 75889
elapsed_seconds: 9.345
exit: 0
```

The clean baseline M-Sitemap contained three items, was `887` bytes, and had
this exact strong identity ETag:

```text
"sha256-eac3953202b22d4eed39344ce64d14723fd817f501027f7ed8e99ca0eb0e2abf"
```

This was a valid result for the bounded representations selected by the
Doctor. The lifecycle evidence below demonstrates why it is not sufficient
for a durable **Verified** classification.

## Disposable Mutation Lifecycle

The test used a revocable WordPress Application Password in process memory.
No credential, authorization header, cookie, nonce, or test marker is retained
in this repository.

### Create

One uniquely named post was published. The identity lane behaved correctly:

- the M-Sitemap changed from three to four items;
- the new item and M-URL were discoverable;
- the M-Sitemap changed from `887` to `1,229` identity bytes;
- the M-Sitemap selected this new strong identity ETag:

  ```text
  "sha256-29d58ea4f23b86e96f673d1094642bc6e3b5f5009a7a011be0a142266eb96264"
  ```

- the new M-URL was `1,389` identity bytes and selected:

  ```text
  "sha256-3c572b5dd3a1ae974ebbfd9ca365e02f00cd2d4e7517228721944f3ca53d7f2f"
  ```

- identity-selected bodies retained exact strong ETags, Content-Digest,
  Content-Length, profiles, and links; and
- the new M-Sitemap hint matched the authoritative M-URL identity.

### Edit

The post's visible content was changed. The identity transition passed:

- the M-URL ETag changed from `sha256-3c572b...` to `sha256-4cfdf1...`;
- the M-Sitemap ETag changed from `sha256-29d58e...` to
  `sha256-c6a315...`;
- a request using each stale validator returned `200` and selected the new
  strong ETag;
- a request using each current validator returned `304`;
- the updated content marker appeared in the M-URL; and
- the updated M-Sitemap hint matched the new M-URL ETag.

This proves create/edit invalidation and conditional behavior for the tested
identity lane.

### Delete and Cleanup

The disposable post was permanently deleted:

- the delete API reported success;
- its former M-URL returned `404`;
- the M-Sitemap returned to three items;
- no test item remained;
- the exact original `887`-byte M-Sitemap ETag was restored; and
- a request carrying the deleted-state sitemap validator returned `200` and
  selected the restored baseline identity.

The authentication material was cleared from process memory. The exact
disposable Application Password was subsequently identified through
WordPress's self-introspection endpoint and revoked; no other credential was
changed.

## Compression Counterexample

While the post existed, an authoritative external alpha.5 rerun reported:

```text
schema: tct-external-validator-report-v1
ok: false
requests: 16
sampled_murls: 3
checks: 97
failed: 7
response_bytes: 77096
elapsed_seconds: 5.953
exit: 1
```

All seven failures belonged to the M-Sitemap's advertised-gzip lane. The
`1,229`-byte identity representation was delivered with
`Content-Encoding: gzip` even though `Cache-Control` still contained
`no-transform`. The coded response omitted the identity ETag and
Content-Length.

A direct raw-header comparison on the new `1,389`-byte M-URL reproduced the
same boundary:

- `Accept-Encoding: identity` returned the certified identity bytes, strong
  ETag, digest, and length;
- `Accept-Encoding: gzip` returned `Content-Encoding: gzip`;
- the coded response omitted ETag and Content-Length; and
- the coded response used chunked transfer encoding.

Additional read-only requests using unique diagnostic query keys showed:

- an `887`-byte M-Sitemap remained identity-coded;
- a `1,006`-byte M-URL remained identity-coded; and
- a `1,867`-byte M-URL became gzip-coded on its fresh query key.

The observation is therefore size-sensitive and public-cache/request-key
sensitive. The exact platform threshold or causal component was not proven.
The tested uncoded/coded trigger is bracketed between `1,006` and `1,229`
identity body bytes for these fresh requests; this is evidence, not a claimed
provider rule.

## Diagnostic Coverage Gap

Alpha.5's Deployment Doctor gzip-tests:

1. the M-Sitemap; and
2. only the first selected M-URL.

The external validator has the same first-M-URL boundary. In the clean site,
the M-Sitemap was `887` bytes and the first M-URL was `819` bytes, so both
stayed below the observed coding trigger. Larger sampled M-URLs received
identity checks but were not selected for the gzip-advertised probe.

Consequently, “all mandatory bounded checks passed” was accurate for the
implemented probe matrix, but that matrix could miss representation-dependent
compression. It must not support a provider compatibility claim.

A future diagnostic correction should replace the first-M-URL gzip selection
with the largest successfully certified identity response among the bounded
M-URL samples. That preserves the current request budget while making the one
M-URL compression probe materially stronger. Oversized mutation fixtures
should also be required before any compatibility claim.

This packet does not authorize that implementation.

## Final Clean-State Verification

After permanent deletion, the public site returned exactly to the original
small baseline. A final external run passed:

```text
schema: tct-external-validator-report-v1
ok: true
requests: 16
sampled_murls: 3
checks: 97
failed: 0
response_bytes: 75889
elapsed_seconds: 10.149
exit: 0
```

The clean pass does not erase the valid larger-representation counterexample.

## Classification

Under the labels in
[`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md),
this exact tested 64-bit public-delivery lane is:

**Incompatible**

The activation and identity lanes are sound, but the observed public path
cannot preserve the generation's required identity-only selected
representation for larger fresh JSON responses.

This does not authorize:

- a Wasmer-specific plugin branch;
- weakening Draft-03 ETag or `no-transform` requirements;
- coded TCT variants;
- query-key cache busting as a deployment recipe;
- Wasmer configuration or API changes;
- a provider adapter;
- an Alpha.6 implementation; or
- a provider-wide support or incompatibility claim.

## Relevant Provider Documentation

Wasmer documents its WebAssembly/WASIX edge architecture at
<https://docs.wasmer.io/edge/architecture/> and its managed WordPress hosting
at <https://docs.wasmer.io/edge/wordpress-hosting/>. These references describe
the platform boundary but do not replace the recorded wire evidence or prove
causation.

## Artifact Integrity

No packaged Alpha.5 file changed. The retained ZIP remains:

```text
dist/trusted-collab-tunnel-3.0.0-alpha.5.zip
SHA-256 e68985cb8e315385052d0632d745969e759bd53d5bc5e70649891fc9b5833869
```

## Stop

This is a documentation-only characterization. No plugin, package, provider
setting, cache adapter, deployment, or retained artifact changed.
