# W3 Total Cache Alpha.6 Controlled Docker Evidence

Status: controlled strict configuration passes; public confirmation not
authorized

Evidence date: 2026-07-28

The subsequently authorized exact Namecheap/LiteSpeed public checkpoint is
recorded in
[`NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md`](NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md).

## Decision

W3 Total Cache is not inherently incompatible with TCT. The exact tested
W3 Total Cache `2.10.3` configurations preserved TCT Alpha.6 after:

1. Page Cache exclusions covered the origin root-discovery route and every
   derived TCT machine route;
2. Browser Cache stopped applying its `Other files` compression, expiry, ETag,
   and last-modified rules to `application/json`; and
3. the pre-exclusion page cache was explicitly flushed.

Pretty permalinks passed with Disk Enhanced Page Cache. True WordPress plain
permalinks passed with Disk Basic Page Cache and query-string caching enabled.
W3TC itself rejects Disk Enhanced with the default plain permalink structure,
so Disk Enhanced is not a valid plain-permalink configuration and its
non-operational validator pass is not compatibility evidence.

Default activation and unexcluded Disk Enhanced Page Cache both produced
valid negative counterexamples. A W3TC installation must not be described as
compatible merely because the plugin is detected, active, or configured with
M-URL exclusions alone.

This is controlled local evidence. It does not establish an Internet-facing
`Verified`, `Guided`, or `Supported by adapter` lane.

## Scope and Integrity

The controlled test used:

- source checkpoint before this packet:
  `c2afb43760439715acc87bd2ae545ab5c37a9175`;
- exact TCT package: `3.0.0-alpha.6`;
- TCT ZIP SHA-256:
  `387659f8996e3051dd43d6caacdbaa0e92d416e02c9d43a89d70c97a81b3bf42`;
- W3 Total Cache package: `2.10.3`, downloaded from WordPress.org;
- W3TC ZIP SHA-256:
  `7cb9e635ed02e666abd7879b71eb9f41ea78b4bcd11cb7abd28188a0d864ee93`;
- WordPress `7.0.2`;
- PHP `8.3.32`, 64-bit integers, with `mbstring`;
- Apache `2.4.68`;
- MariaDB `10.11.18`;
- WordPress CLI container for installation and deterministic fixture actions;
  and
- Docker Engine `29.5.3`.

The exact container image IDs were:

```text
wordpress:7.0.2-php8.3-apache
sha256:9fac4d47b61186131ffefb5d966f0045d0eea94bfd7bd40cafae29b78a709d1b

mariadb:10.11
sha256:be981e4113326ada8d6004174dd09eeaefc03094037f811182a52d4f2e737350

wordpress:cli-php8.3
sha256:f8aeb68164c6a04f5dcc91da30d8ffa096b0f7fafb7a65f144c2dd62587caca0
```

The retained Alpha.6 ZIP was installed without a source bind mount. The
external validator ran from the Windows host outside the WordPress container.
This is an independent process/network vantage within the controlled
workstation, not an off-site Internet observation.

Apache listened on container port `80` and on the installed site's test port
(`8097` for the pretty-permalink lane and `8098` for the plain-permalink
lane). This let both the Windows validator and the WordPress-container Doctor
request the installed `home_url`. No proxy, CDN, host page cache, or external
compression layer was present.

## Stage A: No-Cache Baseline

The disposable installation contained:

- one deterministic published post;
- one deterministic published page;
- pretty permalinks; and
- the default `/llm-sitemap.json` and `/llm/` TCT routes.

The external Alpha.6 validator passed:

```text
schema: tct-external-validator-report-v1
ok: true
requests: 16
sampled_murls: 3
checks: 97
failed: 0
response_bytes: 73785
elapsed_seconds: 2.314
```

The site-initiated Deployment Doctor also passed:

```text
schema: tct-deployment-doctor-report-v1
public_delivery_path: pass
checks: 52
failed: 0
requests: 18
response_bytes: 74596
serialized_bytes: 21850
```

As designed, the Doctor retained:

