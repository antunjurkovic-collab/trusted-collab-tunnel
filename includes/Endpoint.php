<?php
if (!defined('ABSPATH')) { exit; }

if (!defined('TCT_MAX_REQUEST_PATH_BYTES')) {
    define('TCT_MAX_REQUEST_PATH_BYTES', 8192);
}

/**
 * Return a request path relative to the configured public WordPress home URL.
 *
 * REQUEST_URI includes the installation prefix for WordPress sites served
 * below paths such as /subsite and Playground browser scopes. home_url()
 * already supplies that prefix, so route reconstruction must remove it once.
 * A path outside the configured home boundary fails closed.
 */
function tct_request_path_relative_to_home($request_path, $configured_home_url = null) {
    if (
        !is_string($request_path)
        || $request_path === ''
        || $request_path[0] !== '/'
        || str_starts_with($request_path, '//')
        || strlen($request_path) > TCT_MAX_REQUEST_PATH_BYTES
        || preg_match('/[\x00-\x1F\x7F]/', $request_path) === 1
    ) {
        return null;
    }

    $home_url_value = is_string($configured_home_url)
        ? $configured_home_url
        : home_url('/');
    $home_parts = parse_url($home_url_value);
    if (
        !is_array($home_parts)
        || !isset($home_parts['scheme'], $home_parts['host'])
        || !in_array(strtolower((string) $home_parts['scheme']), ['http', 'https'], true)
        || (string) $home_parts['host'] === ''
        || isset($home_parts['user'])
        || isset($home_parts['pass'])
        || isset($home_parts['query'])
        || isset($home_parts['fragment'])
    ) {
        return null;
    }

    $home_path = isset($home_parts['path']) ? (string) $home_parts['path'] : '/';
    if (
        $home_path === ''
        || $home_path[0] !== '/'
        || str_starts_with($home_path, '//')
        || strlen($home_path) > TCT_MAX_REQUEST_PATH_BYTES
        || preg_match('/[\x00-\x1F\x7F]/', $home_path) === 1
    ) {
        return null;
    }

    $home_path = '/' . trim($home_path, '/');
    if ($home_path === '/') {
        return $request_path;
    }

    if ($request_path === $home_path) {
        return '/';
    }

    $home_prefix = $home_path . '/';
    if (!str_starts_with($request_path, $home_prefix)) {
        return null;
    }

    $relative = substr($request_path, strlen($home_path));
    return is_string($relative) && $relative !== '' ? $relative : '/';
}

function tct_handle_requests() {
    $endpoint = sanitize_title((string) get_option('tct_endpoint_slug', 'llm'));
    if ($endpoint === '') {
        $endpoint = 'llm';
    }
    $sitemap_path = (string) parse_url(
        (string) get_option('tct_sitemap_path', '/llm-sitemap.json'),
        PHP_URL_PATH
    );
    // Manifest now defaults to JSON to avoid colliding with llms.txt human-readable guide
    $manifest_path = (string) parse_url(
        (string) get_option('tct_manifest_path', '/llm-manifest.json'),
        PHP_URL_PATH
    );
    $llms_path = (string) parse_url(
        (string) get_option('tct_llms_path', '/llms.txt'),
        PHP_URL_PATH
    );

    // Plain WordPress permalinks use query-style C-URLs. Their M-URLs retain
    // that query and add the dedicated public routing flag.
    if ((string) get_query_var('tct_m_url') === '1') {
        $post_id = (int) get_queried_object_id();
        $post = $post_id > 0 ? get_post($post_id) : null;
        tct_output_llm_endpoint('', $post);
        exit;
    }

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

    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($req_path)) {
        return;
    }
    $req_path = tct_request_path_relative_to_home($req_path);
    if (!is_string($req_path)) {
        return;
    }

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
        // Root suffix maps to the homepage C-URL.
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

/**
 * Recover the original If-None-Match field value after WordPress applies
 * legacy magic quotes to request globals during bootstrap.
 */
function tct_if_none_match_request_value() {
    if (!isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        return '';
    }

    return (string) wp_unslash((string) $_SERVER['HTTP_IF_NONE_MATCH']);
}

