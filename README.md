# Trusted Collaboration Tunnel (TCT) — Draft-03 Alpha WordPress Reference

**Version: 3.0.0-alpha.1** | **Target specification: draft-jurkovikj-collab-tunnel-03 internal**

This branch is a draft-03 alignment workspace for the WordPress TCT reference plugin. It is not the published draft-02 implementation and should not be described as production-grade yet.

TCT, in draft-03 wording, means **Collaboration Content Transfer**. Earlier deployments used "Trusted Collaboration Tunnel" / "Collaboration Tunnel" naming. This plugin keeps the historical plugin name while aligning the wire surface toward draft-03.

## Core Draft-03 Surface

Core TCT features in this branch:

- C-URL to M-URL mapping for selected WordPress content.
- M-URL JSON envelopes with `profile: "tct-1"`.
- Strong HTTP `ETag` values derived from deterministic canonical JSON bytes.
- M-URL response bodies emitted using the same deterministic JSON bytes used for ETag hashing.
- `Content-Digest` integrity headers on 200 M-URL responses.
- `Link: rel="canonical"` from M-URL back to C-URL.
- Root discovery Link header with `profile="tct-1"`.
- M-Sitemap at `/llm-sitemap.json` with version `2`, `profile`, `cUrl`, `mUrl`, `etag`, and `lastModified`.
- Conditional `GET` with `If-None-Match` and `304 Not Modified`.

## Non-Core Extensions

These features are deployment extensions, not core TCT conformance requirements:

- `/llm-policy.json`
- `AI-Usage-Receipt` headers
- `/llm-stats.json`
- `/llm-changes.json`
- `/llms.txt`
- admin cache/status utilities

Stats and change-feed DB writes are disabled by default in this branch through:

- `tct_stats_enabled = 0`
- `tct_changes_enabled = 0`

Receipts are disabled by default and sanitize `X-AI-Contract` to a narrow header-safe identifier grammar before emitting receipt headers.

## Draft-03 Alignment Notes

This branch intentionally differs from the draft-02 plugin surface:

- Sitemap advisory timestamp field is `lastModified`, not `modified`.
- Legacy `contentHash` and body `hash` fields are not emitted.
- Root discovery includes `profile="tct-1"`.
- Policy, receipt, stats, and changes are explicitly non-core extensions.
- Broad `/llm/` substring 404 suppression was narrowed to exact TCT route shapes.

## Current Status

This is an alpha alignment branch. Before a public draft-03 release, still validate:

- live install behavior;
- canonical JSON byte / ETag parity;
- sitemap field shape;
- 200/304 behavior;
- extension-off default behavior;
- receipt header escaping;
- large-site sitemap/index strategy.

## Install

Copy the plugin folder to `wp-content/plugins/` and activate it in WordPress admin.

Default endpoints:

- `/llm-sitemap.json`
- `/{canonical}/llm/`
- `/llm-policy.json` extension
- `/llms.txt` extension

## Security Notes

- Public mode is intended only for public content.
- If a C-URL requires authentication, protect its corresponding M-URL similarly.
- Treat policy, receipt, stats, and changes endpoints as deployment extensions.
- Do not place secrets in repository files.
- Use HTTPS for M-URLs and M-Sitemaps.