```text
origin_implementation: not_tested
wordpress_cache_integration: not_tested
```

The deterministic three-item M-Sitemap baseline was:

```text
bytes: 811
ETag: "sha256-e7897a10ead62be55befafc9c71bb247bacf247b1ad1aed9db56e71a2ee80d9e"
```

## Stage B1: W3TC Active, Page Cache Disabled

W3 Total Cache `2.10.3` activation produced:

```text
pgcache.enabled: false
pgcache.engine: file_generic
browsercache.enabled: true
minify.enabled: false
```

Although Page Cache remained disabled, the default Browser Cache configuration
failed TCT:

```text
external checks: 97
external failed: 12
external requests: 16
external response_bytes: 73238
external elapsed_seconds: 2.044

Doctor checks: 52
Doctor failed: 4
Doctor requests: 18
Doctor response_bytes: 74837
```

Every external failure belonged to the advertised-gzip sitemap or M-URL lane.
The Doctor reported the two coded responses and their two changed ETags.

For the `811`-byte M-Sitemap:

```text
identity:
  Content-Encoding: none
  Content-Length: 811
  ETag: "sha256-e7897a10ead62be55befafc9c71bb247bacf247b1ad1aed9db56e71a2ee80d9e"

gzip advertised:
  Content-Encoding: gzip
  Content-Length: 389
  ETag: "sha256-e7897a10ead62be55befafc9c71bb247bacf247b1ad1aed9db56e71a2ee80d9e-gzip"
```

The coded response retained the identity-derived `Content-Digest`. Browser
Cache also added a one-year `Expires` value and a second
`max-age=31536000` directive to TCT's own `Cache-Control`.

The generated Apache rules confirmed that W3TC's `Other files` section
included `application/json` in its DEFLATE and expiry rules. This is a Browser
Cache configuration condition, not a TCT Page Cache result.

## Stage B2: Browser Cache Disabled

With W3TC active, Page Cache disabled, and Browser Cache disabled, both
validators returned to a clean pass:

```text
external checks: 97
external failed: 0
external requests: 16
external response_bytes: 74026
external elapsed_seconds: 2.253

Doctor checks: 52
Doctor failed: 0
Doctor requests: 18
Doctor response_bytes: 74837
```

The gzip-advertised M-Sitemap again selected the exact `811` identity bytes,
strong ETag, digest, length, cache policy, and no coding.

This isolates default Browser Cache behavior as sufficient to break the tested
TCT lane.

## Stage B3: Browser Cache Retained with Other-File Rules Disabled

The narrower tested Browser Cache configuration was:

```text
browsercache.enabled: true
browsercache.other.compression: false
browsercache.other.expires: false
browsercache.other.cache.control: false
browsercache.other.etag: false
browsercache.other.last_modified: false
```

This preserves W3TC Browser Cache as a module for its separately configured
HTML, CSS/JavaScript, and media groups while preventing its `Other files`
group from changing TCT's `application/json` responses.

With Page Cache still disabled:

```text
external checks: 97
external failed: 0
external requests: 16
external response_bytes: 74026
external elapsed_seconds: 2.153

Doctor checks: 52
Doctor failed: 0
Doctor requests: 18
Doctor response_bytes: 74837
```

The selected TCT responses had no W3TC-added coding, expiry, ETag suffix, or
cache-policy directive.

## Stage B4: Disk Enhanced Page Cache Without Exclusions

The next configuration enabled:

```text
pgcache.enabled: true
pgcache.engine: file_generic
```

The Browser Cache `Other files` restrictions from Stage B3 remained active.
The default Page Cache rejection list contained only:

```text
wp-.*\.php
index\.php
```

Without TCT exclusions, Disk Enhanced Page Cache failed in both cold and warm
states:

| State | Checks | Failed | Requests | Response bytes | Seconds |
| --- | ---: | ---: | ---: | ---: | ---: |
| Cold/fill | 97 | 37 | 16 | 77,311 | 1.700 |
| Warm | 97 | 46 | 16 | 74,085 | 0.535 |

