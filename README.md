# Trusted Collaboration Tunnel (TCT) — WordPress Plugin (Reference)

A minimal, install-and-go plugin that exposes a deterministic machine endpoint (M_URL) for each canonical page (C_URL), with validator discipline and sitemap-first skip. Optional trust extensions add policy links, access control, and usage receipts.

## Patent Notice

This plugin implements methods covered by **US Patent Application 63/895,763** ("Method and System for a Collaborative, Resource-Efficient, and Verifiable Communication Tunnel", filed October 8, 2025, status: Patent Pending).

**For Website Owners:**
- ✅ FREE to install and use under GPL v2+ license
- ✅ No additional patent license required for your own website
- ✅ Full access to all features in this GPL version

**For Commercial AI Companies & Large-Scale Users:**
- Commercial use at scale (>10,000 URLs/month) may require a separate patent license
- Crawler operators, CDN providers, and AI companies processing TCT endpoints at scale should contact us for licensing terms
- Contact: antunjurkovic@gmail.com

**Trademark Notice:**
"Trusted Collaboration Tunnel" and "TCT" are pending trademark applications.

**Note**: The patent-pending technology enables 60-90% bandwidth savings through sitemap-first verification and zero-fetch optimization. This GPL implementation is provided for website owners to benefit from these savings. Large-scale commercial users who build services around this protocol should obtain appropriate licensing.

## Specification & Resources

This plugin implements the Collaboration Tunnel Protocol (TCT):
- 📄 **Full Specification:** https://github.com/antunjurkovic-collab/collab-tunnel-spec
- 📦 **Python Client Library:** https://pypi.org/project/collab-tunnel/
- 🔍 **Protocol Validator:** https://llmpages.org/validator/

### Measured Results
Based on 970 URLs across 3 production sites:
- **83% bandwidth savings** (103 KB → 17.7 KB average)
- **86% token reduction** (13,900 → 1,960 tokens)
- **90%+ skip rate** for unchanged content
- **100% protocol compliance**

## Protocol Endpoints

- Endpoint: `{canonical}/llm/` (configurable via `tct_endpoint_slug`)
- Sitemap: `/llm-sitemap.json`
- Manifest: `/llms.txt`
- Headers on M_URL: `Link: <C_URL>; rel="canonical"`, `ETag: "sha256-…"`, `Cache-Control: max-age=0, must-revalidate, stale-while-revalidate=60, stale-if-error=86400`, `Vary: Accept-Encoding`
- Conditional GET: honors `If-None-Match` and returns `304` (no body) on match; works for HEAD and GET

### M-URL JSON Response Format

```json
{
  "profile": "tct-1",
  "llm_url": "https://example.com/post/llm/",
  "canonical_url": "https://example.com/post/",
  "hash": "sha256-e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "title": "Post Title",
  "content": {"text": "Article content..."},
  "modified": "2025-10-23T18:00:00Z"
}
```

**Profile Field:** The `"profile": "tct-1"` field enables protocol versioning. Future versions (e.g., `tct-2`) can introduce new fields while maintaining backward compatibility.

### Sitemap JSON Format

```json
{
  "version": 1,
  "profile": "tct-1",
  "items": [
    {
      "cUrl": "https://example.com/post/",
      "mUrl": "https://example.com/post/llm/",
      "modified": "2025-10-23T18:00:00Z",
      "contentHash": "sha256-e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
    }
  ]
}
```

## Optional Trust Extensions (off by default)
- Policy links: `Link: <…>; rel="terms-of-service"`, `Link: <…>; rel="payment"` (set options `tct_terms_url`, `tct_pricing_url`) — Note: for backward compatibility, the plugin also accepts legacy `rel="terms"` and `rel="pricing"` from origin
- Auth: `tct_auth_mode = off|api_key`, with `tct_api_keys = ["key1","key2"]`
- Usage receipts: `AI-Usage-Receipt: contract=…; status=200|304; bytes=…; etag="…"; ts=…; sig=base64(hmac)` when `tct_receipts_enabled = 1` and `tct_receipt_hmac_key` set

## AI Policy Descriptor (AIPREF Alignment)

**NEW:** Machine-readable policy at `/llm-policy.json` aligned with IETF AIPREF working group priorities.

### Policy Descriptor JSON

Every M-URL now includes a Link header pointing to the policy descriptor:

```http
Link: </llm-policy.json>; rel="describedby"; type="application/json"
```

The policy descriptor provides structured information about AI usage preferences:

```json
{
  "profile": "tct-policy-1",
  "version": 1,
  "effective": "2025-10-25T10:00:00Z",
  "updated": "2025-10-25T10:00:00Z",
  "policy_urls": {
    "terms_of_service": "https://example.com/terms",
    "payment_info": "https://example.com/pricing",
    "contact": "https://example.com/contact"
  },
  "purposes": {
    "allow_ai_input": true,
    "allow_ai_train": false,
    "allow_search_indexing": true
  },
  "requirements": {
    "attribution_required": true,
    "link_back_required": false,
    "notice_required": true
  },
  "rate_hints": {
    "max_requests_per_second": null,
    "max_requests_per_day": 10000,
    "note": "Advisory limits, honor system"
  },
  "extensions": {}
}
```

### Configuration

Configure via **WordPress Admin → Settings → TCT → AI Policy Descriptor**:

