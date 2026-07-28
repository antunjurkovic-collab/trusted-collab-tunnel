# Namecheap W3 Total Cache Alpha.6 Public Evidence

Status: exact public deployment passes; no provider-wide compatibility claim

Evidence date: 2026-07-28

## Decision

TCT `3.0.0-alpha.6` and W3 Total Cache `2.10.3` can coexist on the exact
public Namecheap shared-host installation at `tct.llmpages.org`.

The passing configuration:

- serves the test hostname directly from Namecheap using a Cloudflare
  **DNS only** record;
- does not invoke the `llmpages.org` Cloudflare Worker;
- keeps W3TC Disk Enhanced Page Cache operational for ordinary
  human-facing posts;
- excludes the root discovery resource and default TCT machine routes;
- prevents W3TC Browser Cache's `Other files` group from transforming TCT
  JSON; and
- requires an explicit purge after the exclusions are installed.

This is evidence for one exact deployment and configuration. Namecheap's
LiteSpeed server remains part of the delivery path, and a shared-host server
restart was unavailable. The result is not a claim that every Namecheap,
LiteSpeed, W3TC, WordPress, proxy, or CDN configuration is compatible.

## Integrity and Environment

The test used:

- source/evidence checkpoint before this packet:
  `4e03915`;
- exact TCT package: `3.0.0-alpha.6`;
- TCT ZIP SHA-256:
  `387659f8996e3051dd43d6caacdbaa0e92d416e02c9d43a89d70c97a81b3bf42`;
- exact W3 Total Cache package: `2.10.3`, downloaded from WordPress.org;
- W3TC ZIP SHA-256:
  `7cb9e635ed02e666abd7879b71eb9f41ea78b4bcd11cb7abd28188a0d864ee93`;
- WordPress `7.0.2`;
- public origin: `198.54.116.200`;
- observed server: LiteSpeed;
- valid public TLS for both `tct.llmpages.org` and
  `www.tct.llmpages.org`;
- pretty WordPress permalinks; and
- default TCT routes `/llm-sitemap.json`, `/{canonical}/llm/`, and
  `?tct_m_url=1`.

The public validator ran in a disposable PowerShell container configured to
resolve through `1.1.1.1`. This avoided a pre-existing negative response in
the workstation's ISP resolver without modifying workstation or public DNS.
The site-initiated Doctor ran independently through WordPress's HTTP
transport.

## Public DNS and TLS Boundary

The authoritative/public records were:

```text
tct.llmpages.org      A      198.54.116.200      DNS only
www.tct.llmpages.org  CNAME  tct.llmpages.org    DNS only
```

The apex `llmpages.org` remained proxied and unchanged. Its Cloudflare
nameservers and production Worker routes remained intact. The test did not
pause Cloudflare, alter nameservers, remove the Worker, or expose the test
through the Cloudflare HTTP proxy.

## Alpha.6 No-W3TC Baseline

The clean WordPress installation initially contained only inactive Akismet
and Hello Dolly. The exact Alpha.6 ZIP was uploaded and activated.

The external public matrix passed:

```text
schema: tct-external-validator-report-v1
ok: true
checks: 97
failed: 0
requests: 16
sampled_murls: 3
response_bytes: 75052
elapsed_seconds: 7.490
```

The site-initiated Doctor also passed:

```text
schema: tct-deployment-doctor-report-v1
plugin_version: 3.0.0-alpha.6
overall: pass
checks: 52
failed: 0
requests: 18
sampled_murls: 3
response_bytes: 75849
elapsed_seconds: 1.494775
```

As designed, the independent layer outcomes remained:

```text
origin_implementation: not_tested
wordpress_cache_integration: not_tested
public_delivery_path: pass
```

The deterministic baseline contained three sitemap items:

```text
bytes: 797
ETag: "sha256-1f7009c06871eed84b6eaffbd68840fc881065b1f16a0a3a988486554b6e1e10"
```

## W3TC Activation Boundary

W3TC activation forced its first-run Setup Guide. Before the guide was
skipped:

```text
pgcache.enabled: false
pgcache.engine: file_generic
browsercache.enabled: true
```

An external `97/97` pass at that point is not Page Cache evidence because
Page Cache was disabled and the generated cache environment was not yet the
tested strict state.

The Setup Guide was skipped without opting into usage telemetry. The actual
settings pages then became available.

## Browser Cache Restrictions

The following W3TC `Other files` settings were disabled and verified:

```text
browsercache.other.compression: false
browsercache.other.expires: false
browsercache.other.cache.control: false
browsercache.other.etag: false
browsercache.other.last_modified: false
```

W3TC Browser Cache remained enabled for its separately configured CSS,
JavaScript, HTML, and media groups.

## Namecheap Page Cache Exclusions

Namecheap's LiteSpeed request-filtering layer returned `403 Forbidden` when
the controlled Docker packet's more expressive regular-expression array was
submitted through W3TC's legitimate administration form. Page Cache remained
disabled during those rejected submissions.

One conservative, UI-submitted alternative was accepted and verified:

```text
wp-
index.php
^/$
llm-sitemap.json
/llm/
tct_m_url=1
```

This is the exact Namecheap-tested array. W3TC treats each line as a regular
expression matched against the request URI:

- `wp-` conservatively rejects WordPress administrative/system paths;
- `index.php` retains a conservative equivalent of the default index
  rejection;
