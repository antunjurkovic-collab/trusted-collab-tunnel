<?php
if (!defined('ABSPATH')) { exit; }

function tct_handle_requests() {
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    $sitemap_path = get_option('tct_sitemap_path', '/llm-sitemap.json');
    // Manifest now defaults to JSON to avoid colliding with llms.txt human-readable guide
    $manifest_path = get_option('tct_manifest_path', '/llm-manifest.json');
    $llms_path = get_option('tct_llms_path', '/llms.txt');

    // If rewrite captured root /{endpoint}/, serve it now
    if (get_query_var('tct_llm_root')) {
        tct_output_llm_endpoint('/');
        exit;
    }

    // If rewrite captured sitemap/manifest via query vars, serve immediately
    if (get_query_var('tct_sitemap')) {
        tct_output_sitemap();
        exit;
    }
    if (get_query_var('tct_manifest')) {
        tct_output_manifest();
        exit;
    }
    if (get_query_var('tct_llms')) {
        tct_output_llms_txt();
        exit;
    }
    if (get_query_var('tct_policy')) {
        tct_output_policy_json();
        exit;
    }
    if (get_query_var('tct_stats')) {
        tct_output_stats_json();
        exit;
    }
    if (get_query_var('tct_changes')) {
        tct_output_changes_json();
        exit;
    }

    $path = parse_url(home_url(add_query_arg([])), PHP_URL_PATH); // not used
    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    // 1) Sitemap (path match fallback if rewrites not applied)
    if ($req_path === $sitemap_path) {
        tct_output_sitemap();
        exit;
    }
    // 2) Manifest (path match fallback)
    if ($req_path === $manifest_path) {
        tct_output_manifest();
        exit;
    }
    // 3) llms.txt (path match fallback)
    if ($req_path === $llms_path) {
        tct_output_llms_txt();
        exit;
    }
    // 3.5) Policy descriptor (path match fallback)
    if ($req_path === '/llm-policy.json') {
        tct_output_policy_json();
        exit;
    }
    // 4) Stats + Changes (path fallback)
    if ($req_path === '/llm-stats.json') {
        tct_output_stats_json();
        exit;
    }
    if ($req_path === '/llm-changes.json') {
        tct_output_changes_json();
        exit;
    }
    // 5) Page endpoint */{endpoint}/ including root /{endpoint}/
    $root_pattern = '~^/?' . preg_quote($endpoint, '~') . '/?$~';
    if (preg_match($root_pattern, ltrim($req_path, '/'))) {
        // Root mapping: /llm/ â†’ canonical /
        tct_output_llm_endpoint('/');
        exit;
    }

    $pattern = '~^(.+?)/' . preg_quote($endpoint, '~') . '/?$~';
    if (preg_match($pattern, $req_path, $m)) {
        $c_path = user_trailingslashit($m[1]);
        tct_output_llm_endpoint($c_path);
        exit;
    }
}

