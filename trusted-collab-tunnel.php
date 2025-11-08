<?php
/**
 * Plugin Name: Trusted Collaboration Tunnel
 * Plugin URI: https://llmpages.org
 * Description: AI-optimized content delivery with sitemap-first discovery, template-invariant ETags, and 304 discipline. Reduces AI crawler bandwidth by 60-90%.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Antun Jurkovikj
 * Author URI: https://llmpages.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tct
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TCT_VERSION', '1.0.0');
define('TCT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TCT_PLUGIN_DIR . 'includes/Hashing.php';
require_once TCT_PLUGIN_DIR . 'includes/Policy.php';
require_once TCT_PLUGIN_DIR . 'includes/PolicyDescriptor.php';
require_once TCT_PLUGIN_DIR . 'includes/Auth.php';
require_once TCT_PLUGIN_DIR . 'includes/Receipt.php';
require_once TCT_PLUGIN_DIR . 'includes/Endpoint.php';
require_once TCT_PLUGIN_DIR . 'includes/Sitemap.php';
require_once TCT_PLUGIN_DIR . 'includes/Manifest.php';
require_once TCT_PLUGIN_DIR . 'includes/HeadLinks.php';
require_once TCT_PLUGIN_DIR . 'includes/LLMS.php';
require_once TCT_PLUGIN_DIR . 'includes/Admin.php';
require_once TCT_PLUGIN_DIR . 'includes/Stats.php';
require_once TCT_PLUGIN_DIR . 'includes/Changes.php';
require_once TCT_PLUGIN_DIR . 'includes/Shortcodes.php';


// CRITICAL: Prevent WordPress from setting 404 on TCT endpoints
// Use pre_handle_404 filter (WP 5.5+) to prevent 404 before LiteSpeed Cache sees it
add_filter('pre_handle_404', 'tct_prevent_404_on_endpoints', 10, 2);

function tct_prevent_404_on_endpoints($preempt, $wp_query) {
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Check if this is an M-URL or TCT endpoint
    $is_tct_request = (
        strpos($uri, '/' . $endpoint . '/') !== false ||
        strpos($uri, '/llm-sitemap.json') !== false ||
        strpos($uri, '/llm-policy.json') !== false ||
        strpos($uri, '/llm-manifest.json') !== false ||
        strpos($uri, '/llm-stats.json') !== false ||
        strpos($uri, '/llm-changes.json') !== false ||
        strpos($uri, '/llms.txt') !== false
    );

    if ($is_tct_request) {
        // Return true to prevent WordPress from setting is_404 = true
        return true;
    }

    return $preempt;
}

// Fallback for WP < 5.5: Clear 404 flag on wp hook as backup
add_action('wp', 'tct_clear_404_fallback', 5);

function tct_clear_404_fallback() {
    global $wp_query;
    if (!isset($wp_query)) return;
    
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    $is_tct_request = (
        strpos($uri, '/' . $endpoint . '/') !== false ||
        strpos($uri, '/llm-sitemap.json') !== false ||
        strpos($uri, '/llm-policy.json') !== false ||
        strpos($uri, '/llm-manifest.json') !== false ||
        strpos($uri, '/llm-stats.json') !== false ||
        strpos($uri, '/llm-changes.json') !== false ||
        strpos($uri, '/llms.txt') !== false
    );

    if ($is_tct_request && $wp_query->is_404) {
        $wp_query->is_404 = false;
        status_header(200);
    }
}



// Central request router: handle /llm-sitemap.json, /llms.txt, and */llm/
add_action('template_redirect', 'tct_handle_requests', 0);

// Add HTML rel="alternate" link for pages/front page (optional but recommended)
add_action('wp_head', 'tct_output_html_alternate_link', 5);

// Add Link header on root for M-Sitemap discovery (draft-01 Section 4.1 REQUIRED)
add_action('send_headers', 'tct_add_root_link_header');

