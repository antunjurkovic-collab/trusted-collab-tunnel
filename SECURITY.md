# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.9.x   | :white_check_mark: |
| < 0.9   | :x:                |

## Security Considerations

### Authentication and Access Control

**API Key Mode:**
- When `tct_auth_mode` is set to `api_key`, ensure API keys are:
  - Strong (minimum 32 characters, cryptographically random)
  - Stored securely in WordPress options (not in version control)
  - Rotated regularly (recommend every 90 days)
  - Transmitted only over HTTPS

**Public Mode:**
- Default mode (`tct_auth_mode = off`) allows unrestricted access to machine endpoints
- This is intended behavior for public content
- Do not use public mode for sensitive, paywalled, or embargoed content

### HMAC Key Handling

**Usage Receipt Keys:**
- `tct_receipt_hmac_key` should be:
  - Generated using cryptographically secure random source
  - Minimum 32 bytes (256 bits)
  - Never committed to version control
  - Never logged or exposed in error messages
  - Rotated if compromised

**Key Storage:**
- Keys are stored in WordPress wp_options table
- Recommend additional encryption at rest (server-level)
- Consider using WordPress Secrets or environment variables for production

### Header Injection Risks

**Input Validation:**
- All URL parameters are sanitized using `esc_url_raw()`
- Link header values are validated before output
- Policy URLs (`tct_terms_url`, `tct_pricing_url`) should be absolute URLs only

**Mitigation:**
- Plugin validates all header values
- No user-supplied data directly inserted into headers
- Canonical URLs derived from WordPress post objects

### PII and Privacy Considerations

**Machine-Readable JSON:**
- May contain personally identifiable information (PII) if present in post content
- Review content before enabling TCT on posts containing:
  - Personal names, addresses, email addresses
  - User-generated content with PII
  - Comments or author information

**Recommendations:**
- Use `tct_build_payload` filter to redact PII from JSON output
- Exclude sensitive post types from TCT endpoints
- Review privacy policy implications

**Example PII Redaction:**
```php
add_filter('tct_build_payload', function($ret, $post, $c_url, $m_url) {
    if (!empty($ret['payload']['author'])) {
        $ret['payload']['author'] = 'Author Name Redacted';
    }
    return $ret;
}, 10, 4);
```

### Usage Receipt Data Minimization

**Receipt Contents:**
- Receipts include: contract ID, HTTP status, bytes transferred, ETag, timestamp
- HMAC signature proves authenticity
- Do not log or transmit receipts unless required for billing/analytics

**Best Practices:**
- Enable receipts only when needed (`tct_receipts_enabled = 0` by default)
- Receipts should be consumed by client, not stored long-term
- HMAC verification prevents tampering but receipts contain metadata

### Cache Poisoning

**ETag Stability:**
- ETags are derived from normalized content only
- Template, CSS, JavaScript changes do NOT affect ETag
- This is intentional (template-invariant fingerprinting)

**Risk:**
- Malicious modifications to content will change ETag and trigger re-fetch
- Standard WordPress content security applies

**Mitigation:**
- Use WordPress roles and capabilities to control post editing
- Monitor content changes via WordPress audit logs
- ETags use SHA-256 (collision-resistant)

### Vary Header and Content Negotiation

**Current Implementation:**
- `Vary: Accept` header is set on machine endpoints
- Content is JSON (fixed format), not negotiated

**Consideration:**
- If future versions support multiple formats, ensure proper Vary handling
- Intermediary caches must respect Vary header

### HEAD Request Handling

**Availability:**
- HEAD requests may not be supported by all server configurations
- Plugin handles HEAD requests identically to GET (returns headers, no body)

**Fallback:**
- Clients should gracefully fall back to GET with `If-None-Match` if HEAD fails
- Both methods return same ETag and support 304 Not Modified

### WordPress Security Best Practices

**General:**
- Keep WordPress core, plugins, and themes updated
- Use strong passwords and 2FA for admin accounts
- Run WordPress on HTTPS only
- Configure proper file permissions (755 for directories, 644 for files)

**Specific to TCT:**
- Restrict access to `Settings → TCT Settings` to administrators only
- Review generated JSON output before enabling in production
- Test with a staging site first