After the warm state, the Doctor failed closed before dependent M-URL
sampling:

```text
Doctor checks: 18
Doctor failed: 12
Doctor requests: 8
Doctor response_bytes: 73339
```

Disk Enhanced Page Cache stored TCT JSON bodies in active `.html` and
`.html_gzip` cache rows. A warm identity M-Sitemap response was:

```text
Content-Type: text/html; charset=UTF-8
Content-Length: 811
ETag: "32b-657b149760120"
```

It omitted the TCT profile Link, `Content-Digest`, `Vary`, and `no-transform`.
The page cache also prevented conditional, HEAD-validator, and
identity-prohibition behavior from reaching WordPress. The cached homepage
omitted TCT's generic M-Sitemap discovery Link.

This proves that machine-route exclusions alone are insufficient if the
origin root used for generic M-Sitemap discovery remains cached without the
required Link.

## Stage C: Strict Configuration

The passing Page Cache rejection array was:

```text
wp-.*\.php
index\.php
^/$
^/llm-sitemap\.json(?:\?.*)?$
(?:^|/)llm/?(?:\?.*)?$
[?&]tct_m_url=1(?:&|$)
```

W3TC `2.10.3` documents this administration field as **Never cache the
following pages** and validates its entries as regular expressions. Source
inspection confirmed that the expressions are matched case-insensitively
against W3TC's request URI.

The `^/$` entry is required for this configuration because the origin root is
TCT's generic M-Sitemap discovery resource and W3TC's warm homepage cache did
not preserve its HTTP Link. It does not disable page caching for ordinary
posts and pages.

The test explicitly ran W3TC's site-wide `flush all` command after installing
the exclusions. Historical cache files renamed with W3TC's `_old` suffix
remained in the disposable cache directory, but no old TCT row was active or
served after the flush.

The strict cold and warm results were:

| State | Checks | Failed | Requests | Response bytes | Seconds |
| --- | ---: | ---: | ---: | ---: | ---: |
| Cold | 97 | 0 | 16 | 74,090 | 2.094 |
| Warm | 97 | 0 | 16 | 74,090 | 1.521 |

The Doctor independently passed the selected site-initiated matrix:

```text
Doctor checks: 52
Doctor failed: 0
Doctor requests: 18
Doctor response_bytes: 74901
```

Two repeated identity requests to the ordinary human-facing post produced an
active Disk Enhanced cache row. No active M-Sitemap or M-URL cache row was
created after strict exclusions. W3TC Page Cache therefore remained
operational for ordinary post content rather than being disabled site-wide.

## Query-Form M-URLs

The deterministic post and page were each requested using:

```text
?tct_m_url=1
```

For both resources:

- status was `200`;
- content type was exact `application/json`;
- body bytes were identical to the corresponding pretty M-URL;
- the strong ETag was identical to the corresponding pretty M-URL;
- `Content-Digest` and `Content-Length` were exact; and
- no query-specific W3TC Page Cache row was active.

## True Plain-Permalink Lane

A second clean disposable installation used WordPress's true default
permalink structure rather than testing query-form M-URLs only inside the
pretty-permalink site. Its three authoritative sitemap entries used:

```text
home M-URL: /llm/
post C-URL: /?p=4
post M-URL: /?p=4&tct_m_url=1
page C-URL: /?page_id=5
page M-URL: /?page_id=5&tct_m_url=1
```

W3TC's environment setup rejected the initially requested Disk Enhanced
engine:

```text
Disk Enhanced mode can't work with "Default" permalinks structure
```

The validators happened to pass while that enhanced cache was not
operational. Those results are deliberately excluded from the compatibility
decision.

The applicable passing plain-permalink configuration instead used:

```text
pgcache.enabled: true
pgcache.engine: file
pgcache.cache.query: true

browsercache.enabled: true
browsercache.other.compression: false
browsercache.other.expires: false
browsercache.other.cache.control: false
browsercache.other.etag: false
browsercache.other.last_modified: false
```

