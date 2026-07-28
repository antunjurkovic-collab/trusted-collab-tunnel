# Alpha.6 URL-Path-Prefix Repair Plan and Evidence

Status: repair complete; alpha.6 experimental package candidate

Opened: 2026-07-28

## Purpose

Produce one separately versioned `3.0.0-alpha.6` experimental package that
repairs alpha.5's pretty non-home M-URL routing failure when the configured
WordPress public home URL contains a path prefix.

The independently reproduced alpha.5 finding and attribution are recorded in
[`PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md`](PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md).

## Authorized Changes

1. Remove the configured public WordPress home-path prefix exactly once from
   a raw request path before C-URL reconstruction.
2. Apply the same relative-path boundary to protocol-route recognition before
   WordPress 404 handling.
3. Reject malformed, over-limit, or outside-home paths without attempting a
   partial reconstruction.
4. Preserve a configured base path in external-validator report metadata.
5. Add root, subdirectory, Playground-style scope, pretty-route, query-route,
   and byte-boundary regression evidence.
6. Align version/package metadata and create a reproducible alpha.6 ZIP.

## Immutable Protocol Boundary

Alpha.6 must not change:

- Draft-03 profile URIs or schemas;
- M-URL or M-Sitemap canonical JSON;
- RFC 8785 serialization;
- ETag, Content-Digest, or Content-Length derivation;
- conditional-request or identity-negotiation semantics;
- exposure, authorization, receipt, or telemetry policy;
- `tct_v03_alpha2` cache namespace or epoch semantics;
- cache adapters, purges, provider configuration, or Worker behavior; or
- the published Internet-Draft.

## Path Contract

The adapter accepts at most 8,192 path bytes. It rejects an empty path,
control characters, an invalid configured home path, or a request outside the
configured home-path segment boundary.

Examples:

| Configured home path | Raw request path | Relative result |
| --- | --- | --- |
| `/` | `/post/llm/` | `/post/llm/` |
| `/subsite/` | `/subsite/post/llm/` | `/post/llm/` |
| `/scope:fixture/` | `/scope:fixture/post/llm/` | `/post/llm/` |
| `/subsite/` | `/subsite-other/post/llm/` | fail closed |

The configured prefix is removed only at an exact path-segment boundary.
`home_url()` remains the sole authority that adds it during reconstructed
C-URL creation.

## Release Gates

- [x] PHP unit suite passes.
- [x] Draft-03 static audit passes.
- [x] Syntax and diff checks pass.
- [x] Deterministic root-hosted Playground CLI lane passes 97 checks.
- [x] Exact packaged ZIP activates as alpha.6 without a source bind mount.
- [x] Exact packaged root pretty-permalink lane passes 97 checks.
- [x] Exact packaged `/subsite` pretty-permalink lane passes 97 checks.
- [x] Post and page query-form M-URLs return certified JSON in `/subsite`.
- [x] External report `base_url` retains `/subsite`.
- [x] Two clean pinned builds are byte-identical.
- [x] Package manifest pins source commit and published Draft-03 digest.
- [x] Runtime/package diff contains no unrelated subsystem change.
- [x] Disposable test resources are removed.

## Source and Package Identity

- package source commit:
  `817f1bc26d557d91b0f28990f418fa1c2a4186ac`;
- artifact:
  `dist/trusted-collab-tunnel-3.0.0-alpha.6.zip`;
- artifact bytes: `99,356`;
- artifact SHA-256:
  `387659f8996e3051dd43d6caacdbaa0e92d416e02c9d43a89d70c97a81b3bf42`;
- package entries, including the generated manifest: `64`;
- two independently assembled builds: byte-identical; and
- published Draft-03 source SHA-256:
  `d106c6b10fad897b434834d74682bf093e66a0a5aea1dbf116691e301ef692fd`.

The deterministic builder read committed Git blobs rather than the working
tree. The generated package manifest identifies the source commit, plugin
version, draft revision, draft source digest, and each packaged file's bytes
and SHA-256.

## Source Validation

The final source checkpoint reproduced:

```text
PHPUnit: 124 tests, 3435 assertions, pass
Draft-03 static audit: 45 checks, 0 failed
PHP syntax: includes/Endpoint.php and trusted-collab-tunnel.php, pass
Git diff check: pass
```

The three added test methods cover root, `/subsite`, a
`/scope:deterministic-fixture` prefix, exact prefix-only mapping, adjacent but
nonmatching segments, outside-home paths, controls, noncanonical leading
slashes, invalid home URLs, and the exact 8,192-byte boundary.

## Exact-Package WordPress/Apache Matrix

The generated ZIP was installed and activated without a source bind mount in
two disposable WordPress `7.0.2`, PHP `8.3`, Apache, and MariaDB `10.11`
installations sharing one test server:

| Public base | Requests | Samples | Checks | Failed | Bytes | Seconds |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `http://127.0.0.1:8094` | 16 | 3 | 97 | 0 | 74,741 | 2.958 |
| `http://127.0.0.1:8094/subsite` | 16 | 3 | 97 | 0 | 75,310 | 4.542 |

Both installations reported plugin version `3.0.0-alpha.6`. Every selected
pretty M-URL retained canonical JSON, the exact strong body-derived ETag,
Content-Digest, Content-Length, identity-only selection, profile/canonical
links, sitemap hint parity, conditional `304`, HEAD metadata, and bounded
method/identity rejection.

The `/subsite` report serialized:

```json
{"schema":"tct-external-validator-report-v1","ok":true,"base_url":"http://127.0.0.1:8094/subsite","checks":97,"failed":0}
```

Two non-home `/subsite` C-URLs were additionally requested with
`?tct_m_url=1`. Each returned `200 application/json`, the exact Draft-03
M-URL profile, and a `canonical_url` equal to the selected sitemap C-URL.

## Exact-Package Playground CLI Lane

The alpha.6 Blueprint installed the same ZIP into
`@wp-playground/cli` `3.1.47`, PHP `8.3`, and the CLI's current WordPress
package. With the supported six-worker pool and a workspace-local isolated
temporary filesystem, the external validator reproduced:

```text
schema: tct-external-validator-report-v1
requests: 16
sampled_murls: 3
checks: 97
failed: 0
response_bytes: 73732
elapsed_seconds: 43.749
exit: 0
```

Two earlier disposable attempts using the system temporary filesystem
exhausted the validator's fixed 60-second total budget after 10 and 14
requests. All completed semantic checks passed and the incomplete checks
reported `total_time`. A one-worker diagnostic attempt exhibited the
file-lock behavior warned about by the CLI and was discarded. The final
supported run changed neither package nor validator limits and completed
within budget.

## Change Isolation

The runtime correction is limited to:

- request-path relativization in `includes/Endpoint.php`;
- reuse of that boundary during pre-404 route recognition;
- external-validator base-path reporting; and
- version/diagnostic text alignment.

No file under `src/Draft03/` changed. The cache namespace remains
`tct_v03_alpha2`. No cache/provider adapter, purge, Worker, draft,
canonicalizer, schema, identity algorithm, authorization policy, or
experimental receipt/telemetry behavior changed.

All disposable Docker containers, network, volume, Playground processes,
runtime directories, and test listeners were removed after validation.

## Stop

Passing these gates authorizes only an alpha.6 experimental prerelease
candidate. It does not establish production readiness, universal host/cache
compatibility, provider support, WordPress.org publication, a cache adapter,
or a Worker deployment.
