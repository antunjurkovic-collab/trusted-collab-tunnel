<?php
if (!defined('ABSPATH')) { exit; }

function tct_output_sitemap() {
    header('Content-Type: application/json; charset=UTF-8', true);

    // Cache-Control: Allow CDN caching but with revalidation
    // Matches internal cache duration (default: 3600s = 1 hour)
    $cache_duration = (int) get_option('tct_sitemap_cache_duration', 3600);
    header('Cache-Control: max-age=' . $cache_duration . ', must-revalidate, stale-while-revalidate=60', true);

    // Phase 0: Check cache first (expert-approved pattern)
    // v3: Updated to use unified tct_build_tct_payload_and_hash helper (ensures parity)
    $cache_key = 'tct_sitemap_cache_v3';
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        echo $cached;
        return;
    }

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

    $qargs = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'post__not_in' => $post_not_in,
        'posts_per_page' => -1,  // Include ALL posts
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',
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
                'modified' => $modified,
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
                'modified' => $modified,
                'etag' => $etag,
            ];
        }
    }

    // Add all other posts/pages
    foreach ((array)$ids as $pid) {
        $c_url = get_permalink($pid);
        if (!$c_url) { continue; }
        $m_url = trailingslashit($c_url) . trailingslashit($endpoint);

        // Use unified helper: ensures sitemap etag == M-URL ETag (expert review fix)
        // This applies the same canonicalization/hashing pipeline as the M-URL endpoint
        $post = get_post($pid);
        list(, $etag) = tct_build_tct_payload_and_hash(
            $post,
            trailingslashit($c_url),
            trailingslashit($m_url)
        );

        $entries[] = [
            'cUrl' => trailingslashit($c_url),
            'mUrl' => trailingslashit($m_url),
            'modified' => get_post_modified_time('c', true, $post),
            'etag' => $etag,
        ];
    }
    $out = [
        'version' => 1,
        'profile' => 'tct-1',
        'items' => $entries,
    ];

    // Generate JSON with error handling
    $json = wp_json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        status_header(500);
        return;
    }

    // Cache for configurable duration (default: 1 hour)
    $cache_duration = (int) get_option('tct_sitemap_cache_duration', 3600);
    set_transient($cache_key, $json, $cache_duration);

    echo $json;
}