- **Contact URL**: Where AI systems can reach you
- **Permitted AI Purposes**:
  - AI Input (assistants, chatbots) — Default: enabled
  - AI Training (model fine-tuning) — Default: disabled
  - Search Indexing (Perplexity, Bing, Google) — Default: enabled
- **AI Usage Requirements**:
  - Attribution Required — Default: enabled
  - Link-Back Required — Default: disabled
  - Notice Required — Default: enabled
- **Advisory Rate Limits**:
  - Max requests/second (0 = no limit)
  - Max requests/day (default: 10,000)

### llms.txt Integration

The policy descriptor is automatically referenced in `/llms.txt`:

```
## Machine-Readable Policy
- https://example.com/llm-policy.json - Policy descriptor (JSON)

The policy descriptor provides structured information about:
- Allowed purposes (AI input, training, search indexing)
- Requirements (attribution, notice)
- Advisory rate limits

## AI Preferences
# Aligned with emerging standards (Cloudflare Content Signals)
- AI-Input: allowed
- AI-Train: prohibited
- Search: allowed
```

**Note**: If using a static llms.txt file, regenerate it after changing policy settings to ensure the AI Preferences section reflects your current configuration. The virtual endpoint (recommended) always stays in sync automatically.

### AIPREF Alignment

This implementation follows IETF AIPREF working group priorities:

- ✅ Uses IANA-registered `rel="describedby"` (not custom relations)
- ✅ Vocabulary-agnostic design ready for AIPREF finalization (August 2025)
- ✅ Extensions object for future standards mapping
- ✅ Explicit versioning with `tct-policy-1` profile
- ✅ Compatible with Cloudflare Content Signals (ai-input, ai-train, search)

**Note**: Policy defaults favor restrictive settings (AI Input: yes, Training: no) aligned with publisher interests.

## Integration with llm-pages (optional)
If another plugin can provide the JSON payload and content hash, hook:

```php
add_filter('tct_build_payload', function($ret, $post, $c_url, $m_url){
  // Compute $payload and $hash using your normalization
  return [ 'payload' => $payload, 'hash' => $hash ];
}, 10, 4);
```

## Security Considerations

**Before deploying to production, review [SECURITY.md](SECURITY.md) for complete security guidance.**

### Key Security Points

**Authentication:**
- Default mode (`tct_auth_mode = off`) allows public access to machine endpoints
- Use API key mode only for sensitive content: `tct_auth_mode = api_key`
- API keys must be 32+ characters, cryptographically random, stored securely

**HMAC Keys:**
- Usage receipts require `tct_receipt_hmac_key` (32+ bytes minimum)
- Never commit keys to version control
- Rotate keys regularly (every 90 days recommended)

**PII and Privacy:**
- Machine JSON may contain personally identifiable information from post content
- Review content before enabling TCT on posts with PII
- Use `tct_build_payload` filter to redact sensitive data

**Server Configuration:**
- Enable HTTPS only (required for API keys and receipts)
- Allowlist `/llm-sitemap.json` and `/*/llm/` in WAF/Cloudflare
- Allow HEAD method (some WAFs block by default)
- Configure robots.txt to permit `/llm*` paths

**Responsible Disclosure:**
- Security vulnerabilities: Email antunjurkovic@gmail.com with subject "SECURITY: TCT WordPress Plugin"
- Do NOT open public issues for security bugs
- See [SECURITY.md](SECURITY.md) for disclosure timeline

## Licensing

**Code License:** GPL v2+ (see [LICENSE](LICENSE))
**Patent Rights:** See [PATENTS.md](PATENTS.md) for patent licensing information

The GPL v2+ license covers the source code. Patent rights are a separate matter detailed in PATENTS.md.

## Notes
- This directory is a reference implementation; drop it into `wp-content/plugins/` to run on a WP site.
- For production, consider adding rewrite rules on activation; this reference uses `template_redirect` path interception.

## Future-Ready Push Discovery (Optional)

TCT is fully effective on its own (the 4-part method: handshake, template-invariant ETag, validator discipline with `304`, and sitemap-first skipping). When faster discovery is desirable, TCT can be complemented by optional push mechanisms without changing the core contract:

- IndexNow (search engine change hints)
  - Purpose: notify participating engines immediately when URLs change.
  - How it complements TCT: engines learn about changes sooner, then revalidate `{canonical}/llm/` using `HEAD` + `If-None-Match` to get `304` when unchanged.
  - When to use: time-sensitive content, high-volume publishing, or when reducing stale windows is important.

- WebSub (real-time hub notifications)
  - Purpose: publish change notifications to a hub; subscribers receive near–real-time pings.
  - How it complements TCT: partners subscribe to a change topic (e.g., `/llm-changes.json`) and fetch only on change, leveraging `ETag` parity and `304` discipline.
  - When to use: you have identifiable subscribers/partners who want instant updates.

Notes
- These are accelerators, not requirements. Delivery stays the same: JSON at `{canonical}/llm/`, `Link: rel="canonical"`, `ETag` from normalized content, `Cache-Control: must-revalidate`, and strict `304` on `If-None-Match`.
- Monetization/accounting is unchanged: policy/pricing links, optional API-key access, and signed `AI-Usage-Receipt` headers continue to apply.
- Implementation is deferred in this reference; sites can add them later without modifying TCT’s core behavior.
