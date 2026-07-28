# InfinityFree and W3 Total Cache Alpha.5 Public Delivery Evidence

Status: tested free-hosting lane externally incompatible; documentation only

Evidence date: 2026-07-28

## Scope

This packet characterizes one disposable WordPress installation:

- plugin generation: WordPress `3.0.0-alpha.5`;
- public base URL: `https://llmpages.rf.gd`;
- M-Sitemap: `https://llmpages.rf.gd/llm-sitemap.json`;
- observed WordPress version: `7.0.2`;
- observed server signals: Apache and OpenResty; and
- one same-host enabled/disabled comparison involving W3 Total Cache.

The classification applies only to this exact free-hosting public-delivery
lane at the recorded time. It is not a provider-wide statement about every
InfinityFree or iFastNet service, premium hosting, custom domain, region,
W3 Total Cache version, configuration, or future platform version.

No origin-only bypass was independently observed. W3 Total Cache was detected,
but its integration was not instrumented independently. Detection is not
conformance and the temporal comparison below does not prove which individual
W3 Total Cache setting produced each response change.

## Evidence Vantages

The administrator Deployment Doctor is initiated by WordPress and requests the
site's configured public name from the hosting environment. It observes a
site-initiated public-name path. It is valuable evidence, but it does not prove
that an independent off-host automated client receives the same response.

The packaged PowerShell validator observes the public name from an independent
external machine. A site-initiated Doctor pass and an external-validator pass
are therefore separate requirements for a durable automated-client
compatibility claim.

This distinction is material on the tested lane because its two vantages
received different response classes after W3 Total Cache was disabled.

## W3 Total Cache Enabled

The owner ran the administrator-initiated Deployment Doctor with W3 Total
Cache enabled:

- started: `2026-07-28T17:43:18+00:00`;
- completed: `2026-07-28T17:43:19+00:00`;
- rendered overall public delivery path: **fail**;
- evidence vantage: site-initiated public-name path;
- origin implementation: **not tested**; and
- WordPress cache integration: **not tested**.

The Doctor expected a `785`-byte certified M-Sitemap with this strong identity
ETag:

```text
"sha256-329fe32b934432d5fbd057aee2d5fa9f9491e5d65e51430914164512575aa9ae"
```

Instead, the observed public response:

- used `Content-Type: text/html; charset=UTF-8`;
- replaced the identity ETag with `"311-657af12bacc72"`;
- omitted `Content-Digest` and the required profile Link;
- replaced the TCT cache policy with
  `max-age=2592000, public, proxy-revalidate`;
- selected gzip when gzip was advertised and changed the ETag again;
- returned `200` when identity was explicitly prohibited;
- returned `200` rather than conditional `304` for the expected identity
  validator; and
- did not expose generic root M-Sitemap discovery.

The M-Sitemap body could not be certified, so the dependent M-URL sample
matrix was correctly reported as **not tested**.

These observations make the exact enabled configuration incompatible with the
TCT identity-response contract. Several displayed failures are consequences of
the same primary metadata and response-selection changes, not independent
defects.

## W3 Total Cache Disabled

The owner disabled W3 Total Cache and reran the same administrator Doctor:

- started: `2026-07-28T17:47:10+00:00`;
- completed: `2026-07-28T17:47:20+00:00`;
- rendered overall public delivery path: **pass**;
- evidence vantage: site-initiated public-name path;
- origin implementation: **not tested**; and
- WordPress cache integration: **not tested**.

All selected mandatory checks passed for the M-Sitemap and three M-URLs. The
site-initiated path retained:

- canonical Draft-03 JCS identity bodies;
- exact strong identity ETags;
- matching `Content-Digest` and `Content-Length`;
- exact JSON content type;
- profile, canonical, and root-discovery Links;
- identity selection when gzip was advertised;
- `Vary: Accept-Encoding` and `no-transform`;
- identity-prohibited `406`;
- conditional `304`;
- matching `HEAD` metadata;
- unsafe-method `405`; and
- repeated identity stability and sitemap-hint parity.

