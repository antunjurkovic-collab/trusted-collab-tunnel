# LiteSpeed Cache: 304 Not Modified Fix

## Problem

If you're using LiteSpeed Cache (LSCache) on your WordPress site, you may find that `304 Not Modified` responses are not working for M-URLs, even after updating the plugin with the fix.

**Symptoms:**
- Every request returns `200 OK` with full body
- Conditional GET (`If-None-Match`) header is ignored
- No bandwidth savings from 304 responses

**Root Cause:**
LiteSpeed Cache intercepts requests and serves cached responses **before** WordPress/PHP code runs, preventing the plugin's conditional GET logic from executing.

---

## Solution

You need to configure LiteSpeed to **bypass cache** for TCT M-URLs.

### Option 1: LiteSpeed Cache Plugin Settings (Easiest)

If using the **LiteSpeed Cache WordPress plugin**:

1. Go to **WordPress Admin > LiteSpeed Cache > Settings**
2. Click **Cache** tab
3. Find **Exclude** section
4. Add these paths to **Do Not Cache URIs**:
   ```
   /llm/
   /llm-sitemap.json
   /llm-policy.json
   /llm-manifest.json
   ```

5. **Save** and **Purge All Cache**

---

### Option 2: .htaccess Rules (Server-Level)

Add these rules to your site's `.htaccess` file (before WordPress rules):

```apache
<IfModule LiteSpeed>
  # Bypass LiteSpeed cache for Trusted Collaboration Tunnel endpoints
  RewriteEngine On

  # Don't cache M-URLs or TCT JSON endpoints
  RewriteCond %{REQUEST_URI} /llm/$ [OR]
  RewriteCond %{REQUEST_URI} /llm-sitemap\.json$ [OR]
  RewriteCond %{REQUEST_URI} /llm-policy\.json$
  RewriteRule .* - [E=Cache-Control:no-cache,E=no-cache:1]
</IfModule>
```

After adding, **restart LiteSpeed** or purge cache.

---

### Option 3: Server Configuration

If you have server access, add to LiteSpeed virtual host config:

```apache
<Location ~ "/(.*)/llm/$">
  CacheDisable on
</Location>

<Location ~ "/llm-.*\.json$">
  CacheDisable on
</Location>
```

Restart LiteSpeed:
```bash
systemctl restart lsws
```

---

## Verification

Test if 304 is now working:

```bash
# Get initial ETag
curl -I https://yoursite.com/llm/

# Copy the ETag value, then test conditional GET
curl -H 'If-None-Match: W/"sha256-..."' -I https://yoursite.com/llm/

# Should return:
# HTTP/1.1 304 Not Modified
```

---

## Why This Happens

LiteSpeed Cache is designed to serve cached responses **before** PHP executes. This is great for performance, but it prevents WordPress from:

1. Reading the `If-None-Match` header
2. Comparing it with the current ETag
3. Returning `304 Not Modified`

By excluding M-URLs from LiteSpeed cache, we allow WordPress/PHP to handle conditional GET logic while still benefiting from:
- Client-side caching (via `Cache-Control` headers)
- Shared cache caching (via `public` directive)
- 304 responses (87-90% bandwidth savings)

---

## Other Cache Plugins

Similar issues can occur with:
- **WP Super Cache**: Exclude `/llm/` paths in settings
- **W3 Total Cache**: Add to Page Cache exclusions
- **WP Rocket**: Add to "Never Cache URL(s)"
- **Cloudflare**: Ensure "Respect Existing Headers" is enabled

---

## Support

If 304 still doesn't work after trying these solutions:

1. Check server error logs
2. Verify plugin updated to latest version
3. Test with all caching disabled temporarily
4. Open issue: https://github.com/antunjurkovic-collab/trusted-collab-tunnel/issues
