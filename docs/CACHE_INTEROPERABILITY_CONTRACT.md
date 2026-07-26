# TCT Draft-03 Cache Interoperability Contract

- Status: documentation-first proposal
- Baseline package: `3.0.0-alpha.3` at
  `b0a5c05c77d202a38da648c4a2e036c5d0df1ff4`
- Protocol source: pinned unpublished
  `draft-jurkovikj-collab-tunnel-03`
- Implementation authorization: none

## Decision

Trusted Collaboration Tunnel is cache-vendor-neutral. Its protocol core and
WordPress adapter must not depend on Cloudflare, LiteSpeed, or another named
cache product.

Universal deployment correctness cannot be guaranteed by PHP code alone.
Page-cache drop-ins can answer before WordPress loads, web servers can change
headers after PHP finishes, and reverse proxies or CDNs can select or construct
a different representation. TCT therefore separates:

1. origin implementation correctness;
2. known WordPress-cache integration;
3. host and reverse-proxy configuration; and
4. end-to-end public-path verification.

A deployment must not be described as TCT-ready solely because the plugin is
active or because an origin representation is internally certified.

This contract defines the intended compatibility architecture, strict default,
adapter responsibilities, user-facing controls, provider-recipe format, and
acceptance evidence. It does not authorize PHP implementation, modify a live
site, or change the pinned Draft-03 wire contract.

## Frozen Boundaries

Cache-interoperability work must not change:

- Draft-03 JSON schemas or exact profile URIs;
- RFC 8785 JCS identity bytes;
- the SHA-256 ETag algorithm or validator shape;
- M-Sitemap format version `2`;
- C-URL/M-URL discovery or canonical-link semantics;
- pretty- or plain-permalink M-URL mapping;
- exposure, authentication, or receipt policy;
- resource ceilings;
- `tct_v03_alpha2` representation-cache namespace;
- cache-epoch meaning; or
- the retained alpha.3 package and manifest.

Any future wire-affecting change requires a separately versioned alpha
checkpoint and complete conformance review. Provider compatibility must not be
smuggled into canonicalization, representation DTOs, or protocol profiles.

## Terminology

**Origin representation**
: The response body and metadata selected by the TCT WordPress adapter before
  an external cache or proxy transforms them.

**Internal representation cache**
: TCT's epoch-keyed WordPress transients containing exact certified identity
  bodies and ETags. This is independent of page caches and CDNs.

**Page cache**
: A WordPress plugin, drop-in, or web-server facility that stores complete HTTP
  responses and may answer without executing the TCT plugin.

**Transformation**
: Compression, decompression, recompression, JSON rewriting, minification,
  character-set rewriting, or another operation that changes selected
  representation data or bytes.

**Strict compatibility**
: TCT's default deployment mode. Known page-cache and optimization layers are
  instructed to bypass TCT core routes while ordinary HTTP client caching,
  TCT's internal representation cache, and conditional requests remain
  available.

**Validated shared caching**
: An advanced future mode in which a known shared cache may serve TCT routes
  only after fresh end-to-end evidence demonstrates correct selected-
  representation and validator behavior.

**Deployment Doctor**
: An administrator-initiated, read-only verifier that compares the public
  delivery path with TCT's required behavior. A Doctor result is timestamped
  evidence, not a permanent guarantee.

## Trust Boundaries

The intended layering is:

```text
src/Draft03
  pure DTO, schema, JCS, negotiation, conditional-request behavior
        |
includes
  WordPress extraction, exposure, routing, cache, and response adapter
        |
includes/Compatibility
  isolated cache-product detection and request/purge adapters
        |
host or provider recipe
  pre-PHP cache, web server, reverse proxy, and CDN configuration
        |
Deployment Doctor and external validator
  observed public-path evidence
```

Passing at one layer does not imply that the next layer passes.

The pure `src/Draft03/` foundation must never call a WordPress cache API.
Compatibility adapters must never construct or rewrite a TCT JSON value.

## Core Route Set

Compatibility behavior applies to core protocol resources, not every
experimental plugin endpoint.

The route set is derived from current WordPress settings and permalink mode:

- the configured M-Sitemap path, default `/llm-sitemap.json`;
- the configured M-URL suffix, default `llm`;
- the root M-URL using that suffix;
- nested pretty-permalink M-URLs ending in `/<suffix>/`; and
- plain-permalink C-URLs carrying exact query member `tct_m_url=1`.

An implementation must derive route patterns from sanitized current settings.
It must not assume that `/llm-sitemap.json` or `/llm/` is permanently fixed.