function tct_output_llm_endpoint($canonical_path, $resolved_post = null) {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!\TCT\Draft03\ConditionalRequest::isSafeReadMethod($method)) {
        status_header(405);
        header('Allow: GET, HEAD', true);
        exit;
    }

    if (!\TCT\Draft03\AcceptEncoding::identityIsAllowed($_SERVER['HTTP_ACCEPT_ENCODING'] ?? null)) {
        status_header(406);
        header('Vary: Accept-Encoding', true);
        exit;
    }

    $post = $resolved_post;
    if (func_num_args() < 2) {
        $requested_c_url = home_url($canonical_path);
        $post_id = url_to_postid($requested_c_url);

        if ($post_id) {
            $post = get_post($post_id);
        } elseif (untrailingslashit($requested_c_url) === untrailingslashit(home_url('/'))) {
            $front_id = (int) get_option('page_on_front');
            $post = $front_id ? get_post($front_id) : tct_create_homepage_pseudo_post();
        }
    }

    if (!$post || !tct_post_is_exposable($post)) {
        status_header(404);
        exit;
    }

    if (tct_auth_required() && !tct_auth_ok()) {
        status_header(401);
        header('WWW-Authenticate: Bearer realm="tct"');
        header('Cache-Control: private, no-store', true);
        exit;
    }

    $c_url = tct_c_url_for_post($post);
    if (!is_string($c_url) || $c_url === '') {
        status_header(404);
        exit;
    }
    $m_url = tct_m_url_for_c_url($c_url);

    $resource_id = isset($post->ID) ? (int) $post->ID : 0;
    $identity = tct_get_cached_identity($resource_id);

    if ($identity === null) {
        try {
            [, $identity] = tct_build_murl_identity($post, $c_url, $m_url);
            tct_set_cached_identity($resource_id, $identity);
        } catch (\Throwable $exception) {
            error_log('TCT Draft-03 M-URL build failed: ' . $exception->getMessage());
            status_header(500);
            header('Cache-Control: no-store', true);
            exit;
        }
    }

    if (isset($GLOBALS['wp_query'])) {
        $GLOBALS['wp_query']->is_404 = false;
    }

    $if_none_match = tct_if_none_match_request_value();
    if (
        $if_none_match !== ''
        && \TCT\Draft03\ConditionalRequest::ifNoneMatchMatches($if_none_match, $identity->etag)
    ) {
        status_header(304);
        tct_send_murl_identity_headers($identity, $c_url, false);
        if (function_exists('tct_stats_record')) {
            tct_stats_record($m_url, 304, 0);
        }
        if (tct_receipts_enabled()) {
            tct_emit_usage_receipt($identity->catalogEtag(), 304, 0);
        }
        exit;
    }

    status_header(200);
    tct_send_murl_identity_headers($identity, $c_url, true);

    if ($method === 'HEAD') {
        if (function_exists('tct_stats_record')) {
            tct_stats_record($m_url, 200, 0);
        }
        exit;
    }

    $body_length = strlen($identity->body);
    if (function_exists('tct_stats_record')) {
        tct_stats_record($m_url, 200, $body_length);
    }
    if (tct_receipts_enabled()) {
        tct_emit_usage_receipt($identity->catalogEtag(), 200, $body_length);
    }

    echo $identity->body;
    exit;
}

function tct_send_murl_identity_headers($identity, $c_url, $include_content_headers) {
    if (!($identity instanceof \TCT\Draft03\IdentityRepresentation)) {
        throw new InvalidArgumentException('Expected a certified identity representation.');
    }

    if (function_exists('ini_set')) {
        @ini_set('zlib.output_compression', '0');
    }

    header('ETag: ' . $identity->etag, true);
    $cache_control = (tct_auth_required() || tct_receipts_enabled())
        ? 'private, no-store, no-transform'
        : 'public, max-age=0, must-revalidate, stale-while-revalidate=60, stale-if-error=86400, no-transform';
    header('Cache-Control: ' . $cache_control, true);
    header('Vary: Accept-Encoding', true);
    header('Link: <' . esc_url_raw($c_url) . '>; rel="canonical"', false);
    header(
        'Link: <' . \TCT\Draft03\Protocol::M_URL_PROFILE . '>; rel="profile"',
        false
    );
    tct_emit_policy_links();

    $policy_url = home_url('/llm-policy.json');
    header('Link: <' . esc_url_raw($policy_url) . '>; rel="describedby"; type="application/json"', false);

    if ($include_content_headers) {
        header('Content-Type: ' . \TCT\Draft03\Protocol::CONTENT_TYPE, true);
        header('Content-Digest: ' . $identity->contentDigest, true);
        header('Content-Length: ' . strlen($identity->body), true);
    }
}