### Logging and Monitoring

**What to Monitor:**
- Unusual spikes in `/llm-sitemap.json` requests (potential abuse)
- High 304 Not Modified rates (expected, good)
- Low 304 rates with unchanged content (potential cache bypass)
- Unexpected API key authentication failures

**What NOT to Log:**
- API keys (even hashed values)
- HMAC secrets
- Full receipt data (contains metadata)

### Server Configuration Notes

**WAF and Cloudflare:**
- Allowlist `/llm-sitemap.json` and `/{canonical}/llm/` paths
- Do not block based on User-Agent (legitimate crawlers vary)
- Allow HEAD method (some WAFs block by default)

**LiteSpeed Cache:**
- Exclude `/llm-sitemap.json` from caching (dynamic, changes frequently)
- Exclude `/{canonical}/llm/` endpoints if using `tct_receipts_enabled`
- Allow ETags to pass through (do not strip)

**robots.txt:**
- Do NOT block `/llm*` paths if you want crawlers to discover endpoints
- Example:
```
User-agent: *
Allow: /llm-sitemap.json
Allow: /*/llm/
```

## Reporting a Vulnerability

**If you discover a security vulnerability, please:**

1. **DO NOT** open a public GitHub issue
2. **DO NOT** disclose the vulnerability publicly until patched

**Instead, contact:**
- **Email:** antunjurkovic@gmail.com
- **Subject:** "SECURITY: TCT WordPress Plugin Vulnerability"
- **Include:**
  - Description of the vulnerability
  - Steps to reproduce
  - Affected versions
  - Potential impact
  - Your name/handle (for credit, optional)

**What to Expect:**
1. **Acknowledgment** within 48 hours
2. **Initial assessment** within 5 business days
3. **Coordinated disclosure** timeline (typically 90 days)
4. **Security patch** released before public disclosure
5. **Credit** in release notes and security advisory (if desired)

**Severity Levels:**
- **Critical:** Remote code execution, authentication bypass, SQL injection
- **High:** XSS, privilege escalation, PII exposure
- **Medium:** Information disclosure, CSRF
- **Low:** Configuration issues, low-impact bugs

**Bug Bounty:**
- No formal bug bounty program at this time
- Credit and acknowledgment provided for valid reports
- May offer compensation for critical vulnerabilities (case-by-case)

## Security Updates

**How to Stay Informed:**
- Watch this repository for security releases
- Subscribe to WordPress.org plugin updates (when published)
- Follow [@antunjurkovic-collab](https://github.com/antunjurkovic-collab) on GitHub

**Security Advisories:**
- Published via GitHub Security Advisories
- Announced on WordPress.org plugin page
- Included in CHANGELOG.md

## Responsible Disclosure Timeline

1. **Day 0:** Vulnerability reported privately
2. **Day 1-2:** Acknowledgment sent to reporter
3. **Day 3-7:** Vulnerability assessed and confirmed
4. **Day 8-30:** Patch developed and tested
5. **Day 31-45:** Patch released (version bump)
6. **Day 46-90:** Public disclosure (coordinated with reporter)

**Exceptions:**
- **Critical vulnerabilities:** Expedited timeline (7-14 days)
- **Already public:** Immediate patch release
- **Actively exploited:** Emergency patch within 48 hours

## Past Security Issues

None reported as of October 19, 2025.

## Security Hardening Checklist

**Before Production:**
- [ ] HTTPS enabled (not HTTP)
- [ ] API keys strong (if using `tct_auth_mode = api_key`)
- [ ] HMAC key secure (if using `tct_receipts_enabled = 1`)
- [ ] PII reviewed and redacted from JSON payloads
- [ ] WAF/Cloudflare allowlists configured
- [ ] robots.txt permits `/llm*` paths
- [ ] Tested on staging site first
- [ ] WordPress core, plugins, themes updated
- [ ] File permissions correct (755/644)
- [ ] Monitoring configured for unusual traffic

---

**Last Updated:** October 19, 2025

For general support (non-security), please use [GitHub Issues](https://github.com/antunjurkovic-collab/trusted-collab-tunnel/issues).