In W3TC `2.10.3`, `file` is the Disk Basic engine and `file_generic` is Disk
Enhanced. The same strict Page Cache rejection array remained installed:

```text
wp-.*\.php
index\.php
^/$
^/llm-sitemap\.json(?:\?.*)?$
(?:^|/)llm/?(?:\?.*)?$
[?&]tct_m_url=1(?:&|$)
```

After environment regeneration and an explicit site-wide flush, the
operational Disk Basic results were:

| State | Checks | Failed | Requests | Response bytes | Seconds |
| --- | ---: | ---: | ---: | ---: | ---: |
| Cold | 97 | 0 | 16 | 73,345 | 2.662 |
| Warm | 97 | 0 | 16 | 73,345 | 1.858 |

The Doctor independently passed:

```text
Doctor checks: 52
Doctor failed: 0
Doctor requests: 18
Doctor response_bytes: 74098
```

Two requests to the ordinary human-facing `/?p=4` produced active Disk Basic
page-cache storage. Inspection confirmed that the cached payload was the
ordinary HTML post and retained its M-URL alternate Link; no cached payload
contained the TCT M-URL profile relation. This establishes that query caching
was operational for the ordinary C-URL while the TCT query-form M-URL was
excluded.

A create/edit/delete lifecycle in this lane changed both sitemap and M-URL
ETags, kept the sitemap hint synchronized, returned `304` only for each exact
current validator, returned `200` for stale validators, and restored the exact
baseline sitemap body and ETag after deletion. The former M-URL returned
`404`.

After restarting WordPress/Apache, the external validator again passed:

```text
external checks: 97
external failed: 0
external requests: 16
external response_bytes: 73345
external elapsed_seconds: 2.796
```

The final three-item plain-permalink M-Sitemap was:

```text
bytes: 753
ETag: "sha256-3ae967a949802e16b4a216a4ddb80feee80ca4817a7d1bd4927dccc3d6bef825"
```

The final safe plain-permalink configuration digests were:

```text
W3TC master configuration:
28ce958e100823c718ab807fd24443b6f070d48c301dd4d255c01067d0c1cb2b

.htaccess:
b3b290a0bdaf19ea99a1097a8e20fa3555cca404d7a00b0dfa7ad76c0edde9fe
```

## Custom Route Derivation

The disposable TCT configuration was changed to:

```text
M-Sitemap: /machine-catalog.json
M-URL suffix: machine
```

The W3TC rejection expressions were correspondingly derived as:

```text
^/$
^/machine-catalog\.json(?:\?.*)?$
(?:^|/)machine/?(?:\?.*)?$
[?&]tct_m_url=1(?:&|$)
```

The custom-route validator passed:

```text
external checks: 97
external failed: 0
external requests: 16
external response_bytes: 74154
external elapsed_seconds: 2.255
```

The custom-route Doctor also passed:

```text
Doctor checks: 52
Doctor failed: 0
Doctor requests: 18
Doctor response_bytes: 74977
```

The observed root Link identified both `/machine/` and
`/machine-catalog.json`. The defaults and their derived exclusions were
restored before lifecycle testing.

## Create, Edit, Trash, Restore, and Delete

Starting from the exact three-item baseline:

### Create

- a fourth published post became discoverable;
- the M-Sitemap ETag changed;
- its authoritative M-URL returned `200`;
- its M-Sitemap hint matched the authoritative strong ETag; and
- no stale TCT Page Cache row was served.

### Edit

- visible title and body content changed;
- both M-Sitemap and M-URL ETags changed;
- the edited marker appeared in the authoritative M-URL; and
- the new sitemap hint matched the new authoritative M-URL ETag.

Using exact raw HTTP ETag field values:

```text
stale M-Sitemap ETag: 200
current M-Sitemap ETag: 304
stale M-URL ETag: 200
current M-URL ETag: 304
```

### Trash and Restore

- after trash, the sitemap item was absent and the former M-URL returned
  `404`;
- after restore, the item returned and its M-URL returned `200`; and
- the restored sitemap hint again identified the authoritative representation.