function tct_build_full_payload($post, $c_url, $m_url, $hash) {
    unset($hash);
    $post_id = $post && isset($post->ID) ? (int) $post->ID : 0;
    $is_pseudo = $post && $post_id === 0 && ($post->post_type ?? '') === 'homepage';
    $modified = $is_pseudo
        ? gmdate('c', strtotime((string) $post->post_modified_gmt . ' UTC'))
        : ($post ? get_post_modified_time('c', true, $post) : gmdate('c'));
    $title = $is_pseudo
        ? (string) ($post->post_title ?? '')
        : ($post ? get_the_title($post) : '');
    $wc = 0;
    $content_text = '';
    if ($post) {
        // Saved source is deterministic; request-context rendering is not.
        $html_raw = (string) $post->post_content;
        $content_text = tct_build_content_string($post);
        $wc = str_word_count($content_text);
    }
    // Excerpt: prefer WP excerpt cleaned; fallback to first sentence of the content text
    $excerpt_source = $is_pseudo
        ? (string) ($post->post_excerpt ?? '')
        : ($post ? get_the_excerpt($post) : '');
    $excerpt = wp_strip_all_tags($excerpt_source, true);
    if ($excerpt !== '') {
        // Remove the common WP token like "[&hellip;]" and unicode ellipsis
        $excerpt = preg_replace('/\[\s*&hellip;\s*\]/i', '', $excerpt);
        $excerpt = str_replace(['&hellip;', "\u{2026}"], '', $excerpt);
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
    if ($post && !$is_pseudo) {
        $aid = (int) $post->post_author;
        $author = [
            'id' => $aid,
            'name' => get_the_author_meta('display_name', $aid),
            'url' => get_author_posts_url($aid),
        ];
    }

    // Featured image (url + alt)
    $featured_image = null;
    if ($post && !$is_pseudo) {
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
            $previous_error_mode = libxml_use_internal_errors(true);
            try {
                $loaded = $doc->loadHTML(
                    '<?xml encoding="UTF-8">' . $html_raw,
                    LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR
                );
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous_error_mode);
            }
            if (!$loaded) {
                throw new \TCT\Draft03\SchemaException(
                    'Saved HTML could not be parsed for deterministic extensions.'
                );
            }
            // Images
            $imgs = $doc->getElementsByTagName('img');
            foreach ($imgs as $imgNode) {
                if (count($body_images) >= \TCT\Draft03\Protocol::MAX_EXTRACTED_ITEMS) {
                    throw new \TCT\Draft03\ResourceLimitException(
                        'In-body image count exceeds the internal Draft-03 limit.'
                    );
                }
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
            if ($include_headings && class_exists('DOMXPath')) {
                $xpath = new DOMXPath($doc);
                foreach ($xpath->query('//h2 | //h3 | //h4') as $n) {
                    if (count($headings) >= \TCT\Draft03\Protocol::MAX_EXTRACTED_ITEMS) {
                        throw new \TCT\Draft03\ResourceLimitException(
                            'Heading count exceeds the internal Draft-03 limit.'
                        );
                    }
                    $text = trim($n->textContent);
                    if ($text === '') continue;
                    $id = $n->getAttribute('id');
                    $headings[] = [
                        'level' => (int) substr(strtolower($n->nodeName), 1),
                        'text' => $text,
                        'anchor' => ($id !== '' ? ('#' . $id) : null),
                    ];
                }
            }
        }
    }

    // Categories (if present on the post)
    $categories = null;
    if ($post && $post->post_type !== 'homepage') {
        $cats = get_the_category($post->ID);
        if (is_array($cats) && !empty($cats)) {
            usort($cats, static fn($left, $right) => $left->term_id <=> $right->term_id);
            if (count($cats) > \TCT\Draft03\Protocol::MAX_EXTRACTED_ITEMS) {
                throw new \TCT\Draft03\ResourceLimitException(
                    'Category count exceeds the internal Draft-03 limit.'
                );
            }
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
            usort($tags, static fn($left, $right) => $left->term_id <=> $right->term_id);
            if (count($tags) > \TCT\Draft03\Protocol::MAX_EXTRACTED_ITEMS) {
                throw new \TCT\Draft03\ResourceLimitException(
                    'Tag count exceeds the internal Draft-03 limit.'
                );
            }
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
    $published = $is_pseudo
        ? gmdate('c', strtotime((string) $post->post_date_gmt . ' UTC'))
        : ($post ? get_post_time('c', true, $post) : null);
    $slug = $post ? $post->post_name : null;

    // Per draft-03: content_media_type specifies content format
    // Default: text/plain (plain text, no HTML)
    // Sites can filter to change to text/markdown if needed
    $content_media_type = apply_filters('tct_content_media_type', 'text/plain; charset=utf-8', $post);

    $payload = [
        'profile' => \TCT\Draft03\Protocol::M_URL_PROFILE,
        'llm_url' => $m_url,
        'canonical_url' => $c_url,
        'post_id' => $post ? $post_id : null,
        'post_type' => $post ? $post->post_type : null,
        'title' => $title,
        'content_media_type' => $content_media_type,
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
    $sitemap_url = home_url((string) get_option('tct_sitemap_path', '/llm-sitemap.json'));

    // Build STATIC homepage content
    // REMOVED: Dynamic recent posts (get_posts() loop) to ensure stable hash
    // Per expert review: Homepage M-URL must have stable content for ETag parity
    // Dynamic "latest 5 posts" caused cache drift (sitemap cached at T1, M-URL at T2)
    $content = '';
    if ($site_desc) {
        $content .= "{$site_desc}\n\n";
    }

    $content .= "For complete content, visit the TCT M-Sitemap: {$sitemap_url}";

    // Get most recent post's modified date for stable timestamps
    // This ensures homepage hash is stable until actual content changes
    $recent_post = get_posts([
        'posts_per_page' => 1,
        'post_status' => 'publish',
        'post_type' => ['post', 'page'],
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