Provider recipes must cover both pretty- and plain-permalink forms when the
provider can match query parameters. If a cache cannot safely distinguish the
plain-permalink form, the recipe must bypass the complete affected query route
or classify that configuration as unsupported.

Experimental policy, receipt, statistics, changes, `llms.txt`, manifest,
shortcode, or administration endpoints are outside the Draft-03 conformance
claim. Their cache policies remain independently governed.

## Origin Response Invariants

For a successful identity `GET`, the adapter must continue to emit:

- exact JCS UTF-8 response bytes;
- `Content-Type: application/json` without `charset`;
- the exact strong identity ETag derived from those bytes;
- matching identity `Content-Digest`;
- exact identity `Content-Length`;
- `Vary: Accept-Encoding`;
- `Cache-Control` containing `no-transform`;
- the required profile Link;
- the required canonical Link on M-URLs; and
- no `Content-Encoding`.

For this identity-only WordPress generation:

- an advertised `gzip`, Brotli, or other supported coding must not cause the
  plugin or a compatibility adapter to construct a coded variant;
- if identity remains acceptable, the selected response remains the exact
  identity representation and strong identity ETag;
- if identity is explicitly unacceptable, the response is `406`;
- matching `If-None-Match` on `GET` or `HEAD` returns `304` with the current
  ETag; and
- unsafe methods remain rejected as currently specified.

Compatibility code must not weaken a strong ETag, remove `Vary`, replace the
identity digest, or retain the identity metadata over non-identical coded
bytes.

Disabling PHP `zlib.output_compression` and diagnostic display remains a
defense at the PHP layer. It is not evidence that a later layer preserved the
response.

## Compatibility Modes

### Strict Compatibility

Strict compatibility is the required default.

In this mode:

- TCT's internal epoch-keyed representation cache remains enabled;
- normal browser/client caching under the emitted `Cache-Control` remains
  enabled;
- conditional requests remain enabled;
- known WordPress page caches are instructed not to cache TCT core routes;
- known optimization plugins are instructed not to transform those routes;
- no output-compression feature is enabled by TCT; and
- unknown external intermediaries are handled through recipes and validation.

Strict compatibility does not change public responses to `no-store` merely to
avoid integration work. TCT depends on ordinary HTTP caching semantics, and
authenticated or receipt-bearing responses already have their separately
bounded private/no-store policy.

### Validated Shared Caching

Validated shared caching is a later, separately reviewed capability. It must
not be implemented as a generic "allow cache" checkbox.

Before it can be enabled for a known integration:

1. the provider adapter or recipe is versioned;
2. every required Doctor check passes through the selected public path;
3. identity and coding-selection behavior is byte-correct;
4. purge behavior for all TCT core routes is demonstrated;
5. configuration evidence records provider/version details and test time; and
6. a strict fallback path is available.

A pass becomes stale after seven days, after a TCT plugin/version or route
change, after a detected cache-plugin change, or after the operator reports a
host/CDN configuration change. An implementation must not present stale
evidence as a current pass.

Because an external provider can change without WordPress observing it, the UI
must say "last verified" rather than "guaranteed compatible."

The first interoperability implementation milestone may omit validated shared
caching entirely. A read-only Doctor plus strict adapters is the preferred
first implementation.

## Adapter Contract

Known WordPress-cache behavior belongs under an isolated future
`includes/Compatibility/` adapter layer.

Each adapter must have one provider identifier and must be able to expose:

- detection state and detected version, without claiming compatibility;
- whether it can act before its cache decision;
- whether static URI or query exclusions are required;
- a request-time strict-bypass operation, when officially supported;
- an optimization/transform bypass operation, when separately required;
- targeted purge capability, when officially supported;
- operator instructions for any unsupported configuration step; and
- the official API/reference version used by the adapter.

Adapter operations must be:

- scoped only to TCT core routes;
- idempotent;
- bounded and non-recursive;
- safe when the named cache plugin is absent;
- independent of TCT representation construction;
- free of embedded provider credentials; and
- unable to turn a private response into a public response.

An adapter must use a documented public integration API when one exists. It
must not call private classes or mutate another plugin's serialized options
without a separately reviewed migration contract.

For example, LiteSpeed Cache for WordPress documents request-time cache-control
hooks such as `litespeed_control_set_nocache`. The exact hook and supported
version must be pinned when that adapter is implemented:

<https://docs.litespeedtech.com/lscache/lscwp/api/>

### Early Cache Limitation

