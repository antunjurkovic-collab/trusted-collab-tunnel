<?php
if (!defined('ABSPATH')) { exit; }

function tct_output_sitemap() {
    // PHASE 2.1: 304 FAST-PATH - Check cached sitemap + strong ETag first (zero-fetch path)
    $cached_json = get_transient('tct_sitemap_json_v3');
    $cached_etag = get_transient('tct_sitemap_etag_v3');

    if ($cached_json && $cached_etag) {
        // Check If-None-Match for 304
        $inm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        if ($inm) {
            // Normalize ETags: strip quotes, weak prefix, handle comma-separated lists
            foreach (explode(',', $inm) as $tok) {
                $t = trim($tok);
                if (stripos($t, 'W/') === 0) { $t = trim(substr($t, 2)); }
                if (strlen($t) >= 2 && $t[0] === '"' && substr($t, -1) === '"') {
                    $t = substr($t, 1, -1);
                }

                if ($t === $cached_etag) {
                    // 304 - NO QUERY OR BUILD WORK!
                    status_header(304);
                    header('Content-Type: application/json; charset=UTF-8; profile="tct-1"', true);
                    header('ETag: "' . $cached_etag . '"', true);
                    header('Cache-Control: public, max-age=3600, must-revalidate', true);
                    header('Vary: Accept-Encoding', true);

                    // Support HEAD
                    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
                        exit;
                    }
                    exit;
                }
            }
        }

        // Cache hit but no 304 - serve cached JSON (200 from cache)
        status_header(200);
        header('Content-Type: application/json; charset=UTF-8; profile="tct-1"', true);
        header('ETag: "' . $cached_etag . '"', true);

        // Add Content-Digest (RFC 9530)
        $body_hash_bin = hash('sha256', $cached_json, true);
        $body_hash_b64 = base64_encode($body_hash_bin);
        header('Content-Digest: sha-256=:' . $body_hash_b64 . ':', false);

        header('Cache-Control: public, max-age=3600, must-revalidate', true);
        header('Vary: Accept-Encoding', true);

        // Support HEAD
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }

        echo $cached_json;
        exit;
    }

    // Cache miss - build fresh

    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    // Collect recent posts/pages (publish). Sites can filter this query.
    // Default behavior: exclude WooCommerce 'product' CPT and the Shop page.
    $public = get_post_types(['public' => true], 'names');
    $default_excluded = apply_filters('tct_sitemap_excluded_post_types', ['product']);
    if (!is_array($default_excluded)) { $default_excluded = ['product']; }
    $post_types = array_values(array_diff($public, $default_excluded));

    $post_not_in = [];
    if (function_exists('wc_get_page_id')) {
        $shop_id = (int) wc_get_page_id('shop');
        if ($shop_id > 0) { $post_not_in[] = $shop_id; }
    }
    $posts_page_id = (int) get_option('page_for_posts');
    if ($posts_page_id > 0) {
        // The posts page behaves as an archive, not as a singular content M-URL.
        $post_not_in[] = $posts_page_id;
    }

    // PHASE 2.3: Optimize query with performance flags
    $qargs = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'post__not_in' => $post_not_in,
        'posts_per_page' => -1,  // Include ALL posts
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',  // Already present

        // Performance flags (from wp-dual-native pattern)
        'no_found_rows' => true,              // Skip SQL_CALC_FOUND_ROWS
        'update_post_term_cache' => false,    // Skip category/tag cache warming
        'update_post_meta_cache' => false,    // Skip meta cache warming (we fetch individually)
    ];
    $qargs = apply_filters('tct_sitemap_query_args', $qargs);
    $ids = get_posts($qargs);

    $entries = [];

    // Add homepage as first item
    $home_url = trailingslashit(home_url('/'));
    $home_m_url = $home_url . trailingslashit($endpoint);

    // Determine homepage type and hash
    $front_id = (int) get_option('page_on_front');
    if ($front_id) {
        // Static homepage - use actual page content
        $home_post = get_post($front_id);
        if ($home_post) {
            // Use unified helper: ensures sitemap etag == M-URL ETag (expert review fix)
            list(, $etag) = tct_build_tct_payload_and_hash($home_post, $home_url, $home_m_url);
            $modified = get_post_modified_time('c', true, $home_post);

            $entries[] = [
                'cUrl' => $home_url,
                'mUrl' => $home_m_url,
                'lastModified' => $modified,
                'etag' => $etag,
            ];
        }
    } else {
        // Blog list homepage - use synthetic content
        if (function_exists('tct_create_homepage_pseudo_post')) {
            $pseudo = tct_create_homepage_pseudo_post();
            // Use unified helper: ensures sitemap etag == M-URL ETag (expert review fix)
            list(, $etag) = tct_build_tct_payload_and_hash($pseudo, $home_url, $home_m_url);
            $modified = gmdate('c', strtotime($pseudo->post_modified_gmt));

            $entries[] = [
                'cUrl' => $home_url,
                'mUrl' => $home_m_url,
                'lastModified' => $modified,
                'etag' => $etag,
            ];
        }
    }

    // PHASE 2.2: Read ETags from post meta (NOT regenerate)
    // This turns O(N Ã— expensive) into O(N Ã— cheap meta lookup)
    foreach ((array)$ids as $pid) {
        $c_url = get_permalink($pid);
        if (!$c_url) { continue; }
        $m_url = trailingslashit($c_url) . trailingslashit($endpoint);

        // Read cached ETag from post meta (precomputed on save_post)
        $etag = get_post_meta($pid, '_tct_etag', true);

        // If missing (rare - first request or cache cleared), compute once
        if (!$etag) {
            $post = get_post($pid);
            list(, $etag) = tct_build_tct_payload_and_hash(
                $post,
                trailingslashit($c_url),
                trailingslashit($m_url)
            );
            // Note: tct_build_tct_payload_and_hash now stores in post meta (Phase 1.2)
        }

        $entries[] = [
            'cUrl' => trailingslashit($c_url),
            'mUrl' => trailingslashit($m_url),
            'lastModified' => get_post_modified_time('c', true, $pid),
            'etag' => $etag,
        ];
    }
    // Per draft-03: version 2 for updated spec
    $out = [
        'version' => 2,
        'profile' => 'tct-1',
        'items' => $entries,
    ];

    // Generate deterministic JSON bytes for the sitemap response.
    $json = tct_canonical_json_encode($out);

    // PHASE 2.4: Compute strong ETag from final JSON bytes
    $etag = 'sha256-' . hash('sha256', $json);

    // Cache both JSON and ETag for fast revalidation
    set_transient('tct_sitemap_json_v3', $json, 3600);
    set_transient('tct_sitemap_etag_v3', $etag, 3600);

    // Send 200 response with strong validator
    status_header(200);
    header('Content-Type: application/json; charset=UTF-8; profile="tct-1"', true);
    header('ETag: "' . $etag . '"', true);

    // Content-Digest for integrity (RFC 9530)
    $body_hash_bin = hash('sha256', $json, true);
    $body_hash_b64 = base64_encode($body_hash_bin);
    header('Content-Digest: sha-256=:' . $body_hash_b64 . ':', false);

    header('Cache-Control: public, max-age=3600, must-revalidate', true);
    header('Vary: Accept-Encoding', true);

    // Support HEAD
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    echo $json;
}

