<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function() {
    add_options_page(
        'Trusted Collaboration Tunnel',
        'TCT',
        'manage_options',
        'tct-settings',
        'tct_render_settings_page'
    );
});

function tct_update_option_bool($key) {
    $val = isset($_POST[$key]) ? 1 : 0;
    update_option($key, $val);
}

function tct_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tct_action'])) {
        check_admin_referer('tct_settings');
        $action = sanitize_text_field($_POST['tct_action']);

        if ($action === 'save') {
            tct_update_option_bool('tct_llms_virtual_enabled');
            tct_update_option_bool('tct_llms_include_samples');
            tct_update_option_bool('tct_llms_include_xml_sitemap');
            tct_update_option_bool('tct_llms_include_policies');

            $sample_count = max(1, intval($_POST['tct_llms_sample_count'] ?? 20));
            update_option('tct_llms_sample_count', $sample_count);

            $post_types_raw = sanitize_text_field($_POST['tct_llms_post_types'] ?? 'post,page');
            $post_types = array_filter(array_map('trim', explode(',', $post_types_raw)));
            update_option('tct_llms_post_types', $post_types);

            // Terms/Pricing URLs
            update_option('tct_terms_url', esc_url_raw($_POST['tct_terms_url'] ?? ''));
            update_option('tct_pricing_url', esc_url_raw($_POST['tct_pricing_url'] ?? ''));

            // Usage receipts
            tct_update_option_bool('tct_receipts_enabled');
            $hmac = sanitize_text_field($_POST['tct_receipt_hmac_key'] ?? '');
            update_option('tct_receipt_hmac_key', $hmac);

            $notice = 'Settings saved.';
        } elseif ($action === 'generate') {
            $ok = tct_llms_generate_static(false);
            $notice = $ok ? 'Static llms.txt generated.' : 'Could not generate. A different owner exists or write failed.';
        } elseif ($action === 'overwrite') {
            $ok = tct_llms_generate_static(true);
            $notice = $ok ? 'Static llms.txt overwritten (backup created if not ours).' : 'Overwrite failed (permissions?).';
        } elseif ($action === 'remove') {
            $ok = tct_llms_remove_static();
            $notice = $ok ? 'Static llms.txt removed.' : 'Remove failed (not owned by TCT or permissions).';
        }
    }

    $owned = tct_llms_owned_by_us();
    $path = tct_llms_physical_path();
    $exists = file_exists($path);
    $mod = $exists ? @filemtime($path) : 0;

    $virtual_enabled = (int) get_option('tct_llms_virtual_enabled', 1) === 1;
    $include_samples = (int) get_option('tct_llms_include_samples', 1) === 1;
    $include_xml = (int) get_option('tct_llms_include_xml_sitemap', 1) === 1;
    $include_policies = (int) get_option('tct_llms_include_policies', 1) === 1;
    $sample_count = intval(get_option('tct_llms_sample_count', 20));
    $post_types = get_option('tct_llms_post_types', array('post','page'));
    if (!is_array($post_types)) { $post_types = array_filter(array_map('trim', explode(',', (string)$post_types))); }
    $terms = get_option('tct_terms_url', '');
    $pricing = get_option('tct_pricing_url', '');
    $receipts_enabled = (int) get_option('tct_receipts_enabled', 0) === 1;
    $receipt_key = (string) get_option('tct_receipt_hmac_key', '');

    ?>
    <div class="wrap">
      <h1>Trusted Collaboration Tunnel</h1>
      <?php if ($notice): ?><div class="updated"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

      <h2>llms.txt</h2>
      <p><strong>Status:</strong>
        Path: <code><?php echo esc_html($path); ?></code>
        <?php if ($exists): ?> | File exists<?php if ($mod) echo ' (modified ' . esc_html(date('Y-m-d H:i', $mod)) . ')'; ?><?php else: ?> | Not found<?php endif; ?>
        | Owner: <?php echo $owned ? '<span style="color:green">TCT</span>' : '<span style="color:#a00">Other/Unknown</span>'; ?>
      </p>

      <form method="post">
        <?php wp_nonce_field('tct_settings'); ?>
        <input type="hidden" name="tct_action" value="save">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Enable virtual /llms.txt</th>
            <td>
              <label><input type="checkbox" name="tct_llms_virtual_enabled" <?php checked($virtual_enabled); ?>> Serve virtual guide at /llms.txt</label>
            </td>
          </tr>
          <tr>
            <th scope="row">Include sample endpoints</th>
            <td>
              <label><input type="checkbox" name="tct_llms_include_samples" <?php checked($include_samples); ?>> List recent posts/pages</label>
              &nbsp;Count: <input type="number" min="1" max="100" name="tct_llms_sample_count" value="<?php echo esc_attr($sample_count); ?>" style="width:80px">
              &nbsp;Post types: <input type="text" name="tct_llms_post_types" value="<?php echo esc_attr(implode(',', $post_types)); ?>" style="width:220px">
            </td>
          </tr>
          <tr>
            <th scope="row">Include XML sitemap link</th>
            <td><label><input type="checkbox" name="tct_llms_include_xml_sitemap" <?php checked($include_xml); ?>> Add /sitemap_index.xml link</label></td>
          </tr>
          <tr>
            <th scope="row">Policies (terms/pricing)</th>
            <td>
              <label><input type="checkbox" name="tct_llms_include_policies" <?php checked($include_policies); ?>> Include policy links</label><br>
              Terms/Policy URL: <input type="url" name="tct_terms_url" value="<?php echo esc_attr($terms); ?>" size="50"><br>
              Pricing URL: <input type="url" name="tct_pricing_url" value="<?php echo esc_attr($pricing); ?>" size="50">
            </td>
          </tr>
          <tr>
            <th scope="row">Usage receipts (optional)</th>
            <td>
              <label><input type="checkbox" name="tct_receipts_enabled" <?php checked($receipts_enabled); ?>> Emit AI-Usage-Receipt on 200/304</label><br>
              HMAC key: <input type="text" name="tct_receipt_hmac_key" value="<?php echo esc_attr($receipt_key); ?>" size="50"><br>
              <small>Clients send <code>X-AI-Contract: YOUR-ID</code>. Receipt header includes contract, status, bytes, ETag, timestamp, and base64 HMAC signature.</small>
            </td>
          </tr>
        </table>

        <p class="submit"><button type="submit" class="button button-primary">Save Settings</button></p>
      </form>

      <h3>Static File Controls</h3>
      <form method="post" style="display:inline-block;margin-right:10px">
        <?php wp_nonce_field('tct_settings'); ?>
        <input type="hidden" name="tct_action" value="generate">
        <button type="submit" class="button">Generate static llms.txt</button>
      </form>
      <form method="post" style="display:inline-block;margin-right:10px">
        <?php wp_nonce_field('tct_settings'); ?>
        <input type="hidden" name="tct_action" value="overwrite">
        <button type="submit" class="button button-secondary">Overwrite &amp; take over (backup if not ours)</button>
      </form>
      <form method="post" style="display:inline-block">
        <?php wp_nonce_field('tct_settings'); ?>
        <input type="hidden" name="tct_action" value="remove">
        <button type="submit" class="button" onclick="return confirm('Remove static llms.txt (only if owned by TCT)?')">Remove static llms.txt</button>
      </form>

      <h3 style="margin-top:24px">Reference Implementation</h3>
      <p>See a complete, production TCT implementation with real measurements and validation:</p>
      <p><a href="https://llmpages.org/" target="_blank" class="button button-primary">Visit llmpages.org &rarr;</a></p>
      <ul style="margin-top:12px;">
        <li><strong>Real Data:</strong> 83% bandwidth, 86% tokens, 90%+ skip rate (970+ URLs)</li>
        <li><strong>Live Validator:</strong> <a href="https://llmpages.org/validator/" target="_blank">llmpages.org/validator</a></li>
        <li><strong>Documentation:</strong> Integration guides, FAQ, developer docs</li>
        <li><strong>Production Sites:</strong> wellbeing-support.com, galaxybilliard.club (10/10 scores)</li>
      </ul>
    </div>
    <?php
}

