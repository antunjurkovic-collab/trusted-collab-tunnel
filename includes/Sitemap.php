<?php
if (!defined('ABSPATH')) { exit; }

function tct_output_sitemap() {
    header('Content-Type: application/json; charset=UTF-8', true);

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
        'posts_per_page' => 200,
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',
    ];
    $qargs = apply_filters('tct_sitemap_query_args', $qargs);
    $ids = get_posts($qargs);

    $items = [];

    // Add homepage as first item
    $home_url = trailingslashit(home_url('/'));
    $home_m_url = $home_url . trailingslashit($endpoint);

    // Determine homepage type and hash
    $front_id = (int) get_option('page_on_front');
    if ($front_id) {
        // Static homepage - use actual page content
        $home_post = get_post($front_id);
        if ($home_post) {
            $content_string = tct_build_content_string($home_post);
            $normalized = tct_normalize_text($content_string);
            $hash = tct_compute_fingerprint($normalized);
            $modified = get_post_modified_time('c', true, $home_post);

            $items[] = [
                'cUrl' => $home_url,
                'mUrl' => $home_m_url,
                'modified' => $modified,
                'contentHash' => $hash,
            ];
        }
    } else {
        // Blog list homepage - use synthetic content
        if (function_exists('tct_create_homepage_pseudo_post')) {
            $pseudo = tct_create_homepage_pseudo_post();
            // Build authoritative content string for parity with endpoint
            $content_string = tct_build_content_string($pseudo);
            $normalized = tct_normalize_text($content_string);
            $hash = tct_compute_fingerprint($normalized);
            $modified = gmdate('c', strtotime($pseudo->post_modified_gmt));

            $items[] = [
                'cUrl' => $home_url,
                'mUrl' => $home_m_url,
                'modified' => $modified,
                'contentHash' => $hash,
            ];
        }
    }

    // Add all other posts/pages
    foreach ((array)$ids as $pid) {
        $c_url = get_permalink($pid);
        if (!$c_url) { continue; }
        $m_url = trailingslashit($c_url) . trailingslashit($endpoint);

        // Hash: ask payload filter for hash first, else compute
        $hash = null;
        $post = get_post($pid);
        $filtered = apply_filters('tct_build_payload', null, $post, $c_url, $m_url);
        if (is_array($filtered) && isset($filtered['hash'])) {
            $hash = $filtered['hash'];
        }
        if (!$hash) {
            $content_string = tct_build_content_string($post);
            $normalized = tct_normalize_text($content_string);
            $hash = tct_compute_fingerprint($normalized);
        }
        $items[] = [
            'cUrl' => trailingslashit($c_url),
            'mUrl' => trailingslashit($m_url),
            'modified' => get_post_modified_time('c', true, $post),
            'contentHash' => $hash,
        ];
    }
    $out = [
        'version' => 1,
        'profile' => 'tct-1',
        'items' => $items,
    ];
    echo wp_json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

