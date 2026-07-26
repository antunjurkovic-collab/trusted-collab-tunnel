<?php

use TCT\Compatibility\Doctor\DeploymentDoctor;
use TCT\Compatibility\Doctor\DoctorContext;
use TCT\Compatibility\WordPress\ProviderSignals;
use TCT\Compatibility\WordPress\WordPressHttpTransport;
use TCT\Compatibility\WordPress\WordPressReportSerializer;

if (!defined('ABSPATH') || !defined('TCT_VERSION')) {
    throw new RuntimeException('Run this fixture through WP-CLI with TCT active.');
}

$sitemapPath = (string) get_option('tct_sitemap_path', '/llm-sitemap.json');
$endpoint = sanitize_title((string) get_option('tct_endpoint_slug', 'llm'));
$routeSignature = hash('sha256', implode("\n", [
    TCT_VERSION,
    home_url('/'),
    $sitemapPath,
    $endpoint !== '' ? $endpoint : 'llm',
    (string) get_option('permalink_structure', ''),
]));
$context = new DoctorContext(
    home_url('/'),
    home_url($sitemapPath),
    TCT_VERSION,
    $routeSignature,
    'external validator',
    (string) get_option('tct_auth_mode', 'off') === 'off',
    ProviderSignals::collect()
);
$report = (new DeploymentDoctor())->run($context, new WordPressHttpTransport());
echo (new WordPressReportSerializer())->serialize($report);