/**
 * DEPRECATED: This function is no longer used.
 * Showcase pages are now referenced from llmpages.org instead of auto-generated.
 * Kept for backward compatibility only.
 */
function tct_create_showcase_pages() {
    $created = false;
    $msgs = array();
    // Helper to create page if slug not taken
    $mk = function($title, $slug, $content) use (&$msgs, &$created) {
        $existing = get_page_by_path($slug);
        if ($existing && $existing instanceof WP_Post) {
            $msgs[] = '/' . $slug . ' exists (ID ' . $existing->ID . ')';
            return $existing->ID;
        }
        $pid = wp_insert_post(array(
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
        if (is_wp_error($pid) || !$pid) {
            $msgs[] = 'Failed to create /' . $slug;
            return 0;
        }
        $created = true;
        $msgs[] = '/' . $slug;
        return $pid;
    };

    $domain = parse_url(home_url('/'), PHP_URL_HOST);
    $sitemap = home_url('/llm-sitemap.json');
    $llms = home_url('/llms.txt');

    // 1) Partner Hub
    $hub = '';
    $hub .= "# Trusted Collaboration Tunnel (TCT) — Partner Hub\n\n";
    $hub .= "- Domain: https://{$domain}\n";
    $hub .= "- LLM Sitemap: {$sitemap}\n";
    $hub .= "- llms.txt: {$llms}\n";
    $hub .= "- Live Stats: " . home_url('/llm-stats.json') . "\n";
    $hub .= "- Change Feed: " . home_url('/llm-changes.json') . "\n\n";
    $hub .= "## Validate Now\n";
    $hub .= "Use the validator to check canonical, ETag parity, 304 precedence, and JSON hygiene.\n\n";
    $hub .= "[llm_validator title=\"Validate Our Endpoints\" description=\"Validate canonical↔machine pairs or the sitemap.\" show_sitemap_tab=\"true\" default_sitemap=\"{$sitemap}\"]\n\n";
    $hub .= "## Sample Endpoints\n";
    $hub .= "- Example: https://{$domain}/ai-policy/ → https://{$domain}/ai-policy/llm/\n";
    $hub .= "- Example: https://{$domain}/ai-pricing/ → https://{$domain}/ai-pricing/llm/\n\n";
    $hub .= "## Method Overview\n";
    $hub .= "1) Sitemap-first discovery\n2) Deterministic JSON (1:1 content)\n3) ETag parity + 304 discipline\n4) Optional receipts (HMAC)\n\n";

    $hub .= "## Savings Estimator\n";
    $hub .= "[tct_savings_estimator default_requests=\"20000\" html_kb=\"250\" json_kb=\"40\" hit_304=\"60\"]\n\n";
    $mk('AI Partner Hub', 'partners', $hub);

    // 2) For AI Crawlers
    $crawlers = '';
    $crawlers .= "# For AI Crawlers\n\n";
    $crawlers .= "- Use `{$sitemap}` for discovery.\n- Per-article JSON: `{canonical}/llm/`.\n- Headers: Link rel=canonical; ETag; Cache-Control; Vary.\n- Conditional GET: send If-None-Match (quoted ETag) for 304.\n\n";
    $crawlers .= "### Quick Checks\n";
    $crawlers .= "- `curl -sI https://{$domain}/ai-policy/llm/`\n- `curl -s -o /dev/null -w '%{http_code}\\n' -H 'If-None-Match: \"sha256-…\"' https://{$domain}/ai-policy/llm/`\n\n";
    $mk('For AI Crawlers', 'for-ai-crawlers', $crawlers);

    // 3) Developer Docs
    $dev = '';
    $dev .= "# Developer Notes\n\n";
    $dev .= "JSON shape includes: title, modified, published, hash, word_count, slug, excerpt, author, image, images[], headings[], categories[], tags[], content.text.\n\n";
    $dev .= "Headers: Link rel=canonical; ETag(sha256-…); Cache-Control: private, max-age=0, must-revalidate; Vary: Accept.\n\n";
    $dev .= "Receipts (optional): `AI-Usage-Receipt: contract=…; status=…; bytes=…; etag=\"…\"; ts=…; sig=…` (HMAC-SHA256).\n\n";
    $mk('Developer Docs', 'developers', $dev);

    // 4) For Publishers
    $pub = '';
    $pub .= "# For Publishers & Website Owners\n\n";
    $pub .= "## Why TCT Benefits Your Website\n\n";
    $pub .= "AI crawlers (OpenAI, Perplexity, Google Gemini, Anthropic) are fetching your content multiple times per day. Most of those requests are wasteful:\n\n";
    $pub .= "- They fetch full HTML even when content hasn't changed\n";
    $pub .= "- They download all CSS, JavaScript, and images\n";
    $pub .= "- They parse redundant boilerplate (navigation, sidebar, footer)\n";
    $pub .= "- You pay egress costs for every byte\n\n";
    $pub .= "**TCT solves this** by giving AI crawlers a more efficient way to access your content:\n\n";
    $pub .= "### Benefits\n\n";
    $pub .= "✓ **Reduce bandwidth costs by 60-90%** - JSON is 67% smaller than HTML, and unchanged content is skipped entirely\n\n";
    $pub .= "✓ **Lower server load** - 90%+ of requests result in zero origin hits (sitemap pre-check)\n\n";
    $pub .= "✓ **Better AI visibility** - Crawlers prefer efficient sources, improving your content's discoverability\n\n";
    $pub .= "✓ **No SEO impact** - Your human visitors see the exact same HTML; only AI endpoints change\n\n";
    $pub .= "✓ **Usage transparency** - Optional receipts let you track exactly how AI systems use your content\n\n";
    $pub .= "### Real-World Savings\n\n";
    $pub .= "For a typical blog with **20,000 AI crawler requests/month**:\n\n";
    $pub .= "- **Before TCT:** ~5 GB egress/month\n";
    $pub .= "- **After TCT:** ~500 MB egress/month (90% reduction)\n";
    $pub .= "- **Cost savings:** \\$20-100/month (depending on host)\n\n";
    $pub .= "For high-traffic sites (news, e-commerce), savings can reach **\\$1,000-10,000+/month**.\n\n";
    $pub .= "### Zero Maintenance\n\n";
    $pub .= "Once installed, TCT requires no ongoing work:\n\n";
    $pub .= "- Automatically creates JSON endpoints for all posts/pages\n";
    $pub .= "- Updates sitemap when you publish/edit content\n";
    $pub .= "- Works with any WordPress theme or page builder\n";
    $pub .= "- Compatible with WooCommerce, custom post types, etc.\n\n";
    $pub .= "### Security & Privacy\n\n";
    $pub .= "- **No data exposure** - Only publicly visible content is included in JSON endpoints\n";
    $pub .= "- **Optional authentication** - Require API keys for crawler access\n";
    $pub .= "- **Access control** - Paywalled/members-only content remains protected\n\n";
    $pub .= "### Get Started\n\n";
    $pub .= "1. Plugin is already active (you're reading this page!)\n";
    $pub .= "2. Configure settings at: WordPress Admin → Settings → TCT\n";
    $pub .= "3. Test your endpoints: {$sitemap}\n";
    $pub .= "4. Share with AI crawler partners or submit to search engines\n\n";
    $mk('For Publishers', 'for-publishers', $pub);

    // 5) Integration Guide
    $guide = '';
    $guide .= "# Integration Guide\n\n";
    $guide .= "## Step 1: Verify Installation\n\n";
    $guide .= "Check that your site is serving TCT endpoints:\n\n";
    $guide .= "```\n";
    $guide .= "curl -I https://{$domain}/llm-sitemap.json\n";
    $guide .= "# Should return: HTTP/1.1 200 OK\n";
    $guide .= "```\n\n";
    $guide .= "## Step 2: Configure Settings\n\n";
    $guide .= "Go to **WordPress Admin → Settings → TCT**:\n\n";
    $guide .= "- ✓ Enable virtual /llms.txt\n";
    $guide .= "- ✓ Include sample endpoints\n";
    $guide .= "- Set post types (default: `post,page`)\n";
    $guide .= "- Add policy URLs (terms/pricing) - optional\n";
    $guide .= "- Enable usage receipts - optional\n\n";
    $guide .= "## Step 3: Test Endpoints\n\n";
    $guide .= "Pick any published post and test its JSON endpoint:\n\n";
    $guide .= "```\n";
    $guide .= "# Replace with your actual post URL\n";
    $guide .= "curl https://{$domain}/your-post-slug/llm/\n";
    $guide .= "```\n\n";
    $guide .= "You should see JSON output with `title`, `content`, `author`, etc.\n\n";
    $guide .= "## Step 4: Validate Compliance\n\n";
    $guide .= "Use the validator on the Partners page to check:\n\n";
    $guide .= "- Canonical ↔ Machine handshake\n";
    $guide .= "- ETag generation and parity\n";
    $guide .= "- 304 Not Modified discipline\n";
    $guide .= "- Content-Type headers\n\n";
    $guide .= "→ [Go to Validator](/partners)\n\n";
    $guide .= "## Step 5: Optional - Cloudflare Worker\n\n";
    $guide .= "If you use Cloudflare, deploy the TCT Worker for additional features:\n\n";
    $guide .= "- Edge-layer authentication\n";
    $guide .= "- Usage receipt signing at edge\n";
    $guide .= "- Policy link injection\n";
    $guide .= "- Header optimization\n\n";
    $guide .= "**Setup:**\n\n";
    $guide .= "1. Copy Worker code from: [GitHub repo link]\n";
    $guide .= "2. Deploy to Cloudflare Workers\n";
    $guide .= "3. Add route: `*{$domain}/llm*`\n";
    $guide .= "4. Configure environment variables\n\n";
    $guide .= "## Step 6: Share with AI Crawlers\n\n";
    $guide .= "Notify AI platforms that your site supports TCT:\n\n";
    $guide .= "- **OpenAI:** Submit via SearchGPT partner form\n";
    $guide .= "- **Perplexity:** Email partnerships@perplexity.ai\n";
    $guide .= "- **Anthropic:** Submit via support form\n";
    $guide .= "- **Google:** Submit to Search Console\n\n";
    $guide .= "Include your sitemap URL: `{$sitemap}`\n\n";
    $guide .= "## Troubleshooting\n\n";
    $guide .= "**Q: Endpoints return 404**\n";
    $guide .= "A: Go to Settings → Permalinks and click \"Save Changes\" to flush rewrite rules.\n\n";
    $guide .= "**Q: ETag keeps changing even when content hasn't**\n";
    $guide .= "A: Check for dynamic elements (date/time, random content) in your theme template.\n\n";
    $guide .= "**Q: Validator shows ETag mismatch**\n";
    $guide .= "A: Clear all caches (WordPress, CDN, browser) and re-validate.\n\n";
    $mk('Integration Guide', 'integration-guide', $guide);

    // 6) FAQ
    $faq = '';
    $faq .= "# Frequently Asked Questions\n\n";
    $faq .= "## General\n\n";
    $faq .= "### What is TCT?\n\n";
    $faq .= "Trusted Collaboration Tunnel is a protocol that gives AI crawlers efficient access to your content while reducing your bandwidth costs by 60-90%.\n\n";
    $faq .= "### Do I need to change anything for human visitors?\n\n";
    $faq .= "No. TCT only affects AI crawlers. Your regular visitors see the exact same HTML as before.\n\n";
    $faq .= "### Will this hurt my SEO?\n\n";
    $faq .= "No. Google, Bing, and other search engines continue to crawl your HTML as normal. TCT endpoints are purely for AI systems (ChatGPT, Perplexity, etc.).\n\n";
    $faq .= "### Is this compatible with my theme/plugins?\n\n";
    $faq .= "Yes. TCT works with any WordPress theme, page builder (Elementor, Divi, etc.), and most plugins including WooCommerce, bbPress, and BuddyPress.\n\n";
    $faq .= "## Technical\n\n";
    $faq .= "### What data is included in JSON endpoints?\n\n";
    $faq .= "Only the main content that's already publicly visible on your page: title, body text, author, publication date, categories, tags, and featured image. No private data, no user information, no backend content.\n\n";
    $faq .= "### How does the sitemap work?\n\n";
    $faq .= "TCT generates `/llm-sitemap.json` with a list of all your posts/pages, their machine URLs, modification dates, and content hashes. AI crawlers check this first to see what's changed since their last visit.\n\n";
    $faq .= "### What's an ETag?\n\n";
    $faq .= "An ETag is a fingerprint of your content. When content doesn't change, the ETag stays the same, allowing crawlers to skip downloading the full page (304 Not Modified response).\n\n";
    $faq .= "### Does this work with caching plugins?\n\n";
    $faq .= "Yes. TCT is compatible with WP Super Cache, W3 Total Cache, LiteSpeed Cache, and other caching plugins. The JSON endpoints are cacheable by default.\n\n";
    $faq .= "## Security & Privacy\n\n";
    $faq .= "### Can I restrict who accesses the endpoints?\n\n";
    $faq .= "Yes. Enable API key authentication in Settings → TCT. Only clients with valid keys can access JSON endpoints.\n\n";
    $faq .= "### What about paywalled content?\n\n";
    $faq .= "TCT respects your existing access controls. If a post requires authentication to view, the JSON endpoint returns 401 Unauthorized.\n\n";
    $faq .= "### Can I see who's accessing my content?\n\n";
    $faq .= "Yes. Enable usage receipts in Settings → TCT. Crawlers that support receipts will send a contract ID, and you'll get signed records of each access.\n\n";
    $faq .= "### Is this GDPR compliant?\n\n";
    $faq .= "Yes. TCT only serves publicly available content (same as your HTML pages). It doesn't collect personal data, set cookies, or track users.\n\n";
    $faq .= "## Costs & Performance\n\n";
    $faq .= "### How much bandwidth will I save?\n\n";
    $faq .= "Typical savings: 60-90% reduction in AI crawler egress. JSON payloads are ~67% smaller than HTML, and sitemap-first skip logic eliminates 90%+ of unchanged fetches.\n\n";
    $faq .= "### Will this speed up my site?\n\n";
    $faq .= "For human visitors: no change. For AI crawlers: significantly faster (smaller payloads, fewer requests).\n\n";
    $faq .= "### Does TCT cost anything?\n\n";
    $faq .= "The plugin is free and open-source (MIT license). No subscriptions, no usage fees.\n\n";
    $faq .= "## Support\n\n";
    $faq .= "### Where can I get help?\n\n";
    $faq .= "- Documentation: See [Integration Guide](/integration-guide)\n";
    $faq .= "- Validation: Use the [Partners page](/partners) validator\n";
    $faq .= "- Issues: Report on GitHub (link in plugin settings)\n\n";
    $faq .= "### Can I contribute?\n\n";
    $faq .= "Yes! TCT is open-source. Submit pull requests, report bugs, or suggest features on GitHub.\n\n";
    $mk('FAQ', 'faq', $faq);

    return array($created, $msgs);
}