- `^/$` excludes the origin root because it carries generic TCT discovery;
- `llm-sitemap.json` excludes the default M-Sitemap;
- `/llm/` excludes the default pretty M-URL suffix; and
- `tct_m_url=1` excludes query-form M-URLs.

The first two entries are broader than W3TC's defaults. They do not disable
ordinary post or page caching. This public checkpoint did not test custom
TCT route names; a custom route would require freshly derived and separately
validated exclusions.

After the exclusions existed, W3TC was configured and verified as:

```text
pgcache.enabled: true
pgcache.engine: file_generic
browsercache.enabled: true
```

In W3TC `2.10.3`, `file_generic` is Disk Enhanced. The final enable operation
used W3TC's own **Save Settings & Purge Caches** action.

## Cold and Warm Public Matrices

Both public states passed:

| State | Checks | Failed | Requests | Sampled M-URLs | Response bytes | Seconds |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Cold | 97 | 0 | 16 | 3 | 75,364 | 9.627 |
| Warm | 97 | 0 | 16 | 3 | 75,364 | 8.515 |

The W3-enabled Doctor independently passed:

```text
schema: tct-deployment-doctor-report-v1
plugin_version: 3.0.0-alpha.6
overall: pass
checks: 52
failed: 0
requests: 18
sampled_murls: 3
response_bytes: 76161
elapsed_seconds: 1.597956
```

After all temporary lifecycle and large-response fixtures were deleted, the
final external matrix passed again:

```text
checks: 97
failed: 0
requests: 16
sampled_murls: 3
response_bytes: 75364
elapsed_seconds: 7.369
```

## Ordinary Human-Page Cache Proof

Two unauthenticated identity requests to the ordinary Hello World C-URL each
returned:

```text
status: 200
bytes: 71739
Last-Modified: Tue, 28 Jul 2026 21:43:36 GMT
ETag: "1183b-6a692288-0;;;"
```

Both responses contained W3TC's exact footer evidence:

```text
Page Caching using Disk: Enhanced
Served from: tct.llmpages.org @ 2026-07-28 21:43:36 by W3 Total Cache
```

This proves that the passing result was not achieved by disabling W3TC Page
Cache site-wide. Ordinary human-facing content remained cached while TCT
machine routes reached WordPress.

## Create, Edit, Trash, Restore, and Delete

A temporary published post was exercised through the complete lifecycle:

- create added a sitemap item, changed the sitemap ETag, returned a `200`
  M-URL, and produced a matching sitemap validator hint;
- edit changed both sitemap and M-URL ETags, exposed the edited content, and
  kept the hint synchronized;
- exact current sitemap and M-URL validators returned `304`;
- stale sitemap and M-URL validators returned `200`;
- trash removed the item and made its M-URL return `404`;
- restore returned the item and a `200` M-URL;
- permanent delete removed the item and retained `404` for the former M-URL;
  and
- cleanup restored the exact baseline sitemap body and ETag.

The conditional results were:

```text
stale M-Sitemap ETag: 200
current M-Sitemap ETag: 304
stale M-URL ETag: 200
current M-URL ETag: 304
```

The final item count was exactly three.

## Large Representation

A separate temporary post produced an `84,872`-byte M-URL.

Requests advertising `identity` and `gzip` both returned:

- status `200`;
- exactly `84,872` bytes;
- no `Content-Encoding`;
- byte-identical bodies;
- identical strong ETags;
- identical `Content-Digest`;
- exact `Content-Length: 84872`; and
- a sitemap hint matching the authoritative M-URL ETag.

Permanent deletion restored the exact baseline sitemap body, ETag, and
three-item count.

## Unavailable Proofs

A shared-host server or PHP restart was not available and was not simulated.
Restart persistence remains proven only by the controlled Docker checkpoint.
Plain permalinks and custom route names were also not tested in this public
lane.

## Classification

Under
[`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md):

- the exact `tct.llmpages.org` Alpha.6/W3TC configuration is a **passing
  public deployment**;
- the Namecheap-compatible strict array is a **candidate** for a versioned
  Guided recipe for the default pretty routes;
- the original controlled strict regex array is **administratively blocked**
  by this exact LiteSpeed shared-host path;
- Namecheap and LiteSpeed remain **Unknown** as provider-wide categories; and
- no adapter or automatic cache mutation is present or authorized.

The combination of controlled Docker and exact public Namecheap evidence is
strong enough to explain a manual deployment recipe. It is not sufficient
for a universal or provider-wide support statement.

## Final Site State and Privacy

The test installation remains intentionally available with:

```text
TCT: 3.0.0-alpha.6 active
W3 Total Cache: 2.10.3 active
Page Cache: Disk Enhanced active
Sitemap items: 3
Temporary evidence posts: 0
```

No production apex DNS, Cloudflare proxy setting, Worker, nameserver, mail
record, plugin source, retained ZIP, or unrelated website changed.

No username, password, cookie, nonce, authorization header, private key,
certificate material, database credential, administrator response, or
private content is recorded in this packet.

## Stop

This evidence does not authorize:

- a provider-wide Namecheap, LiteSpeed, or W3TC compatibility claim;
- publication of a Guided recipe without a separate documentation review;
- a W3TC adapter or automatic settings mutation;
- custom-route or plain-permalink public claims;
- Alpha.7;
- plugin-source or package changes;
- Cloudflare Worker implementation; or
- production deployment.
