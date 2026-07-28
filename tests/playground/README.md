# WordPress Playground Route Fixture

This fixture installs the retained alpha.5 ZIP and replaces the default
content with one deterministic post and one deterministic page. It is test
infrastructure only; it is excluded from the release package.

The Blueprint uses a bundled resource named
`trusted-collab-tunnel-alpha5.zip`. Assemble a disposable bundle from the
repository root:

```powershell
$runtime = Join-Path $env:TEMP 'tct-alpha5-playground'
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
Copy-Item -LiteralPath 'tests/playground/blueprint.json' `
    -Destination (Join-Path $runtime 'blueprint.json')
Copy-Item -LiteralPath 'dist/trusted-collab-tunnel-3.0.0-alpha.5.zip' `
    -Destination (Join-Path $runtime 'trusted-collab-tunnel-alpha5.zip')
```

Run the bundle with the pinned CLI generation used by the checkpoint:

```powershell
npx @wp-playground/cli@3.1.47 server `
    --blueprint=$runtime `
    --blueprint-may-read-adjacent-files `
    --port=9401 `
    --workers=6
```

In another PowerShell session, validate the running disposable site:

```powershell
& ./scripts/validate-live.ps1 `
    -BaseUrl 'http://127.0.0.1:9401' `
    -SitemapPath '/llm-sitemap.json' `
    -SampleMurls 3
```

This root-hosted CLI fixture does not emulate the browser service worker's
`/scope:<name>/` URL prefix. Path-prefix behavior has a separate controlled
WordPress/Apache characterization in
[`../../docs/PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md`](../../docs/PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md).
