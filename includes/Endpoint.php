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
        // Root mapping: /llm/ → canonical /
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

    // Lookup post by URL
    $post = null;
    $post_id = url_to_postid($c_url);
    if ($post_id) {
        $post = get_post($post_id);
    } else {
        // If front page is a static page, use it
        if (untrailingslashit($c_url) === untrailingslashit(home_url('/'))) {
            $front_id = (int) get_option('page_on_front');
            if ($front_id) {
                $post = get_post($front_id);
            }
        }
    }

    // Allow another plugin (e.g., llm-pages) to provide payload and hash
    $payload = null;
    $hash = null;
    $filtered = apply_filters('tct_build_payload', null, $post, $c_url, $m_url);
    if (is_array($filtered) && isset($filtered['payload'], $filtered['hash'])) {
        $payload = $filtered['payload'];
        $hash = $filtered['hash'];
    }

    // Compute normalized fingerprint from the actual article content so 304s match visible text
    $html = '';
    if ($post) {
        $html = apply_filters('the_content', $post->post_content);
        // Fallback if filters strip content
        if (!is_string($html) || trim($html) === '') {
            $html = (string) $post->post_content;
        }
        $html = '<h1>' . esc_html(get_the_title($post)) . '</h1>' . $html;
    }
    $normalized = tct_normalize_text($html);
    // Always compute hash from actual content for consistency with sitemap
    $computed_hash = tct_compute_fingerprint($normalized);
    // Use computed hash to ensure sitemap and endpoint always match
    $hash = $computed_hash;

    // Always build full-content details from the current post
    $full = tct_build_full_payload($post, $c_url, $m_url, $hash);

    // If another component supplied a payload, merge in content if missing or empty
    if (is_array($payload)) {
        if (!isset($payload['content']) || empty($payload['content']) || empty($payload['content']['text'])) {
            $payload['content'] = $full['content'];
        }
        if (!isset($payload['excerpt']) || empty($payload['excerpt'])) {
            $payload['excerpt'] = $full['excerpt'];
        }
        // Always provide a word_count derived from our text to ensure parity
        $payload['word_count'] = $full['word_count'];
        // Ensure required top-level fields exist
        $payload['llm_url'] = $payload['llm_url'] ?? $m_url;
        $payload['canonical_url'] = $payload['canonical_url'] ?? $c_url;
        $payload['hash'] = $hash;
    } else {
        // No external payload → use our full payload (not the minimal)
        $payload = $full;
    }

    // Optional auth
    if (tct_auth_required() && !tct_auth_ok()) {
        status_header(401);
        header('WWW-Authenticate: Bearer realm="tct"');
        exit;
    }

    // Common headers for both HEAD and GET
    header('Content-Type: application/json; charset=UTF-8', true);
    header('Link: <' . esc_url_raw($c_url) . '>; rel="canonical"', false);
    header('ETag: "' . $hash . '"', true);
    // Allow CDN/shared cache revalidation while maintaining freshness
    header('Cache-Control: max-age=0, must-revalidate, stale-while-revalidate=60, stale-if-error=86400', true);
    header('X-LiteSpeed-Cache-Control: no-cache', false);
    header('Vary: Accept-Encoding', false);
    tct_emit_policy_links();

    // Conditional GET, precedence to If-None-Match
    $inm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) : '';
    $match_inm = false;
    if ($inm) {
        foreach (explode(',', $inm) as $tok) {
            $t = trim($tok);
            if (stripos($t, 'W/') === 0) { $t = trim(substr($t, 2)); }
            if (strlen($t) >= 2 && $t[0] === '"' && substr($t, -1) === '"') { $t = substr($t, 1, -1); }
            if ($t === $hash) { $match_inm = true; break; }
        }
    }
    if ($match_inm) {
        status_header(304);
        // Stats and optional receipt on 304
        if (function_exists('tct_stats_record')) { tct_stats_record($m_url, 304, 0); }
        if (tct_receipts_enabled()) { tct_emit_usage_receipt($hash, 304, 0); }
        exit;
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        status_header(200);
        if (function_exists('tct_stats_record')) { tct_stats_record($m_url, 200, 0); }
        exit;
    }

    // Ensure 200 OK for JSON body even if WP query considered this path a 404
    if (isset($GLOBALS['wp_query'])) {
        $GLOBALS['wp_query']->is_404 = false;
    }
    status_header(200);

    $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $blen = strlen($body);
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
        $plain = wp_strip_all_tags($html_raw, true);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $plain = trim($plain);
        $wc = str_word_count($plain);
        $content_text = $plain;
    }
    // Excerpt: prefer WP excerpt cleaned; fallback to first sentence of the content text
    $excerpt = $post ? wp_strip_all_tags(get_the_excerpt($post), true) : '';
    if ($excerpt !== '') {
        // Remove the common WP token like "[&hellip;]" and unicode ellipsis
        $excerpt = preg_replace('/\[\s*&hellip;\s*\]/i', '', $excerpt);
        $excerpt = str_replace(['&hellip;', '…'], '', $excerpt);
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
    if ($post) {
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
    if ($post) {
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

    $payload = [
        'llm_url' => $m_url,
        'canonical_url' => $c_url,
        'post_id' => $post ? intval($post->ID) : null,
        'post_type' => $post ? $post->post_type : null,
        'title' => $title,
        'modified' => $modified,
        'published' => $published,
        'hash' => $hash,
        'word_count' => $wc,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'author' => $author,
        'image' => $featured_image,
        'images' => !empty($body_images) ? $body_images : null,
        'headings' => !empty($headings) ? $headings : null,
        'categories' => $categories,
        'tags' => $tagsArr,
        'content' => [ 'text' => $content_text ],
    ];
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