### Permanent Delete

- the item was absent;
- the former M-URL returned `404`;
- the M-Sitemap returned to exactly three items;
- the exact baseline ETag was restored; and
- the complete baseline body was byte-identical.

## Internal Epoch Boundary

After lifecycle cleanup, the TCT internal representation-cache epoch was
advanced from `6` to `7`.

No content had changed, so the rebuilt M-Sitemap retained the exact same body
and strong ETag. W3TC Page Cache remained a separate mechanism; no external
purge was implied by the epoch action.

## Large Representation

A fresh post produced a `42,880`-byte authoritative M-URL. Raw requests with
`Accept-Encoding: identity` and `Accept-Encoding: gzip` both returned:

- exactly `42,880` uncoded bytes;
- no `Content-Encoding`;
- byte-identical bodies;
- the same strong ETag;
- the same `Content-Digest`;
- exact `Content-Length`; and
- a sitemap hint matching the authoritative ETag.

Permanent deletion restored the exact baseline sitemap body and ETag. This
direct probe avoids relying only on the validator's bounded first-M-URL
compression selection.

## Restart Persistence

The WordPress/Apache container was restarted without changing W3TC settings.
After restart:

```text
external checks: 97
external failed: 0
external requests: 16
external response_bytes: 74090
external elapsed_seconds: 1.981
```

Page Cache remained enabled, the complete rejection array persisted, and the
Browser Cache `Other files` compression and expiry settings remained disabled.

The final three-item M-Sitemap was again:

```text
bytes: 811
ETag: "sha256-e7897a10ead62be55befafc9c71bb247bacf247b1ad1aed9db56e71a2ee80d9e"
```

The final safe configuration digests were:

```text
W3TC master configuration:
35cd602ec6c50224ddf1e65a7b431b04ecee9a047b68340acb3a21fcfdd5c000

.htaccess:
1de655d744f2f041e9d59eb1c143a3c0f41fc66f609515460639a9d628bdd681
```

## Classification

Under
[`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md):

- W3TC `2.10.3` default Browser Cache behavior is **Incompatible** with the
  tested Alpha.6 identity-only response lane;
- W3TC `2.10.3` Disk Enhanced Page Cache without derived TCT/root exclusions
  is **Incompatible**;
- pretty permalinks with the exact controlled Disk Enhanced strict
  configuration are a **passing controlled candidate** for a future Guided
  recipe;
- plain permalinks with the exact controlled Disk Basic strict configuration
  and query caching enabled are a **passing controlled candidate**;
- Disk Enhanced is **not applicable** to W3TC's default plain permalink
  structure;
- an Internet-facing W3TC deployment remains **Unknown**.

The project must not publish a Guided or provider-wide support claim until the
same exact packages and configuration pass on a clean disposable public host
without a confounding CDN, host cache, transformation layer, or browser
challenge.

## Relevant Upstream Source

The W3 Total Cache package and current release metadata came from:

- <https://wordpress.org/plugins/w3-total-cache/>
- <https://downloads.wordpress.org/plugin/w3-total-cache.2.10.3.zip>

The installed open-source package was used to verify the exact public
configuration keys, CLI operations, regular-expression field, and request-URI
matching behavior for version `2.10.3`.

## Cleanup and Privacy

Both installations' uniquely named WordPress, MariaDB, and CLI containers,
data volumes, and Docker networks were removed. Verification found no
remaining resource with the test prefix.

No live website, production account, host setting, CDN, Worker, retained TCT
artifact, or external WordPress installation changed. No administrator
credential, database credential, cookie, authorization header, nonce,
Application Password, private origin, or response body is committed.

## Stop

This is the mandatory controlled-evidence stop. It does not authorize:

- a public-host test;
- publication of a W3TC compatibility recipe;
- a W3TC adapter or automatic settings mutation;
- targeted or site-wide purge integration;
- Alpha.7;
- plugin-source or package changes;
- Cloudflare or another CDN lane; or
- production or provider-wide compatibility claims.
