# Checkpoint 1 Deployment Doctor Implementation Plan

Status: accepted and frozen by project owner

Contract: [`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md)

Implementation authorization: project owner approved Checkpoint 1 on 2026-07-26

Decision authority: project owner

Last updated: 2026-07-26

## Decision

Checkpoint 1 should implement only an administrator-initiated, read-only
Deployment Doctor. It will observe TCT's public delivery path, classify
failures without changing infrastructure, and export a bounded, secret-free
report.

The project owner separately authorized this narrowly bounded Checkpoint 1
implementation after reviewing the documentation checkpoint. That
authorization did not include cache-product adapters, option changes, route
changes, purges, a shared-caching mode, a package, an alpha.4 release, or a
live-site change.

After reviewing the completed Checkpoint 1 evidence, the project owner
accepted and froze the implementation on 2026-07-26. A separate narrow
authorization covers only alpha.4 metadata, deterministic packaging, packaged
installation evidence, and llmpages.org diagnostic testing. It does not
authorize Checkpoint 2.

## Objective

The Doctor answers one narrow operational question:

> Does the currently observed public path preserve the selected TCT
> representation and its HTTP metadata?

It must distinguish evidence about:

```text
origin_implementation
wordpress_cache_integration
public_delivery_path
```

It is not a general site scanner, an uptime monitor, a cache configurator, or
proof that a detected provider caused an observed failure.

## Frozen Boundaries

### Included

- public origin-root discovery;
- the configured M-Sitemap;
- at most three public M-URLs selected deterministically from that sitemap;
- identity, gzip-advertised, conditional, `HEAD`, and unsafe-method probes;
- exact byte, digest, ETag, media-type, profile, link, and schema checks;
- diagnostic-only provider and delivery-layer signals;
- a bounded administrator page and bounded JSON export;
- an equivalent external-validator command when loopback is unavailable; and
- unit, disposable WordPress, subprocess, and negative public-path evidence.

### Excluded

- protected resources and credentials;
- arbitrary URLs or hosts;
- mutations of posts, routes, TCT cache epochs, transients owned by the
  representation cache, or third-party settings;
- cache-product bypass APIs;
- cache or CDN purges;
- automatic remediation;
- recurring or page-load execution;
- public REST, AJAX, MCP, or diagnostic endpoints;
- full response bodies in UI, logs, storage, or exports; and
- support or compatibility claims based only on provider detection.

Normal HTTP handling may populate an already configured intermediary cache.
That is an effect of observing the public route, not a Doctor mutation or purge
operation.

## Accepted Resource Budget

Checkpoint 1 retains the contract ceilings:

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

The first implementation will deliberately use concurrency **1**. Sequential
execution gives deterministic ordering and is friendlier to constrained shared
hosting while remaining below the contract maximum.

The origin-root discovery request doubles as a loopback preflight and uses a
three-second timeout. Subsequent requests may use up to ten seconds, always
capped by the remaining total execution budget.

Each request and redirect hop is reserved before it is issued. Each raw body is
charged before parsing or diagnostic decoding. Once a global limit is reached,
dependent checks become typed `inconclusive` or `not_tested`; the report can
never derive an overall pass from partial execution.

## Isolation and Compatibility

The existing `TCT\Draft03` protocol foundation and existing WordPress route
implementation remain unchanged.

Checkpoint 1 should add an internal, non-stable namespace:

```text
TCT\Compatibility\Doctor
```

The runtime autoloader and Composer PSR-4 map may add that prefix without
redirecting or modifying the `TCT\Draft03` prefix.

Proposed ownership:

```text
src/Compatibility/Doctor/
  pure request, response, check, report, limit, and orchestration types

includes/Compatibility/
  WordPress HTTP transport, administrator UI, storage, and signal detection

tests/Compatibility/
  pure and generated boundary tests

tests/WordPress/
  WordPress adapter and administrator-action tests
```

Exact filenames may be simplified during implementation, but these ownership
boundaries must remain:

- pure Doctor code does not call WordPress;
- WordPress transport does not construct or rewrite TCT JSON;
- provider-signal code cannot determine a passing outcome;
- administrator code cannot accept a target host; and
- no new class is presented as a stable public plugin API.

Existing alpha.3 behavior remains the compatibility baseline. The new Doctor
observes it; it does not alter its representations, validators, routes, cache
namespace, or settings.

## Typed Internal Model

The implementation should use immutable DTOs or equivalent value objects for:

- `DoctorLimits`;
- `DoctorContext`;
- `ProbeRequest`;
- `ProbeResponse`;
- `TransportFailure`;
- `DoctorCheck`;
- `LayerResult`;
- `ProviderSignal`;
- `DoctorReport`; and
- `ReportLimitFailure`.

The pure orchestration seam is conceptually:

```php
interface DoctorTransport
{
    public function request(ProbeRequest $request): ProbeResponse|TransportFailure;
}

final class DeploymentDoctor
{
    public function run(
        DoctorContext $context,
        DoctorTransport $transport
    ): DoctorReport;
}
```

These signatures are internal and non-stable. Tests use an injected fake
transport; production uses the WordPress transport.

### Typed Failure Codes

At minimum, transport and resource failures distinguish:

```text
loopback_unavailable
transport_timeout
transport_error
unsafe_url
cross_origin_redirect
redirect_limit
request_limit
response_bytes
total_response_bytes
total_time
invalid_header
unexpected_content_coding
unsupported_content_coding
gzip_decode_failed_or_limit
invalid_utf8
invalid_json
invalid_schema
noncanonical_json
diagnostic_bytes
```

Codes are stable within the report schema even if user-facing wording changes.
No exception message, stack trace, raw body, or unbounded provider response is
copied into the report.

## URI Derivation and Redirect Rules

The target origin comes only from the configured WordPress `home_url`. The
administrator action accepts no URL, host, scheme, port, credential, cookie,
or header override.

The Doctor derives:

- the origin root;
- the configured M-Sitemap path;
- M-URLs certified by the accepted M-Sitemap; and
- pretty or plain-permalink forms already emitted by TCT.

Before every request:

1. parse and normalize the target;
2. require `http` or `https`;
3. reject user information and fragments;
4. require the exact `home_url` scheme, normalized host, and effective port;
5. call the WordPress safe-URL validator; and
6. retain only a bounded, secret-free URI representation for reporting.

The WordPress transport disables automatic redirects. The Doctor handles a
`Location` header itself so every hop is validated and charged. A redirect is
followed only when it remains on the exact origin. A scheme, host, or effective
port change is a public-path `fail`; an invalid or unsafe target is a typed
safety failure. More than three hops fails the probe.

No authorization, WordPress login, API key, or site cookie is attached. The
initial Doctor therefore tests only resources that are intentionally public.

## Authoritative WordPress HTTP Transport

Production probes use `wp_safe_remote_request()` for every method. The
transport arguments are fixed by code:

```text
redirection          0
decompress           false
limit_response_size  remaining per-response ceiling plus one byte
timeout              min(request ceiling, remaining total time)
sslverify            true
httpversion          1.1
cookies              empty
blocking             true
```

The transport adds only Doctor-owned public headers, including the exact
`Accept-Encoding`, `Accept`, conditional validator, and method required for
the current probe. It never forwards the administrator request's cookies or
authorization headers.

The response body remains a PHP byte string. The transport records:

- status;
- a normalized allowlist of response metadata;
- the raw body byte count;
- a raw SHA-256 where needed;
- the validated final URI;
- redirect count; and
- bounded elapsed time.

It does not decode UTF-8, JSON, gzip, or another content coding.

`limit_response_size` is a pre-allocation defense, not the sole limit check.
The returned length is also charged and checked before any parser or decoder is
called. A transport that cannot distinguish an exact-limit body from a
truncated over-limit body must conservatively classify the result as
`inconclusive`, never pass it.

Normative implementation references:

- WordPress safe HTTP request:
  <https://developer.wordpress.org/reference/functions/wp_safe_remote_request/>
- WordPress HTTP request arguments:
  <https://developer.wordpress.org/reference/classes/wp_http/request/>
- WordPress loopback behavior:
  <https://developer.wordpress.org/advanced-administration/wordpress/loopback/>

## Execution Lifecycle

Checkpoint 1 uses a synchronous, explicit administrator action:

1. an administrator opens **Settings > TCT Compatibility**;
2. the page shows no automatic remote probe;
3. **Run Deployment Doctor** submits a nonce-protected `POST`;
4. the action checks `manage_options` before doing work;
5. the Doctor runs within the total 60-second budget;
6. a bounded report is stored under a random, user-owned job identifier;
7. the action redirects back to the result page; and
8. the result expires automatically.

There is no polling endpoint, cron job, background worker, or recurring event
in Checkpoint 1.

Some single-worker or restricted hosts cannot serve their own public hostname
while the administrator request occupies the worker. The three-second
origin-root preflight detects this class of problem. A timeout, DNS restriction,
safe-URL rejection, or loopback refusal produces:

```text
public_delivery_path: inconclusive
```

The report then shows the equivalent external-validator command. It does not
claim that TCT or the cache is broken.

## Deterministic Probe Schedule

Checks are executed and reported in a fixed order:

1. origin-root discovery;
2. M-Sitemap identity `GET`;
3. M-Sitemap advertised-gzip `GET`;
4. M-Sitemap `identity;q=0` `GET`;
5. M-Sitemap matching `If-None-Match` `GET`;
6. M-Sitemap `HEAD`;
7. M-Sitemap unsafe-method probe;
8. repeated M-Sitemap identity `GET`;
9. identity, conditional, and `HEAD` probes for up to three sampled M-URLs;
10. one advertised-gzip M-URL probe when the request budget permits; and
11. diagnostic signal collation.

With no redirects, the maximum schedule uses 18 HTTP requests. Redirect hops
consume the shared maximum of 20, so later optional or dependent probes may
become `not_tested` after a valid earlier result.

Sample selection is deterministic:

- accept only M-URLs on the configured home origin;
- retain M-Sitemap order;
- remove exact duplicate M-URLs; and
- select the first three eligible entries.

An out-of-origin M-URL is a sitemap/schema failure and is never requested.

The unsafe method will be one fixed bodyless `POST`. It carries no nonce,
credential, or content and expects the route's method-rejection behavior.

## Raw Bytes and Content Coding

The selected representation is certified from original response bytes.

For `Accept-Encoding: identity`:

- any non-empty `Content-Encoding` is a failed public-path check;
- UTF-8, JSON, schema, and canonicalization are attempted only after raw byte
  limits pass; and
- hash, `Content-Length`, strong ETag, and `Content-Digest` compare against the
  original identity bytes.

For advertised gzip:

- preserve and hash the original coded bytes;
- record the coding and validator form before decoding;
- never pass coded bytes to the UTF-8 or JSON parser;
- if the response remains identity, compare those raw bytes and metadata
  directly with the identity probe;
- if it is gzip-coded, report the current-generation interoperability failure;
  and
- optionally decode only to explain whether the coded entity corresponds to
  the identity representation.

The diagnostic gzip path uses `gzdecode()` with a maximum decoded length of
`16 MiB + 1`. It is called only after the coded body has passed its raw limit.
A result over 16 MiB, a malformed stream, or an ambiguous bounded-decode
failure is typed as `gzip_decode_failed_or_limit`. No unbounded fallback
decoder is allowed.

`br`, multiple codings, or unknown coding values are
`unsupported_content_coding`; they are not passed through a text decoder.

PHP bounded gzip reference:
<https://www.php.net/manual/en/function.gzdecode.php>.

Tests must include invalid UTF-8 beginning with gzip magic bytes and highly
compressible data whose decoded form exceeds the ceiling. Neither input may
throw an uncaught exception, exhaust the configured subprocess memory, or
abort the Doctor.

## JSON and Protocol Certification

After an identity body passes byte and UTF-8 checks:

1. decode JSON with exceptions enabled and the Draft-03 depth ceiling;
2. require the expected top-level object shape;
3. certify it with the existing `MSitemapDocument` or `MUrlDocument`;
4. re-encode the decoded value with the existing `JcsEncoder`;
5. require the canonical bytes to equal the original body byte-for-byte; and
6. derive the expected identity metadata from those original bytes.

Re-encoding catches noncanonical ordering, whitespace, escaping, and duplicate
member loss because the reconstructed canonical bytes cannot equal the
original duplicate-bearing input.

The Doctor reuses the existing pure Draft-03 certifiers. It does not add a
second JCS implementation, normalize a public body into a passing value, or
rewrite the response before hashing.

Cross-resource checks compare each sampled authoritative M-URL identity ETag
with its M-Sitemap hint. Missing optional hints are reported distinctly from
incorrect hints.

## Check Evaluation and Failure Precedence

Within one probe, evaluation uses this deterministic order:

1. safety and resource ceilings;
2. transport completion;
3. HTTP status;
4. bounded metadata syntax;
5. content coding and raw-byte identity;
6. UTF-8, JSON, schema, profile, and canonical form;
7. hash, ETag, digest, length, `Vary`, `Cache-Control`, and link relations; and
8. cross-resource hints and repeated-response stability.

An earlier failure prevents dependent interpretation but not unrelated,
budget-permitted probes.

Classification rules:

- an observed protocol or delivery-path mismatch is `fail`;
- cross-origin redirects and unsafe delivery behavior are `fail`;
- timeout, unavailable loopback, local DNS policy, or an exhausted total
  Doctor budget is `inconclusive`;
- a check whose prerequisite was not obtained is `not_tested`;
- an over-limit public entity is `fail`;
- an over-limit or malformed diagnostic report is an internal
  `inconclusive`, never a partial pass; and
- `stale` is an overlay applied to a previously stored report, not a live
  probe outcome.

No caught failure is converted to `pass`.

## Report Schema and Authoritative Encoding

The JSON export uses one versioned schema:

```text
tct-deployment-doctor-report-v1
```

The report contains:

- schema identifier;
- plugin version and Doctor implementation version;
- start and completion timestamps;
- source home origin and derived public paths, without credentials;
- accepted limits and actual counters;
- the three layer results;
- checks in deterministic contract order;
- allowlisted provider/delivery signals;
- evidence freshness data; and
- the exact rerun action.

Every check contains bounded forms of:

- identifier;
- outcome;
- evidence layer;
- observed public URI;
- expected status or metadata;
- observed status or metadata;
- failure code;
- likely layer and `observed` or `inference` attribution;
- recipe identifier, if any; and
- rerun action.

The authoritative compact encoding is:

```php
wp_json_encode(
    $report->toArray(),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
)
```

The exact resulting bytes are used for storage and export. HTML rendering
uses the DTO fields with WordPress escaping; it does not regenerate or expose
response bodies.

To keep serialization safely below 2 MiB before encoding:

- maximum checks: 64;
- maximum provider signals: 32;
- check and signal identifiers: 64 ASCII bytes;
- reported URI: 2 KiB;
- each expected, observed, detail, action, or recipe field: 4 KiB;
- provider signal value: 256 bytes; and
- aggregate report text before JSON encoding: 128 KiB; and
- no raw body, unfiltered header collection, exception trace, or request
  credential field.

All strings are truncated by a UTF-8-safe bounded helper that marks
truncation. The aggregate ceiling ensures that even the maximum sixfold JSON
escaping expansion remains comfortably below 2 MiB. A generated worst-case
report with maximum counts, maximum-length fields, and maximum JSON escaping
must prove that bound. Every returned report has a final assertion:

```text
strlen(authoritative_compact_json) <= 2 MiB
```

If that assertion fails, storage and export are refused and a minimal fixed
internal-error report is produced.

Generated parity tests assert that the bytes measured for storage, download,
and the final limit check are the same authoritative encoded bytes for every
outcome class.

## Truthful Layer Results

Checkpoint 1 must not over-attribute evidence.

### `origin_implementation`

The in-process package and existing tests are not a live bypass around a CDN
or web server. Unless Checkpoint 1 later obtains separately reviewed
origin-only evidence, the runtime Doctor reports this layer as `not_tested`.
It may link to package/disposable evidence, but it must not convert that link
to a live pass.

### `wordpress_cache_integration`

Detection of a WordPress caching product is diagnostic only. Because
Checkpoint 1 has no adapter and performs no configuration introspection that
can prove behavior, this layer is normally `not_tested`. A directly observed
WordPress transport failure may make it `inconclusive`, but product presence
is never a pass or fail by itself.

### `public_delivery_path`

Only this layer receives the end-to-end public HTTP result. It passes only when
all mandatory, runnable public-path checks pass. A mandatory
`inconclusive`/`not_tested` check prevents a layer pass.

The report may say a provider is the **likely** layer only as an explicitly
labelled inference.

## Provider and Delivery Signals

Signal detection is allowlisted and bounded. Candidate observations include:

- known active WordPress cache/optimization plugin slugs;
- known public constants or classes without reading private options;
- bounded `SERVER_SOFTWARE`;
- `Server`, `Via`, `Age`, `CF-Cache-Status`, `X-Cache`,
  `X-LiteSpeed-Cache`, `X-Turbo-Charged-By`, and `Content-Encoding`
  response metadata; and
- presence, not value, of other separately reviewed provider markers.

Signal code does not:

- enumerate or export all headers, environment variables, plugins, options, or
  filesystem paths;
- read credentials or provider tokens;
- call provider APIs;
- infer causation from a product name; or
- alter an outcome from fail to pass.

Recipe links are local, versioned documentation identifiers. Checkpoint 1 does
not fetch recipe content remotely.

## Administrator UI, Storage, and Export

The page requires `manage_options`. Run and export actions also independently
check capability and a purpose-specific nonce.

The initial page shows:

- a short explanation of read-only operation;
- compatibility mode as `diagnostics only` because Checkpoint 1 adds no
  strict/advanced behavior setting;
- derived route patterns;
- read-only internal cache namespace, epoch, and generation status, explicitly
  separated from public-path evidence;
- the explicit run button;
- the three independent layer cards;
- deterministic check rows;
- observed versus inferred attribution;
- result time and stale status;
- local recipe references;
- the external-validator command; and
- a secret-free JSON export action.

The report is stored for one hour in a user-owned transient keyed by:

- current user ID; and
- a cryptographically random 128-bit job identifier.

The stored value is the exact authoritative compact JSON, not a PHP object.
Loading or exporting a result requires the same administrator, a valid job
identifier, and a fresh nonce for export. Expiry, plugin-version mismatch, or
route-configuration mismatch marks a surviving report `stale` or unavailable;
it never silently presents old evidence as current.

The export response uses JSON content type, attachment disposition, and
`Cache-Control: no-store`. It contains no response bodies or credentials.

No Doctor action advances the cache epoch, clears a transient owned by TCT's
representation cache, changes a post, writes a cache-product option, or invokes
a purge API.

## External Validator Handoff

When loopback is unavailable, the UI displays a command derived from
`home_url` and the configured sitemap path. It contains no credential.

Before Checkpoint 1 acceptance, the external script must be made raw-byte safe:

- automatic decompression disabled;
- body retained as bytes before any text conversion;
- strict UTF-8 applied only to an identity representation;
- gzip and unsupported codings reported as typed checks;
- the same request, byte, sample, redirect, and time ceilings; and
- the same report identifiers where practical.

The existing `scripts/validate-live.ps1` may be repaired or a dedicated
Doctor script may be added. It must no longer attempt strict UTF-8 decoding of
gzip bytes before inspecting `Content-Encoding`.

The external result is separate evidence. It is not posted back to WordPress
and does not create a public import endpoint in Checkpoint 1.

## Verification Matrix

### Pure Unit and Generated Tests

An injected fake transport must cover:

- every outcome and typed failure code;
- exact request order and deterministic sample selection;
- request reservation and redirect-hop accounting;
- same-origin and cross-origin redirects;
- per-body, total-body, request, redirect, and time boundaries at maximum
  minus one, maximum, and maximum plus one;
- raw identity, gzip magic, invalid gzip, gzip expansion beyond the ceiling,
  unsupported coding, and invalid UTF-8;
- weak, missing, malformed, and mismatched ETags;
- digest, length, `Vary`, `no-transform`, link, profile, schema, JCS, and hint
  failures;
- conditional, `HEAD`, unsafe-method, and stability behavior;
- prerequisite-to-`not_tested` propagation;
- fixed failure precedence;
- all three layer derivations;
- UTF-8-safe field truncation;
- exact compact report encoding parity; and
- generated worst-case diagnostic size below 2 MiB.

### WordPress Adapter Tests

Stubbed WordPress tests must prove:

- safe transport and exact fixed arguments;
- automatic decompression and automatic redirects remain disabled;
- cookies and authorization are not forwarded;
- target origin cannot come from request input;
- capability and nonce enforcement for run and export;
- no probe occurs on ordinary page load;
- user/job report isolation and expiry;
- no route, post, epoch, cache, option, or purge mutation;
- bounded allowlisted provider signals;
- `no-store` export behavior; and
- additive autoloading without changing `TCT\Draft03`.

### Disposable End-to-End Evidence

Run against minimum and current supported WordPress/PHP lanes with:

- pretty permalinks;
- plain permalinks;
- no selected page cache;
- a loopback-capable multi-worker lane;
- a deliberately loopback-incapable/single-worker lane;
- the existing public-route test suite unchanged; and
- repeated fresh-container runs producing semantically identical reports.

The no-cache lanes should pass the public path. The restricted loopback lane
should complete quickly as `inconclusive` and display the external command.

### Subprocess Safety Evidence

Under a deliberately constrained PHP memory limit, prove that:

- a maximum accepted body completes;
- a raw body over the limit cannot pass;
- a gzip expansion over the limit does not panic, abort, or exhaust memory;
- maximum diagnostic content remains below 2 MiB; and
- malformed bytes and transport failures produce a bounded report.

### Public Negative Evidence

The current `llmpages.org` alpha.3 lane is expected to classify the observed
gzip transformation, weak ETag, and missing delivered `no-transform` as public
path failures until its delivery configuration changes.

Checkpoint 1 acceptance does **not** require that site to become green. It
requires the Doctor and external validator to classify the observed lane
correctly, safely, and without asserting which detected layer caused it.

## Acceptance Gate

Checkpoint 1 could be presented for project-owner review only when:

1. all existing alpha.3 representation and route tests remain unchanged and
   green;
2. all new boundary, generated, WordPress, and subprocess tests pass;
3. the implementation changes only the reviewed Doctor-owned files, additive
   wiring, and tests;
4. no cache adapter, purge, third-party option mutation, or route behavior is
   present;
5. the packaged plugin has not been rebuilt;
6. the live site has not been changed;
7. the evidence packet records source commit, commands, versions, results, and
   known negative lanes;
8. the report is demonstrably body-free, credential-free, bounded, and
   deterministic; and
9. loopback failure is demonstrably `inconclusive`.

The mandatory owner stop reviewed:

- outcome classifications;
- diagnostic safety;
- truthful layer attribution;
- resource-boundary evidence;
- disposable and public negative evidence; and
- unchanged alpha.3 behavior.

Passing Checkpoint 1 does not authorize Checkpoint 2.

## Future Implementation Sequence

After this documentation plan receives separate approval, implement in these
reviewable commits:

1. pure DTOs, limits, outcome derivation, and authoritative report serializer;
2. fake transport and generated resource/report boundary tests;
3. WordPress safe HTTP transport and bounded content-coding diagnostics;
4. deterministic Doctor orchestration and pure protocol certification;
5. administrator action, user-owned report storage, and export;
6. diagnostic-only provider signals and local recipe identifiers;
7. raw-byte-safe external validator;
8. WordPress, subprocess, disposable, and public negative evidence; and
9. evidence/documentation closure followed by the mandatory stop.

No step may include an adapter or purge merely because a provider was
detected.

## Stop Gate

The implementation candidate is recorded by:

- `f9dc0b9ff74834092471eedf51afdc40f6ada5b9` — bounded read-only Doctor,
  isolated WordPress adapter, administrator UI, and tests; and
- `c37a09d18c65af239bda26a63c3f9a1edd016afc` — raw-byte-safe external
  validator and static acceptance checks.

Stop at the mandatory Checkpoint 1 evidence review.

No additional PHP, adapter, purge, package, deployment, provider setting,
WordPress setting, cache, CDN, live-site, or Checkpoint 2 change is authorized.
