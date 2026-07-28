# Alpha.6 URL-Path-Prefix Repair Plan and Evidence

Status: authorized narrow repair; evidence gate in progress

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

- [ ] PHP unit suite passes.
- [ ] Draft-03 static audit passes.
- [ ] Syntax and diff checks pass.
- [ ] Deterministic root-hosted Playground CLI lane passes 97 checks.
- [ ] Exact packaged ZIP activates as alpha.6 without a source bind mount.
- [ ] Exact packaged root pretty-permalink lane passes 97 checks.
- [ ] Exact packaged `/subsite` pretty-permalink lane passes 97 checks.
- [ ] Post and page query-form M-URLs return certified JSON in `/subsite`.
- [ ] External report `base_url` retains `/subsite`.
- [ ] Two clean pinned builds are byte-identical.
- [ ] Package manifest pins source commit and published Draft-03 digest.
- [ ] Runtime/package diff contains no unrelated subsystem change.
- [ ] Disposable test resources are removed.

## Stop

Passing these gates authorizes only an alpha.6 experimental prerelease
candidate. It does not establish production readiness, universal host/cache
compatibility, provider support, WordPress.org publication, a cache adapter,
or a Worker deployment.
