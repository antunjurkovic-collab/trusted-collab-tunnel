# Changelog

All notable changes to the Trusted Collaboration Tunnel WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-16

### Added
- **Core LLM Endpoint Delivery** - Deterministic JSON endpoints at `{canonical}/llm/` pattern
- **JSON Sitemap** - `/llm-sitemap.json` with sitemap-first discovery (cUrl, mUrl, modified, contentHash)
- **Template-Invariant SHA256 Hashing** - Content fingerprinting immune to theme/template changes
- **ETag & 304 Discipline** - Weak ETag generation with proper `If-None-Match` conditional GET support
- **llms.txt Manifest** - Human-readable guide at `/llms.txt` (virtual or static)
- **JSON Manifest** - Machine-readable capabilities declaration at `/llm-manifest.json`
- **Optional API Key Authentication** - Bearer token or X-API-Key header support
- **HMAC-SHA256 Usage Receipts** - Signed `AI-Usage-Receipt` headers on 200/304 responses
- **Policy Link Injection** - HTTP Link headers for terms/pricing URLs
- **Admin Settings UI** - WordPress admin panel for configuration
- **Request Statistics** - Tracking for hits, bytes served, 200/304 split, bandwidth savings
- **Change Feed** - `/llm-changes.json` showing last 100 content modifications
- **Savings Calculator Shortcode** - `[tct_savings_estimator]` for interactive bandwidth/cost estimates
- **Showcase Page Generator** - Auto-create partner hub, crawler docs, publisher pages, integration guide, FAQ
- **HTML Alternate Links** - Automatic injection of `<link rel="alternate" type="application/json">` in page `<head>`
- **Full Content Extraction** - Post title, body text, author, images, headings (h2-h4), categories, tags, excerpts
- **LiteSpeed Compatibility** - Headers and rewrites optimized for LiteSpeed hosting
- **Custom Post Type Support** - Works with WooCommerce, bbPress, custom post types

### Technical Details
- **WordPress Compatibility:** 5.0+ tested
- **PHP Requirements:** 7.4+ (uses SHA256, HMAC, DOMDocument)
- **No Database Changes:** Zero schema migrations required
- **Rewrite Rules:** Automatic registration for `/llm/*`, `/llm-sitemap.json`, `/llms.txt`, `/llm-stats.json`, `/llm-changes.json`
- **Caching-Friendly:** Proper `Cache-Control`, `Vary`, `ETag` headers for optimal caching
- **Security:** Input sanitization, nonce validation, capability checks, optional API key auth

### Validated
- 100% compliance on llmpages.org (10-URL pilot, October 2025)
- 100% compliance on wellbeing-support.com
- Bandwidth savings: 67% (document-only), 99.6% (full-page with assets)
- Skip rate: 90%+ on unchanged content (sitemap-first + 304 discipline)

### Known Limitations
- Stats tracking capped at 500 M-URLs and 1000 body length entries to prevent database bloat
- Change feed limited to last 100 modifications
- No built-in rate limiting (recommend using Cloudflare or similar CDN)
- Requires permalink structure with trailing slashes for reliable routing

## [Unreleased]

### Planned for Future Releases
- Automated tests (unit + integration)
- WordPress.org plugin directory submission
- Internationalization (i18n) support
- WP-CLI commands for bulk validation
- Admin dashboard widgets for real-time stats
- Optional JSON-LD enrichment in M-URL responses
- Support for non-WordPress platforms (static site generators, etc.)

---

## Version Numbering

- **Major version (X.0.0):** Breaking changes, significant new features, protocol changes
- **Minor version (1.X.0):** New features, backward-compatible improvements
- **Patch version (1.0.X):** Bug fixes, security patches, documentation updates

---

**Note:** This is the initial public release. Previous versions (0.1.0-0.9.x) were internal development iterations not publicly distributed.
