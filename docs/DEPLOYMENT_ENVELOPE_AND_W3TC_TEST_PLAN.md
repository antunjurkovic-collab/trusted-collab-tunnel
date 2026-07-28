# TCT Deployment Envelope and W3 Total Cache Test Plan

Status: controlled and exact Namecheap public stages complete; no further
implementation authorized

Plan date: 2026-07-28

Controlled Docker execution evidence is recorded in
[`W3TC_ALPHA6_CONTROLLED_DOCKER_EVIDENCE.md`](W3TC_ALPHA6_CONTROLLED_DOCKER_EVIDENCE.md).
The subsequently authorized exact Namecheap/LiteSpeed public evidence is
recorded in
[`NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md`](NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md).

## Decision

The TCT Draft-03 WordPress reference can produce and serve its required
responses correctly. A complete deployment is compatible only when every
later delivery layer preserves those responses.

TCT should therefore target a documented deployment envelope rather than
claim universal compatibility with every WordPress host, page cache, proxy,
CDN, compression facility, or bot-protection system.

The current experimental plugin generation for new testing is
`3.0.0-alpha.6`. It retains alpha.5's Draft-03 representation behavior and
adds the accepted WordPress URL-path-prefix routing repair.

## Deployment Envelope

A candidate TCT deployment requires:

### WordPress runtime

- 64-bit PHP `8.1` or newer;
- `mbstring`;
- WordPress `6.0` or newer;
- sufficient filesystem and database support for ordinary WordPress
  operation; and
- publicly exposable content selected by the TCT exposure policy.

### Automated-client access

- ordinary unauthenticated HTTP access to public TCT resources;
- no mandatory JavaScript, browser-cookie, CAPTCHA, or interactive challenge;
- no requirement to impersonate a browser; and
- no robots, WAF, or rate-control rule that prevents the bounded validator
  from observing the selected public lane.

Passing only from a browser or from WordPress's own server is insufficient.

### Response preservation

The selected public path must retain:

- exact RFC 8785 JCS identity response bytes;
- `Content-Type: application/json` without an added charset;
- the exact strong body-derived ETag;
- matching `Content-Digest`;
- exact identity `Content-Length`;
- required profile, canonical, and discovery Links;
- `Vary` containing `Accept-Encoding`;
- `Cache-Control` containing `no-transform`;
- no `Content-Encoding` for this identity-only generation;
- identity-prohibited `406`;
- matching-validator `304`;
- matching `GET` and `HEAD` metadata; and
- unsafe-method rejection.

An intermediary must not weaken or replace the ETag, construct a coded
variant while retaining identity metadata, remove required headers, replace
JSON metadata with HTML metadata, or answer a stale route before WordPress.

### Cache integration

TCT's epoch-keyed transient cache is an internal representation cache. It is
not a WordPress page cache, web-server cache, reverse-proxy cache, browser
cache, or CDN purge mechanism.

Strict compatibility requires TCT core routes to bypass page caching and
response transformation unless a separately tested shared-cache contract
exists. Ordinary client caching and conditional requests remain available.

The core route set must be derived from current settings and includes:

- the configured M-Sitemap path;
- the root and nested pretty M-URLs ending in the configured M-URL suffix; and
- query-form M-URLs carrying exact `tct_m_url=1`.

### Verification

Every selected deployment requires two distinct evidence vantages:

1. the administrator-initiated Deployment Doctor, which observes a
   site-initiated request to the configured public name; and
2. the packaged external validator, which observes the lane independently
   from outside WordPress.

Both must pass. A Doctor pass with no external run leaves independent public
access **not tested**. Evidence becomes stale after a relevant plugin, route,
cache, host, proxy, CDN, or security-policy change.

## Environment Selection for W3 Total Cache

### Primary lane: controlled local Docker WordPress

The first W3 Total Cache investigation should reuse the project's disposable
Docker approach:

- exact retained alpha.6 ZIP, installed without a source bind mount;
- WordPress `7.0.2`;
- 64-bit PHP `8.3`;
- Apache;
- MariaDB `10.11`;
- no CDN, reverse proxy, host page cache, or host compression layer;
- deterministic published post and page fixtures; and
- the validator running outside the WordPress container.

This is the best first lane because one variable can be changed at a time,
cold and warm caches can be reproduced, filesystem/drop-in behavior is real,
and the complete environment can be destroyed afterward.

Docker evidence proves the cache-plugin interaction in a controlled network.
It does not by itself prove compatibility through an Internet-facing hosting
stack.

### Secondary lane: clean disposable public host

Only after the controlled matrix passes should the same exact package and
W3 Total Cache configuration be tested on a disposable public WordPress
installation with:

- direct automated-client access;
- operator control over WordPress and W3 Total Cache;
- no mandatory provider page cache or browser challenge;
- no CDN for the first public run; and
- no server-level response transformation that cannot be disabled.

A small temporary VPS or equivalently controlled public Apache WordPress
installation is preferable. Cloudflare should be introduced only in a later,
separate CDN lane after direct public W3 Total Cache evidence passes.