function tct_output_llm_endpoint($canonical_path) {
    $c_url = home_url($canonical_path);
    $m_url = home_url(trailingslashit($canonical_path) . trailingslashit(trim(get_option('tct_endpoint_slug', 'llm'))));

    // VALIDATION: Only serve endpoints for content pages (not archives)
    $post = null;
    $post_id = url_to_postid($c_url);

    if ($post_id) {
        // Valid content page (post/page/CPT)
        $post = get_post($post_id);
    } else {
        // Check if it's homepage
        if (untrailingslashit($c_url) === untrailingslashit(home_url('/'))) {
            $front_id = (int) get_option('page_on_front');
            if ($front_id) {
                // Static homepage
                $post = get_post($front_id);
            } else {
                // Blog list homepage - synthesize content
                $post = tct_create_homepage_pseudo_post();
            }
        }
    }

    // If no valid post found, this is an archive page or invalid URL
    if (!$post) {
        status_header(404);
        exit;
    }

    // Block specific post types (default: product) until supported
    $blocked = apply_filters('tct_block_post_types', ['product']);
    if ($post && is_array($blocked) && in_array($post->post_type, $blocked, true)) {
        status_header(404);
        exit;
    }

    // Also block WooCommerce shop page (archive-like) until explicitly supported
    if (function_exists('wc_get_page_id')) {
        $shop_id = wc_get_page_id('shop');
        if ($shop_id && $post && (int) $post->ID === (int) $shop_id) {
            status_header(404);
            exit;
        }
    }

    // Optional auth
    if (tct_auth_required() && !tct_auth_ok()) {
        status_header(401);
        header('WWW-Authenticate: Bearer realm="tct"');
        exit;
    }

    // PHASE 1.1: 304 FAST-PATH - Check cached ETag BEFORE any payload work
    // This implements the wp-dual-native pattern: check validators first, compute only if needed
    $stored_etag = get_post_meta($post->ID, '_tct_etag', true);
    if ($stored_etag) {
        $inm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        if ($inm) {
            // Normalize ETags: strip quotes, weak prefix, handle comma-separated lists
            foreach (explode(',', $inm) as $tok) {
                $t = trim($tok);
                // Strip weak prefix
                if (stripos($t, 'W/') === 0) {
                    $t = trim(substr($t, 2));
                }
                // Strip quotes
                if (strlen($t) >= 2 && $t[0] === '"' && substr($t, -1) === '"') {
                    $t = substr($t, 1, -1);
                }

                if ($t === $stored_etag) {
                    // 304 EXIT - NO PAYLOAD BUILD!
                    status_header(304);
                    header('Content-Type: application/json; charset=UTF-8; profile="tct-1"', true);
                    header('ETag: "' . $stored_etag . '"', true);
                    header('Cache-Control: max-age=0, must-revalidate, stale-while-revalidate=60, stale-if-error=86400', true);
                    header('Vary: Accept-Encoding', true);

                    if (function_exists('tct_stats_record')) {
                        tct_stats_record($m_url, 304, 0);
                    }
                    if (tct_receipts_enabled()) {
                        tct_emit_usage_receipt($stored_etag, 304, 0);
                    }
                    exit;
                }
            }
        }
    }

    // PHASE 1.3: Try cached payload first (hot path for 200 responses)
    $cached_payload = get_transient('tct_payload_' . $post->ID);
    $cache_hit = false;

    if ($cached_payload && $stored_etag) {
        // Serve from cache (hot 200 path)
        $payload = $cached_payload;
        $hash = $stored_etag;
        $cache_hit = true;
    } else {
        // Compute fresh (cold path - cache miss or first request)
        // Use unified helper: ensures M-URL ETag == sitemap etag (expert review fix)
        // This is the SINGLE SOURCE OF TRUTH for payload and hash computation
        list($payload, $hash) = tct_build_tct_payload_and_hash($post, $c_url, $m_url);

        // Cache for next time
        update_post_meta($post->ID, '_tct_etag', $hash);
        set_transient('tct_payload_' . $post->ID, $payload, WEEK_IN_SECONDS);
        $cache_hit = false;
    }

    // CRITICAL: Clear 404 flag early
    if (isset($GLOBALS['wp_query'])) {
        $GLOBALS['wp_query']->is_404 = false;
    }

    // No match - send 200 response with full headers
    status_header(200);

    // PHASE 1.4: Common headers for both HEAD and GET (with profile parameter per draft-03)
    header('Content-Type: application/json; charset=UTF-8; profile="tct-1"', true);
    header('Link: <' . esc_url_raw($c_url) . '>; rel="canonical"', false);
    header('ETag: "' . $hash . '"', true);
    // Allow CDN/shared cache revalidation while maintaining freshness
    header('Cache-Control: max-age=0, must-revalidate, stale-while-revalidate=60, stale-if-error=86400', true);
    header('Vary: Accept-Encoding', true);
    tct_emit_policy_links();

    // AI Policy Descriptor (IANA-registered rel="describedby")
    $policy_url = home_url('/llm-policy.json');
    header('Link: <' . esc_url_raw($policy_url) . '>; rel="describedby"; type="application/json"', false);

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        if (function_exists('tct_stats_record')) { tct_stats_record($m_url, 200, 0); }
        exit;
    }


    // Draft-03: send the same canonical JSON bytes used for strong ETag generation.
    $body = tct_canonical_json_encode($payload);
    $blen = strlen($body);

    // Per draft-03: Add Content-Digest header (RFC 9530) for integrity
    // Format: Content-Digest: sha-256=:base64hash:
    $body_hash_bin = hash('sha256', $body, true);  // Binary hash
    $body_hash_b64 = base64_encode($body_hash_bin);
    header('Content-Digest: sha-256=:' . $body_hash_b64 . ':', false);

    if (function_exists('tct_stats_record')) { tct_stats_record($m_url, 200, $blen); }
    $modified = $post ? get_post_modified_time('c', true, $post) : gmdate('c');
    if (function_exists('tct_record_change')) { tct_record_change($post, $c_url, $m_url, $hash, $modified); }
    if (tct_receipts_enabled()) { tct_emit_usage_receipt($hash, 200, $blen); }
    echo $body;
    exit;
}

