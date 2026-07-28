# Alpha.6 Playground Bundle

Assemble this Blueprint beside the exact alpha.6 artifact under the bundled
name `trusted-collab-tunnel-alpha6.zip`, then run:

```powershell
npx @wp-playground/cli@3.1.47 server `
    --blueprint='<bundle-directory>' `
    --blueprint-may-read-adjacent-files `
    --port=9401 `
    --workers=6
```

Validate from the repository root:

```powershell
& ./scripts/validate-live.ps1 `
    -BaseUrl 'http://127.0.0.1:9401' `
    -SitemapPath '/llm-sitemap.json' `
    -SampleMurls 3
```

This is a root-hosted runtime lane. The pure regression suite separately
characterizes `/scope:<name>/`, while the package gate exercises a real
WordPress `/subsite` installation.