function tct_add_root_link_header() {
    // Only add Link header on homepage (root)
    if (!is_front_page() && !is_home()) {
        return;
    }

    // Get sitemap path from settings
    $sitemap_path = get_option('tct_sitemap_path', '/llm-sitemap.json');

    // Send Link header per draft-jurkovikj-collab-tunnel-01 Section 4.1
    // MUST include: rel="index" and type="application/json"
    header(
        'Link: <' . esc_url_raw(home_url($sitemap_path)) . '>; rel="index"; type="application/json"',
        false
    );
}

// Optional: activation defaults
register_activation_hook(__FILE__, function() {
    add_option('tct_endpoint_slug', 'llm');
    add_option('tct_sitemap_path', '/llm-sitemap.json');
    // Default manifest path moved to JSON to avoid colliding with llms.txt
    add_option('tct_manifest_path', '/llm-manifest.json');
    // New: public path for human-readable llms.txt
    add_option('tct_llms_path', '/llms.txt');
    add_option('tct_terms_url', '');
    add_option('tct_pricing_url', '');
    add_option('tct_auth_mode', 'off'); // off|api_key
    add_option('tct_api_keys', []); // array of strings
    add_option('tct_receipts_enabled', 0);
    add_option('tct_receipt_hmac_key', '');
    add_option('tct_root_rewrite_enabled', 1);
    add_option('tct_force_full_content', 1);
    add_option('tct_include_headings', 1);
    // llms.txt defaults
    add_option('tct_llms_virtual_enabled', 1);
    add_option('tct_llms_include_samples', 1);
    add_option('tct_llms_sample_count', 20);
    add_option('tct_llms_post_types', array('post','page'));
    add_option('tct_llms_include_xml_sitemap', 1);
    add_option('tct_llms_include_policies', 1);
    // Initialize policy descriptor defaults
    if (function_exists('tct_init_policy_defaults')) {
        tct_init_policy_defaults();
    }
    // Ensure rewrites are registered on first activation
    if (function_exists('flush_rewrite_rules')) { flush_rewrite_rules(); }
});

register_deactivation_hook(__FILE__, function() {
    // Clean rewrites on deactivation
    if (function_exists('flush_rewrite_rules')) { flush_rewrite_rules(); }
});

// Root /{endpoint}/ rewrite to guarantee routing across environments
add_action('init', function() {
    $enabled = (int) get_option('tct_root_rewrite_enabled', 1);
    if (!$enabled) return;
    $slug = trim(get_option('tct_endpoint_slug', 'llm'));
    if ($slug === '') $slug = 'llm';
    // Avoid conflict if a real Page exists at /{slug}/
    $page = function_exists('get_page_by_path') ? get_page_by_path($slug) : null;
    if ($page && $page instanceof WP_Post) return;
    add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?tct_llm_root=1', 'top');

    // Force WordPress to handle sitemap/manifest/llms even on hosts that treat .json/.txt as static
    add_rewrite_rule('^llm-sitemap\\.json$', 'index.php?tct_sitemap=1', 'top');
    add_rewrite_rule('^llm-manifest\\.json$', 'index.php?tct_manifest=1', 'top');
    add_rewrite_rule('^llms\\.txt$', 'index.php?tct_llms=1', 'top');
    add_rewrite_rule('^llm-policy\\.json$', 'index.php?tct_policy=1', 'top');
    add_rewrite_rule('^llm-stats\\.json$', 'index.php?tct_stats=1', 'top');
    add_rewrite_rule('^llm-changes\\.json$', 'index.php?tct_changes=1', 'top');
});

// Allow tct_llm_root as a public query var
add_filter('query_vars', function($vars) {
    if (is_array($vars)) { $vars[] = 'tct_llm_root'; }
    if (is_array($vars)) { $vars[] = 'tct_sitemap'; }
    if (is_array($vars)) { $vars[] = 'tct_manifest'; }
    if (is_array($vars)) { $vars[] = 'tct_llms'; }
    if (is_array($vars)) { $vars[] = 'tct_policy'; }
    if (is_array($vars)) { $vars[] = 'tct_stats'; }
    if (is_array($vars)) { $vars[] = 'tct_changes'; }
    return $vars;
});