function tct_build_full_payload($post, $c_url, $m_url, $hash) {
    $modified = $post ? get_post_modified_time('c', true, $post) : gmdate('c');
    $title = $post ? get_the_title($post) : '';
    $wc = 0;
    $content_text = '';
    if ($post) {
        $html_raw = apply_filters('the_content', $post->post_content);
        if (!is_string($html_raw) || trim($html_raw) === '') {
            $html_raw = (string) $post->post_content;
        }
        // Build authoritative content string and derive word count from it
        $content_text = tct_build_content_string($post);
        $wc = str_word_count($content_text);
    }
    // Excerpt: prefer WP excerpt cleaned; fallback to first sentence of the content text
    $excerpt = $post ? wp_strip_all_tags(get_the_excerpt($post), true) : '';
    if ($excerpt !== '') {
        // Remove the common WP token like "[&hellip;]" and unicode ellipsis
        $excerpt = preg_replace('/\[\s*&hellip;\s*\]/i', '', $excerpt);
        $excerpt = str_replace(['&hellip;', 'â€¦'], '', $excerpt);
        $excerpt = trim($excerpt);
    }
    if (($excerpt === '' || strlen($excerpt) < 10) && $content_text !== '') {
        if (preg_match('/^(.+?[\.!?])(\s|$)/u', $content_text, $m)) {
            $excerpt = trim($m[1]);
        } else {
            $excerpt = mb_substr($content_text, 0, 240);
        }
    }

    // Author info
    $author = null;
    if ($post) {
        $aid = (int) $post->post_author;
        $author = [
            'id' => $aid,
            'name' => get_the_author_meta('display_name', $aid),
            'url' => get_author_posts_url($aid),
        ];
    }

    // Featured image (url + alt)
    $featured_image = null;
    if ($post) {
        $thumb_id = get_post_thumbnail_id($post);
        if ($thumb_id) {
            $img = wp_get_attachment_image_src($thumb_id, 'full');
            $alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
            if (is_array($img) && !empty($img[0])) {
                $featured_image = [ 'url' => $img[0], 'alt' => (string) $alt, 'width' => isset($img[1]) ? (int)$img[1] : null, 'height' => isset($img[2]) ? (int)$img[2] : null ];
            }
        }
    }

    // In-body images (url, alt, caption) and headings (h2-h4)
    $body_images = [];
    $headings = [];
    if ($post && is_string($html_raw) && trim($html_raw) !== '') {
        if (class_exists('DOMDocument')) {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html_raw);
            libxml_clear_errors();
            // Images
            $imgs = $doc->getElementsByTagName('img');
            foreach ($imgs as $imgNode) {
                $src = $imgNode->getAttribute('src');
                if (!$src) continue;
                $altAttr = $imgNode->getAttribute('alt');
                $captionText = '';
                $parent = $imgNode->parentNode;
                if ($parent && strtolower($parent->nodeName) === 'figure') {
                    foreach ($parent->childNodes as $ch) {
                        if (strtolower($ch->nodeName) === 'figcaption') {
                            $captionText = trim($ch->textContent);
                            break;
                        }
                    }
                }
                $body_images[] = [ 'url' => $src, 'alt' => $altAttr, 'caption' => $captionText !== '' ? $captionText : null ];
            }
            // Headings (h2-h4), optional
            $include_headings = (int) get_option('tct_include_headings', 1) === 1;
            if ($include_headings) {
                foreach (['h2' => 2, 'h3' => 3, 'h4' => 4] as $tag => $lvl) {
                    $nodes = $doc->getElementsByTagName($tag);
                    foreach ($nodes as $n) {
                        $text = trim($n->textContent);
                        if ($text === '') continue;
                        $id = $n->getAttribute('id');
                        $headings[] = [ 'level' => $lvl, 'text' => $text, 'anchor' => ($id !== '' ? ('#' . $id) : null) ];
                    }
                }
            }
        }
    }

    // Categories (if present on the post)
    $categories = null;
    if ($post && $post->post_type !== 'homepage') {
        $cats = get_the_category($post->ID);
        if (is_array($cats) && !empty($cats)) {
            $categories = [];
            foreach ($cats as $c) {
                if (!($c instanceof WP_Term)) continue;
                $categories[] = [
                    'id' => (int)$c->term_id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'url' => get_category_link($c->term_id),
                ];
            }
        }
    }

    // Tags (if present)
    $tagsArr = null;
    if ($post && $post->post_type !== 'homepage') {
        $tags = get_the_tags($post->ID);
        if (is_array($tags) && !empty($tags)) {
            $tagsArr = [];
            foreach ($tags as $t) {
                if (!($t instanceof WP_Term)) continue;
                $tagsArr[] = [
                    'id' => (int)$t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'url' => get_tag_link($t->term_id),
                ];
            }
        }
    }

    // Published date (UTC, ISO 8601) if available
    $published = $post ? get_post_time('c', true, $post) : null;
    $slug = $post ? $post->post_name : null;

    // Per draft-03: content_media_type specifies content format
    // Default: text/plain (plain text, no HTML)
    // Sites can filter to change to text/markdown if needed
    $content_media_type = apply_filters('tct_content_media_type', 'text/plain; charset=utf-8', $post);

    $payload = [
        'profile' => 'tct-1',
        'llm_url' => $m_url,
        'canonical_url' => $c_url,
        'post_id' => $post ? intval($post->ID) : null,
        'post_type' => $post ? $post->post_type : null,
        'title' => $title,
        'content_media_type' => $content_media_type,  // Per draft-03: NEW REQUIRED FIELD
        'lastModified' => $modified,
        'published' => $published,
        'word_count' => $wc,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'author' => $author,
        'image' => $featured_image,
        'images' => !empty($body_images) ? $body_images : null,
        'headings' => !empty($headings) ? $headings : null,
        'categories' => $categories,
        'tags' => $tagsArr,
        'content' => $content_text,
    ];

    // NOTE: Per draft-03, 'hash' field is REMOVED from JSON payload
    // The ETag header is now the sole validator (no redundant hash in body)
    // Kept $hash parameter for backwards compatibility but don't include in payload
    // Allow site owners to force full content regardless of third-party filters
    $force = (int) get_option('tct_force_full_content', 1) === 1;
    if (!$force) {
        /**
         * Filter: tct_full_payload
         * Modify the final payload contents (excerpt/content/word_count) if desired.
         */
        $payload = apply_filters('tct_full_payload', $payload, $post, $c_url, $m_url);
    }
    return $payload;
}