The already characterized InfinityFree Free, Pantheon, TasteWP, and Wasmer
lanes are poor primary W3 Total Cache laboratories because each has a known
independent delivery constraint. `llmpages.org` is also not a clean first lane
while its Cloudflare Worker and LiteSpeed/Cloudflare path remain active.

### Not the primary lane: WordPress Playground

Playground remains useful for TCT routing, extraction, and PHP.wasm
portability. It is not representative for W3 Total Cache page-cache evidence.
W3 Total Cache can depend on filesystem cache state, `advanced-cache.php`,
WordPress cache constants, and web-server rules that do not model an ordinary
persistent Apache deployment in a temporary browser Playground scope.

Playground may later be used only to prove graceful detection or refusal. It
must not support a W3 Total Cache compatibility claim.

## Controlled Test Sequence

The test must pin and record:

- TCT source commit, package version, ZIP SHA-256, and manifest;
- WordPress, PHP, Apache, MariaDB, and W3 Total Cache versions;
- W3 Total Cache installation source and package digest;
- safe configuration digest or exact non-secret settings;
- permalink and TCT route settings;
- test time and validator version; and
- every command and result needed to reproduce the lane.

No credential, cookie, nonce, authorization header, private origin address, or
response body is retained in committed evidence.

### Stage A: no-cache baseline

With W3 Total Cache absent:

1. install the exact alpha.6 ZIP;
2. create deterministic homepage, post, and page fixtures;
3. test pretty permalinks;
4. test plain/query-form routing;
5. run the Deployment Doctor;
6. run the validator from outside the container; and
7. retain exact response and summary measurements without retaining bodies.

Every mandatory check must pass before W3 Total Cache is introduced.

### Stage B: enabled configuration isolation

Install and activate the pinned W3 Total Cache package. Test separately:

1. plugin active with page cache disabled;
2. page cache enabled using the selected documented storage method;
3. cold cache;
4. first fill;
5. warm cache;
6. Browser Cache features, if they are part of the target configuration;
7. compression or optimization features, if separately enabled; and
8. restart/reload behavior.

Do not enable every W3 Total Cache subsystem at once. Page Cache, Browser
Cache, minification, object cache, and other facilities must remain isolated
so evidence can identify which configuration changes the public response.

The observed enabled-state InfinityFree counterexample is retained as negative
discovery evidence, not as the controlled Stage B result.

### Stage C: strict exclusions

Configure W3 Total Cache through its documented administration surface so the
derived TCT core route set bypasses page caching and transformation.

The test must prove:

- the configured M-Sitemap route is excluded;
- root and nested pretty M-URLs are excluded;
- query-form `tct_m_url=1` behavior is preserved;
- a custom M-Sitemap path and custom M-URL suffix remain derivable;
- ordinary non-TCT pages are still cached;
- cold and warm TCT responses remain identical;
- stale pre-exclusion cache entries are explicitly purged;
- cache restart/reload does not lose the exclusions; and
- no TCT DTO, schema, body, profile, validator, or policy changes.

No exact W3 Total Cache exclusion syntax is approved by this plan. The syntax
must be taken from the pinned version's documented public configuration and
proved by route match/non-match tests.

## Mutation and Invalidation Matrix

For the passing strict-exclusion configuration:

1. create a published post;
2. verify discovery, M-Sitemap hint, and authoritative M-URL;
3. edit visible title and body content;
4. prove stale validators select `200` plus the new identity;
5. prove current validators select `304`;
6. trash and restore the post;
7. permanently delete it;
8. advance TCT's internal cache epoch;
9. keep internal epoch advancement visibly separate from W3 Total Cache
   purging; and
10. return to the exact deterministic baseline.

Run the complete public response matrix after each relevant transition.
There must be no stale cached M-Sitemap or M-URL and no site-wide purge hidden
behind a targeted-operation claim.

## Acceptance Outcomes

The first W3 Total Cache evidence lane may conclude:

**Guided**
: A versioned manual strict-exclusion recipe passes the complete controlled
  and clean-public matrices, but the TCT plugin does not configure W3 Total
  Cache automatically.

**Supported by adapter**
: Requires a separately authorized adapter using documented public APIs,
  complete adapter boundary tests, and the same evidence matrices. This plan
  does not authorize that result or implementation.

**Incompatible**
: The pinned W3 Total Cache configuration cannot preserve TCT's response
  contract or reliably exclude the complete dynamic route set.

**Unknown**
: Testing is incomplete, non-reproducible, or confounded by another delivery
  layer.

A local pass alone is controlled evidence, not a public `Verified` claim.

## Completed First Milestone

After separate user authorization, only the controlled local Docker stages A
through C were executed:

- no adapter;
- no automatic settings mutation;
- no live customer or production site;
- no CDN;
- no provider API;
- no package-version change; and
- mandatory evidence stop before choosing a public host.

The strict pretty- and plain-permalink configurations passed. Review the
linked evidence packet before considering a separately authorized clean
disposable public confirmation. No public-host test is authorized by this
checkpoint.

## Stop

The completed milestone changed only disposable local Docker resources, which
were removed. It changed no plugin source, package, live website, retained
artifact, public host, Worker, or CDN. This checkpoint does not authorize
further W3 Total Cache testing, an adapter, alpha.7, repackaging, deployment,
or a compatibility claim.