Some page caches execute from `advanced-cache.php`, web-server rewrite rules,
or another path before normal plugins load. A request-time TCT hook cannot
prevent an already-materialized hit in that architecture.

Such an integration requires at least one of:

- an official persistent URI/query exclusion API;
- a generated provider rule installed during an explicit administrator
  action;
- a host-level recipe; or
- classification as guided/unsupported.

The plugin must not falsely report that defining a late request constant fixed
a pre-PHP cache.

### Purging

Advancing the TCT internal cache epoch does not purge a page cache, web-server
cache, browser, reverse proxy, or CDN.

An adapter may offer targeted external page-cache purging only when:

- the provider exposes an official scoped API;
- all current pretty/plain core routes are covered;
- the operation cannot become a site-wide purge without explicit warning and
  confirmation; and
- success or failure is reported separately from the internal epoch change.

Internal invalidation and external purge must remain distinct events in the
UI and evidence.

## Deployment Doctor Contract

### Invocation

The Doctor is:

- available only to administrators;
- started explicitly rather than on every admin-page view;
- read-only with respect to posts, routes, epochs, and third-party settings;
- restricted to the configured `home_url` origin;
- bounded by fixed request count, response bytes, samples, and timeouts; and
- prohibited from following a redirect to another origin.

The Doctor must not accept an arbitrary hostname from a request parameter. It
must not store API keys, cookies, receipt secrets, CDN tokens, or response
content containing protected material.

The initial Doctor tests only publicly exposable core resources. Protected
deployments require the external validator path and a separately supplied
ephemeral credential.

If the server cannot reach its own public hostname, the result is
`inconclusive`, not `pass` or `fail`. The UI then supplies the equivalent
external-validator command.

The proposed Doctor budgets are:

| Resource | Maximum |
| --- | ---: |
| Public HTTP requests, including redirect hops | 20 |
| Concurrent requests | 2 |
| Sampled M-URLs | 3 |
| Redirect hops per probe | 3, same origin only |
| Bytes in one response body | 16 MiB |
| Bytes across all response bodies | 32 MiB |
| Time per request | 10 seconds |
| Total execution time | 60 seconds |
| Serialized diagnostic result | 2 MiB |
| Response-body bytes displayed or exported | 0 |

The implementation contract for Checkpoint 1 must retain or explicitly revise
these values before code is authorized. Exceeding any budget produces a typed
`inconclusive` or `fail` check as appropriate; it must not return a partial
overall pass.

### Required Checks

The Doctor must test:

1. origin-root discovery of the configured catalog candidate;
2. exact M-Sitemap content type, profile, version, and structure;
3. exact identity body hash versus strong ETag;
4. exact identity body hash versus `Content-Digest`;
5. exact identity `Content-Length`;
6. absence of `Content-Encoding` for `Accept-Encoding: identity`;
7. presence of `Vary: Accept-Encoding`;
8. presence of `no-transform`;
9. advertised-gzip selection remaining the same identity response for this
   plugin generation;
10. absence of ETag weakening or removal;
11. `identity;q=0` producing `406`;
12. matching identity `If-None-Match` producing `304`;
13. `HEAD` metadata matching the selected `GET`;
14. unsafe method rejection;
15. required profile and canonical links;
16. a bounded sample of M-Sitemap hints matching authoritative identity M-URL
    ETags;
17. repeated response stability; and
18. visible server, cache, proxy, and CDN signals used only as diagnostics.

The advertised-gzip probe must be compression-aware. It must retain the
original response bytes, report the coding, and decode only for diagnostic
comparison. Encountering gzip magic bytes must produce a typed failed check,
not a UTF-8 decoder exception or an aborted Doctor run.

### Result Model

The UI must report separate states:

```text
origin_implementation
wordpress_cache_integration
public_delivery_path
```

Allowed outcome classes are:

- `pass`;
- `fail`;
- `not_tested`;
- `inconclusive`; and
- `stale`.

Detection of a provider is never itself a pass.

Each failed check reports:

- the check identifier;
- the observed public URI;
- expected and observed status/metadata;
- the layer most likely responsible, labelled as an inference when not
  directly proven;
- the matching provider recipe, if available; and
- the exact rerun action.

The Doctor must not display complete response bodies, authentication values, or
provider secrets. Diagnostic output must have a fixed serialized byte ceiling.

### Example

```text
Origin implementation: PASS
WordPress cache integration: NOT TESTED
Public delivery path: FAIL

Failure: gzip-advertised response was transformed
Observed: Content-Encoding gzip and weak ETag
Expected: identity bytes and the strong identity ETag
Likely layer: detected reverse proxy/CDN (inference)
Action: apply provider recipe and rerun
```