function tct_create_homepage_pseudo_post() {
    $site_name = get_bloginfo('name');
    $site_desc = get_bloginfo('description');
    $sitemap_url = home_url('/llm-sitemap.json');

    // Build STATIC homepage content
    // REMOVED: Dynamic recent posts (get_posts() loop) to ensure stable hash
    // Per expert review: Homepage M-URL must have stable content for ETag parity
    // Dynamic "latest 5 posts" caused cache drift (sitemap cached at T1, M-URL at T2)
    $content = "{$site_name}\n\n";
    if ($site_desc) {
        $content .= "{$site_desc}\n\n";
    }

    $content .= "For complete content, visit the LLM sitemap: {$sitemap_url}";

    // Get most recent post's modified date for stable timestamps
    // This ensures homepage hash is stable until actual content changes
    $recent_post = get_posts([
        'posts_per_page' => 1,
        'post_status' => 'publish',
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',
    ]);

    if (!empty($recent_post)) {
        $latest_id = $recent_post[0];
        $modified_date = get_post_modified_time('Y-m-d H:i:s', false, $latest_id);
        $modified_date_gmt = get_post_modified_time('Y-m-d H:i:s', true, $latest_id);
    } else {
        // Fallback: Use a fixed epoch date if no posts exist
        $modified_date = '2025-01-01 00:00:00';
        $modified_date_gmt = '2025-01-01 00:00:00';
    }

    // Create pseudo-post object that behaves like a real post
    $pseudo = new stdClass();
    $pseudo->ID = 0;
    $pseudo->post_title = $site_name;
    $pseudo->post_content = $content;
    $pseudo->post_excerpt = $site_desc;
    $pseudo->post_type = 'homepage';
    $pseudo->post_status = 'publish';
    $pseudo->post_author = 0;
    // Use stable timestamps from most recent actual post (not current_time!)
    $pseudo->post_date = $modified_date;
    $pseudo->post_date_gmt = $modified_date_gmt;
    $pseudo->post_modified = $modified_date;
    $pseudo->post_modified_gmt = $modified_date_gmt;
    $pseudo->post_name = 'homepage';

    return $pseudo;
}
