<?php
if (!defined('ABSPATH')) { exit; }

function tct_output_html_alternate_link() {
    // Only on front page or singular content
    if (!(is_front_page() || is_singular())) return;
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));

    // Per draft-03: Add M-Sitemap discovery link on homepage
    if (is_front_page()) {
        $sitemap_path = get_option('tct_sitemap_path', '/llm-sitemap.json');
        $sitemap_url = home_url($sitemap_path);
        echo '<link rel="index" type="application/json" href="' . esc_url($sitemap_url) . '" title="TCT M-Sitemap" />' . "\n";
    }

    if (is_front_page()) {
        $c = home_url('/');
    } else {
        $c = get_permalink();
        if (!$c) return;
    }
    $m = trailingslashit($c) . trailingslashit($endpoint);
    echo '<link rel="alternate" type="application/json" href="' . esc_url($m) . '" title="LLM Semantic Document - AI-Optimized Content" />' . "\n";
}