## Provider Recipe Contract

Provider-specific configuration belongs in versioned documentation, not the
protocol core.

Every recipe must state:

- provider/product and last-tested version/date;
- support classification;
- where the cache acts in the request path;
- exact dynamic route pattern derivation;
- pretty- and plain-permalink coverage;
- page-cache exclusion requirements;
- transformation/compression controls;
- strong ETag and `Vary` requirements;
- `Cache-Control` preservation requirements;
- targeted purge procedure;
- configuration-change invalidation procedure;
- expected Doctor outcomes; and
- rollback steps.

Recipes must use provider-neutral language for protocol requirements and named
instructions only for configuration mechanics.

The initial recipe set should cover:

- no WordPress page cache on Apache;
- LiteSpeed Cache for WordPress and LiteSpeed Web Server;
- WP Rocket;
- W3 Total Cache;
- WP Super Cache;
- nginx FastCGI cache;
- Varnish; and
- Cloudflare as the first CDN evidence lane.

Additional CDNs are guided/unknown until independently tested. A generic
"works with every CDN" statement is prohibited.

## User-Facing Administration

The first implementation should add one **TCT Compatibility** page containing:

- current strict/advanced mode;
- detected WordPress cache and optimization products;
- detected server/proxy/CDN signals;
- route patterns derived from current settings;
- internal cache generation status;
- external cache/purge status kept visibly separate;
- the explicit **Run Deployment Doctor** action;
- last-verification time and freshness;
- failed checks and recipe links; and
- an export of bounded, secret-free evidence.

The default mode is **Automatic strict compatibility**.

No provider-specific option should appear unless the provider is detected or
the operator explicitly opens advanced guidance. Users should not be asked to
choose ETag algorithms, digest semantics, compression formats, or
canonicalization settings.

The plugin must not:

- solicit or persist CDN administrative credentials;
- silently edit `.htaccess`, nginx, Varnish, or CDN configuration;
- silently disable a site-wide cache;
- perform a site-wide purge without explicit confirmation;
- trust a client-supplied forwarding header by default; or
- hide a failed public-path result behind an origin pass.

## Support Classifications

Compatibility documentation and UI use these classifications:

**Verified**
: The exact documented product/version/configuration has complete current
  disposable or public-path evidence.

**Supported by adapter**
: A public provider API is integrated and its strict mode passes the required
  matrix. A selected deployment still requires Doctor verification.

**Guided**
: A reviewed recipe exists, but automatic configuration or complete version
  evidence does not.

**Unknown**
: Not reviewed or tested. The strict headers remain, but no compatibility
  claim is made.

**Incompatible**
: The layer cannot preserve the required selected-representation and validator
  semantics under the tested configuration.

These labels apply to exact evidence lanes, not every version or service tier
of a named product.

## Security and Privacy

Cache compatibility must preserve the existing rule that authentication and
exposure are decided before representation or catalog publication.

In particular:

- private, password-protected, embargoed, or membership-controlled C-URLs must
  not leak through a public page cache or M-Sitemap;
- receipt-bearing or authenticated TCT responses must never be made public by
  an adapter;
- cache keys must include every authorization distinction required by the
  deployment, or the response must remain non-shared;
- provider diagnostics must not expose origin addresses, tokens, cookies, API
  keys, receipt keys, or protected bodies;
- public loopback requests must remain same-origin and HTTPS;
- redirects to private, link-local, loopback, or other origins are rejected;
- a forwarding header carrying the original `Accept-Encoding` is trusted only
  under a separately configured proxy identity contract; and
- Doctor evidence is integrity/behavior evidence, not publisher
  authentication, authorization, or legal policy.

## Acceptance Evidence

### In-Budget Regression Gate

Every currently accepted alpha.3 protocol vector must remain unchanged in
strict mode:

- exact JCS bodies;
- profiles and schemas;
- ETags, digests, and lengths;
- discovery and canonical links;
- conditional and method behavior;
- exposure decisions;
- resource-limit outcomes;
- pretty/plain permalink mapping;
- M-Sitemap hint parity; and
- internal cache-epoch behavior.

### Adapter Boundary Tests

For each implemented adapter, tests are required for:

- provider absent;
- provider detected but inactive;
- supported version active;
- route match and non-match;
- configured custom sitemap path and M-URL suffix;
- pretty and plain permalinks;
- public and private response policy;
- repeated idempotent invocation;
- targeted purge success and typed failure; and
- no body, schema, profile, or validator mutation.

