<?php
/**
 * Plugin Name: Trusted Collaboration Tunnel
 * Plugin URI: https://llmpages.org
 * Description: AI-optimized content delivery with sitemap-first discovery, template-invariant ETags, and 304 discipline. Reduces AI crawler bandwidth by 60-90%.
 * Version: 3.0.0-alpha.1
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

define('TCT_VERSION', '3.0.0-alpha.1');
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
require_once TCT_PLUGIN_DIR . 'includes/AdminCache.php';
require_once TCT_PLUGIN_DIR . 'includes/Stats.php';
require_once TCT_PLUGIN_DIR . 'includes/Changes.php';
require_once TCT_PLUGIN_DIR . 'includes/Shortcodes.php';

// Cache invalidation for sitemap (Phase 0: Expert-approved pattern)
// Invalidates cache when content changes to ensure fresh sitemaps
add_action('save_post', 'tct_invalidate_sitemap_cache');
add_action('delete_post', 'tct_invalidate_sitemap_cache');
add_action('trash_post', 'tct_invalidate_sitemap_cache');
add_action('untrash_post', 'tct_invalidate_sitemap_cache');

/**
 * Invalidate sitemap cache when content changes.
 *
 * This ensures the sitemap reflects current site content without
 * requiring full regeneration on every request.
 *
 * @param int|null $post_id The post ID being modified
 */
function tct_invalidate_sitemap_cache($post_id = null) {
    // Ignore revisions and autosaves
    if ($post_id && (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id))) {
        return;
    }

    // Clear both old and new cache versions (for smooth upgrade)
    delete_transient('tct_sitemap_cache_v2');
    delete_transient('tct_sitemap_cache_v3');
    delete_transient('tct_sitemap_json_v3');
    delete_transient('tct_sitemap_etag_v3');

    // Also invalidate recent changes cache if implemented
    delete_transient('tct_sitemap_recent_cache_v2');
}

// PHASE 1.2: Precompute ETag + Payload on Save (wp-dual-native pattern)
// Hook into save_post and status transitions to shift work to write path
add_action('save_post', 'tct_precompute_etag', 10, 3);
add_action('transition_post_status', 'tct_precompute_etag_status', 10, 3);

/**
 * Precompute ETag and payload when post changes (shift work to write path)
 * Matches wp-dual-native pattern of computing CID on mutation
 *
 * @param int $post_id Post ID being saved
 * @param WP_Post $post Post object
 * @param bool $update Whether this is an update
 */
function tct_precompute_etag($post_id, $post, $update) {
    // Guard against autosaves/revisions
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // Scope to relevant post types (avoid work on posts not in TCT)
    $blocked = apply_filters('tct_block_post_types', ['product']);
    if ($post && is_array($blocked) && in_array($post->post_type, $blocked, true)) {
        return;
    }

    // Clear old caches
    delete_post_meta($post_id, '_tct_etag');
    delete_transient('tct_payload_' . $post_id);

    // Only precompute for published posts (skip drafts to save resources)
    if ($post->post_status !== 'publish') {
        return;
    }

    // Precompute new ETag + payload
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    $c_url = get_permalink($post_id);
    if (!$c_url) return;

    $m_url = trailingslashit($c_url) . trailingslashit($endpoint);

    list($payload, $hash) = tct_build_tct_payload_and_hash($post, $c_url, $m_url);

    // Store for instant 200 or 304
    update_post_meta($post_id, '_tct_etag', $hash);
    set_transient('tct_payload_' . $post_id, $payload, WEEK_IN_SECONDS);
}

/**
 * Handle publish/unpublish transitions
 *
 * @param string $new_status New post status
 * @param string $old_status Old post status
 * @param WP_Post $post Post object
 */
function tct_precompute_etag_status($new_status, $old_status, $post) {
    // Precompute when publishing or unpublishing
    if ($new_status === 'publish' || $old_status === 'publish') {
        tct_precompute_etag($post->ID, $post, true);
    }
}


// CRITICAL: Prevent WordPress from setting 404 on TCT endpoints
// Use pre_handle_404 filter (WP 5.5+) to prevent 404 before LiteSpeed Cache sees it
add_filter('pre_handle_404', 'tct_prevent_404_on_endpoints', 10, 2);

function tct_prevent_404_on_endpoints($preempt, $wp_query) {
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) {
        return $preempt;
    }
    $path = '/' . ltrim($path, '/');

    // Check exact TCT singleton endpoints and exact M-URL patterns only.
    $is_tct_request = (
        in_array($path, array('/llm-sitemap.json', '/llm-policy.json', '/llm-manifest.json', '/llm-stats.json', '/llm-changes.json', '/llms.txt'), true) ||
        preg_match('~^/' . preg_quote($endpoint, '~') . '/?$~', $path) ||
        preg_match('~^/.+?/' . preg_quote($endpoint, '~') . '/?$~', $path)
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
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) return;
    $path = '/' . ltrim($path, '/');

    $is_tct_request = (
        in_array($path, array('/llm-sitemap.json', '/llm-policy.json', '/llm-manifest.json', '/llm-stats.json', '/llm-changes.json', '/llms.txt'), true) ||
        preg_match('~^/' . preg_quote($endpoint, '~') . '/?$~', $path) ||
        preg_match('~^/.+?/' . preg_quote($endpoint, '~') . '/?$~', $path)
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
        'Link: <' . esc_url_raw(home_url($sitemap_path)) . '>; rel="index"; type="application/json"; profile="tct-1"',
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
    add_option('tct_stats_enabled', 0);
    add_option('tct_changes_enabled', 0);
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