The same-host enabled/disabled comparison strongly associates the first
Doctor failure with the W3 Total Cache-enabled delivery state. It does not
establish general W3 Total Cache incompatibility or prove that disabling it
made the external hosting lane usable.

## Independent External Observation

After the disabled-state Doctor pass, the packaged validator was run from an
external Windows machine:

```text
schema: tct-external-validator-report-v1
ok: false
requests: 5
sampled_murls: 0
checks: 29
failed: 21
response_bytes: 4269
elapsed_seconds: 5.771
exit: 1
```

The external machine received `200 text/html` for the M-Sitemap rather than
the certified JSON representation. The `857`-byte response contained
JavaScript, AES, and cookie-challenge markers and omitted TCT's ETag, Digest,
Links, and `Vary` metadata. It also returned `200` for requests that should
have selected conditional `304`, identity-prohibited `406`, or unsafe-method
`405`.

Because the M-Sitemap could not be certified, no authoritative external M-URL
samples were available. This is the correct fail-closed dependency outcome.

InfinityFree documents that its free-hosting browser security requires
JavaScript and cookies and prevents API and automated-client access, including
REST clients, cURL, bots, scripts, and external validators:

<https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353>

The independent wire observation is authoritative for the tested TCT
automated-client lane. The provider documentation explains the relevant
platform boundary but does not replace that evidence.

## Interpretation

The results establish two independent conditions:

1. **W3 Total Cache enabled:** the exact tested configuration did not preserve
   TCT's response contract even on the site-initiated public-name path.
2. **InfinityFree free-hosting external path:** after W3 Total Cache was
   disabled, an off-host automated client received a mandatory browser
   challenge rather than the public TCT resources.

The second condition means that W3 Total Cache exclusions alone cannot make
this free-hosting lane suitable for TCT's automated clients.

No plugin implementation should attempt to execute, bypass, emulate, or
special-case the browser challenge. TCT clients must be able to retrieve the
protocol resources using ordinary HTTP requests.

## Classification

Under the labels in
[`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md):

- the exact W3 Total Cache-enabled configuration is **Incompatible**; and
- the exact InfinityFree free-hosting external automated-client lane is
  **Incompatible**.

W3 Total Cache as a product remains **Unknown** outside this configuration
until a versioned strict-exclusion recipe passes a disposable create/edit/
delete matrix and independent external validation on a suitable host.

A future W3 Total Cache recipe should derive exclusions from TCT's configured
core route set, cover pretty and plain permalink forms, purge the old page
cache explicitly, and rerun both vantages. This packet does not claim that a
particular exclusion syntax or configuration has passed.

## Diagnostic Requirement Exposed

A future, separately authorized diagnostic slice should label Deployment
Doctor evidence by vantage:

- **site-initiated public-name path** for the administrator Doctor; and
- **independent external public path** for the packaged validator.

A site-initiated pass must not be presented as independent external
verification. Where external evidence has not been run, the external public
path remains **not tested**, even if the site-initiated request passed.

This requirement is recorded for future review only. It does not authorize a
Doctor DTO, UI, import, serializer, package, or runtime change.

## Artifact and Evidence Privacy

No packaged alpha.5 file changed. The retained ZIP remains:

```text
dist/trusted-collab-tunnel-3.0.0-alpha.5.zip
SHA-256 e68985cb8e315385052d0632d745969e759bd53d5bc5e70649891fc9b5833869
```

No administrator credential, Application Password, cookie, authorization
header, account identifier, or raw challenge body is retained in this
repository. No secret-free administrator JSON export was supplied to this
checkout, so this packet summarizes the rendered bounded reports and the
independent external observation without fabricating an export.

## Stop

This is a documentation-only characterization. It does not authorize:

- an InfinityFree- or W3 Total Cache-specific plugin branch;
- a browser-challenge workaround;
- weakening Draft-03 response requirements;
- an automatic cache adapter or settings mutation;
- provider configuration or account changes;
- alpha.5 or alpha.6 source changes;
- repackaging or deployment; or
- provider-wide compatibility claims.