### Disposable Matrix

Before a public compatibility claim, each Verified or Supported-by-adapter
lane must test:

- minimum and current declared WordPress/PHP versions where applicable;
- cold and warm page-cache states;
- identity and gzip-advertised requests;
- exact bytes, strong ETag, digest, and length;
- `Vary` and `no-transform`;
- `identity;q=0`;
- `If-None-Match` `304`;
- `HEAD`;
- unsafe methods;
- post save, delete, trash, restore, and shared-dependency invalidation;
- internal epoch advance without external-purge confusion;
- targeted external purge;
- cache restart/reload; and
- repeated subprocess or fresh-worker stability where the cache architecture
  requires it.

Evidence records exact versions, configuration digests where safe, commands,
results, source commit, artifact SHA-256, and test date.

### Current Seed Evidence

The existing Apache disposable alpha.2/alpha.3 wire generation demonstrates
correct behavior without a selected CDN in minimum/current WordPress and PHP
lanes.

The `llmpages.org` alpha.3 installation is useful negative public-path
evidence:

- the package manifest matches source checkpoint `b0a5c05`;
- identity M-Sitemap and sampled M-URL bytes match their strong ETags and
  digests;
- conditional identity requests work;
- the public path identifies a LiteSpeed server layer and the delivered
  `Cache-Control` lacks the plugin-emitted `no-transform`, but the exact
  removing layer is not proven;
- a gzip-advertised response delivered through Cloudflare is gzip-coded and
  carries a weakened identity ETag; and
- Cloudflare's default request-header behavior prevents the origin from seeing
  the client's exact `Accept-Encoding` negotiation.

This is a failed cache-interoperability lane, not a failure of the PHP identity
construction and not a provider-wide incompatibility claim. It must remain
failed until corrected and rerun.

Relevant provider references:

- Cloudflare request-header behavior:
  <https://developers.cloudflare.com/fundamentals/reference/http-headers/>
- Cloudflare compression behavior:
  <https://developers.cloudflare.com/speed/optimization/content/compression/>
- Cloudflare strong ETag configuration:
  <https://developers.cloudflare.com/cache/how-to/cache-rules/examples/respect-strong-etags/>
- WP Rocket URL exclusions:
  <https://docs.wp-rocket.me/article/54-exclude-pages-from-the-cache>

## Documentation-First Checkpoints

### Checkpoint 0: Contract

Authorized by the project owner:

- this contract;
- a current-documentation link; and
- confirmation that only documentation changed.

Mandatory stop: review the compatibility modes, Doctor contract, security
boundary, support labels, and evidence matrix before runtime work.

### Checkpoint 1: Read-Only Doctor

The proposed implementation design is documented in
[`CHECKPOINT1_DEPLOYMENT_DOCTOR_IMPLEMENTATION_PLAN.md`](CHECKPOINT1_DEPLOYMENT_DOCTOR_IMPLEMENTATION_PLAN.md).
The project owner separately authorized this checkpoint on 2026-07-26. The
implementation candidate and its evidence are documented in
[`CHECKPOINT1_DEPLOYMENT_DOCTOR_EVIDENCE.md`](CHECKPOINT1_DEPLOYMENT_DOCTOR_EVIDENCE.md).

The separately authorized Checkpoint 1 scope is:

- compression-aware public-path verifier;
- bounded administrator UI and evidence export;
- provider detection used only for diagnostics;
- no settings mutation, cache purge, or adapter action; and
- complete regression and disposable evidence.

Mandatory stop: review observed classifications and diagnostic safety.

### Checkpoint 2: Strict Adapters

Not authorized by this document:

- one adapter per separately reviewed provider slice;
- strict route bypass only;
- targeted purge separately reviewed;
- no validated shared-caching mode yet; and
- provider-specific disposable evidence.

Mandatory stop after each adapter or deliberately bounded adapter group.

### Checkpoint 3: Validated Shared Caching

Not authorized by this document:

- advanced mode and evidence freshness lifecycle;
- safe strict fallback and purge proof;
- selected shared-cache/CDN support; and
- separate product/publication review.

## Stop Gate

Checkpoint 0 is closed. Checkpoint 1 was separately authorized and is now at
its mandatory evidence-review stop.

No further PHP implementation, adapter, public Doctor endpoint, third-party
option mutation, cache purge, live-site change, new package, publication
claim, alpha.4 release, or Checkpoint 2 work is authorized.
