=== Trusted Collaboration Tunnel (TCT) ===
Contributors: antunjurkovikj
Tags: ai, crawlers, optimization, bandwidth, machine-readable
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reduce AI crawler bandwidth by 60-90% through efficient machine endpoints with sitemap-first verification and zero-fetch optimization.

== Description ==

Trusted Collaboration Tunnel (TCT) is a lightweight plugin that enables efficient content delivery to AI crawlers and automated agents. By implementing a standardized protocol with template-invariant fingerprinting and conditional request discipline, TCT reduces bandwidth consumption by 60-90% compared to traditional HTML crawling.

**Key Features:**

* **Machine Endpoints**: Automatic `/llm/` endpoints for each page with clean JSON content
* **Sitemap-First Discovery**: JSON sitemap at `/llm-sitemap.json` enables skip logic
* **Zero-Fetch Optimization**: AI crawlers can skip 90%+ of unchanged content
* **Template-Invariant Hashing**: SHA-256 content fingerprints stable across theme changes
* **Conditional Request Discipline**: Proper 304 Not Modified responses save bandwidth
* **Bidirectional Handshake**: Verifiable C-URL ↔ M-URL mapping via Link headers

**Measured Results:**

* 83% bandwidth savings vs HTML-only crawling
* 86% token reduction for AI processing
* 100% protocol compliance across 970+ URLs in production

**Optional Trust Extensions:**

* Policy links (terms, pricing)
* API key authentication
* Usage receipts with cryptographic signatures

**Perfect For:**

* Publishers with high AI crawler traffic
* Content sites optimizing infrastructure costs
* Developers implementing efficient content APIs
* SEO professionals managing crawler access

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/trusted-collab-tunnel/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit Settings > TCT to configure optional features
4. Test your implementation at https://llmpages.org/validator/

== Frequently Asked Questions ==

= What is the Trusted Collaboration Tunnel protocol? =

TCT is a 4-part protocol enabling efficient content delivery to AI crawlers through:
1. Bidirectional C-URL ↔ M-URL handshake
2. Template-invariant SHA-256 fingerprinting
3. 304 Not Modified conditional request discipline
4. Sitemap-first verification with zero-fetch optimization

= How much bandwidth will I save? =

Based on measurements from 970 URLs across 3 production sites: 83% bandwidth savings vs HTML-only crawling, and 86% token reduction for AI processing.

= Do I need to modify my theme? =

No. TCT works with any WordPress theme without modifications. Endpoints are automatically generated from your existing content.

= Is this compatible with my existing plugins? =

Yes. TCT integrates cleanly with caching plugins, CDNs, and security tools. It follows WordPress standards and hooks.

= How do AI crawlers discover the TCT endpoints? =

TCT adds `<link rel="alternate" type="application/json">` tags to your HTML pages, and provides a `/llm-sitemap.json` for sitemap-first discovery.

= Does this affect my regular website visitors? =

No. TCT only affects automated agents requesting JSON content. Your regular HTML pages remain unchanged.

== Screenshots ==

1. TCT Settings page - Configure optional trust extensions
2. Validator results showing 100% protocol compliance
3. Analytics showing bandwidth savings

== Changelog ==

= 1.0.0 =
* Initial release
* Machine endpoints at `{canonical}/llm/`
* JSON sitemap at `/llm-sitemap.json`
* Template-invariant SHA-256 fingerprinting
* 304 Not Modified conditional request support
* Optional trust extensions (policy links, auth, receipts)
* llms.txt manifest support

== Upgrade Notice ==

= 1.0.0 =
Initial release of Trusted Collaboration Tunnel protocol implementation.

== Patent Notice ==

This plugin implements methods covered by **US Patent Application 63/895,763** ("Method and System for a Collaborative, Resource-Efficient, and Verifiable Communication Tunnel", filed October 8, 2025, status: Patent Pending).

**For Website Owners:**
✅ FREE to install and use under GPL v2+ license
✅ No additional patent license required for your own website
✅ Full access to all features in this GPL version

**For Commercial AI Companies & Large-Scale Users:**
Commercial use at scale (>10,000 URLs/month) may require a separate patent license. Crawler operators, CDN providers, and AI companies processing TCT endpoints at scale should contact antunjurkovic@gmail.com for licensing terms.

**Trademark Notice:**
"Trusted Collaboration Tunnel" and "TCT" are pending trademark applications.

**Note**: The patent-pending technology enables 60-90% bandwidth savings through sitemap-first verification and zero-fetch optimization. This GPL implementation is provided for website owners to benefit from these savings. Large-scale commercial users who build services around this protocol should obtain appropriate licensing.

== Privacy & Data ==

TCT does not collect, store, or transmit any personal data. All content served through TCT endpoints comes directly from your WordPress database. Optional usage receipts (when enabled) contain only technical metadata (timestamp, content hash, HTTP status) and no personal information.

== Support ==

* Documentation: https://llmpages.org/docs/
* Validator Tool: https://llmpages.org/validator/
* GitHub: https://github.com/antunjurkovic-collab/trusted-collab-tunnel
* Email: antunjurkovic@gmail.com
* IETF Specification: https://llmpages.org/spec/

== Technical Specification ==

**Endpoints:**
* Machine URL: `{canonical}/llm/` (configurable via `tct_endpoint_slug`)
* Sitemap: `/llm-sitemap.json`
* Manifest: `/llms.txt`

**HTTP Headers on M-URL:**
* `Content-Type: application/json`
* `Link: <C_URL>; rel="canonical"`
* `ETag: "sha256-[hash]"`
* `Cache-Control: max-age=0, must-revalidate`
* `Vary: Accept`

**JSON Response Schema:**
```json
{
  "canonical_url": "https://example.com/post/",
  "title": "Post Title",
  "content": "Normalized content text...",
  "meta_description": "Description",
  "published": "2025-01-15T10:30:00Z",
  "modified": "2025-01-16T14:22:00Z",
  "author": "Author Name",
  "content_hash": "sha256-abc123..."
}
```

**Conditional Requests:**
* Honors `If-None-Match` with ETag comparison
* Returns `304 Not Modified` on match (zero body)
* Works for both HEAD and GET requests
* Enables 90%+ skip rate for unchanged content

== Credits ==

Developed by Antun Jurkovikj
Protocol Specification: https://llmpages.org/spec/
Patent Pending: US 63/895,763
