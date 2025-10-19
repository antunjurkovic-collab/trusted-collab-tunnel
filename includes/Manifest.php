<?php
if (!defined('ABSPATH')) { exit; }

function tct_output_manifest() {
    header('Content-Type: application/json; charset=UTF-8', true);
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    $sitemap_path = get_option('tct_sitemap_path', '/llm-sitemap.json');
    $out = [
        'version' => 1,
        'endpoint_pattern' => '{canonical}/' . $endpoint . '/',
        'capabilities' => [
            'supports_diff' => false,
            'supports_minimal' => true,
            'supported_formats' => ['json'],
        ],
        'hash' => [
            'algorithm' => 'sha256',
            'format' => 'etag',
        ],
        'sitemap' => [
            'url' => $sitemap_path,
        ],
    ];
    echo wp_json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

