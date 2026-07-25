<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Resolve the exposable post represented by the current human-facing page.
 */
function tct_current_c_url_post() {
    if (is_front_page()) {
        return tct_protocol_homepage_post();
    }

    if (!is_singular()) {
        return null;
    }

    $post = get_queried_object();
    return $post && tct_post_is_exposable($post) ? $post : null;
}

function tct_output_html_alternate_link() {
    if (is_front_page()) {
        $sitemap_path = (string) get_option('tct_sitemap_path', '/llm-sitemap.json');
        echo '<link rel="index" type="application/json" href="'
            . esc_url(home_url($sitemap_path))
            . '">' . "\n";
    }

    $post = tct_current_c_url_post();
    if (!$post) {
        return;
    }
    $c_url = tct_c_url_for_post($post);
    if (!is_string($c_url) || $c_url === '') {
        return;
    }

    echo '<link rel="alternate" type="application/json" href="'
        . esc_url(tct_m_url_for_c_url($c_url))
        . '">' . "\n";
}

/**
 * C-URL to M-URL discovery is preferably exposed in the HTTP Link field.
 */
function tct_add_c_url_alternate_header() {
    if (tct_is_protocol_response_request()) {
        return;
    }

    $post = tct_current_c_url_post();
    if (!$post) {
        return;
    }
    $c_url = tct_c_url_for_post($post);
    if (!is_string($c_url) || $c_url === '') {
        return;
    }

    header(
        'Link: <' . esc_url_raw(tct_m_url_for_c_url($c_url))
        . '>; rel="alternate"; type="application/json"',
        false
    );
}
add_action('template_redirect', 'tct_add_c_url_alternate_header', -1);
