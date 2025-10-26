<?php
if (!defined('ABSPATH')) { exit; }

function tct_output_sitemap() {
    header('Content-Type: application/json; charset=UTF-8', true);

    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    // Collect recent posts/pages (publish). Sites can filter this query.
    $qargs = [
        'post_type' => 'any',
        'post_status' => 'publish',
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
            $html = apply_filters('the_content', $home_post->post_content);
            $html = '<h1>' . esc_html(get_the_title($home_post)) . '</h1>' . $html;
            $normalized = tct_normalize_text($html);
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
            $normalized = tct_normalize_text($pseudo->post_content);
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
            $html = apply_filters('the_content', $post->post_content);
            $html = '<h1>' . esc_html(get_the_title($post)) . '</h1>' . $html;
            $normalized = tct_normalize_text($html);
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

