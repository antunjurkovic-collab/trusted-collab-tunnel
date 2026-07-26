<?php
/**
 * Plugin Name: Trusted Collaboration Tunnel — Internal Draft-03 Reference
 * Plugin URI: https://llmpages.org
 * Description: Internal reference implementation of the Collaboration Content Transfer Draft-03 HTTP profile.
 * Version: 3.0.0-alpha.3
 * Requires at least: 6.0
 * Requires PHP: 8.1
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

define('TCT_VERSION', '3.0.0-alpha.3');
define('TCT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCT_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function($class) {
    $prefix = 'TCT\\Draft03\\';
    if (!is_string($class) || !str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === '' || preg_match('/^[A-Za-z0-9_\\\\]+$/D', $relative) !== 1) {
        return;
    }

    $file = TCT_PLUGIN_DIR . 'src/Draft03/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once TCT_PLUGIN_DIR . 'includes/Hashing.php';
require_once TCT_PLUGIN_DIR . 'includes/Policy.php';
require_once TCT_PLUGIN_DIR . 'includes/PolicyDescriptor.php';
require_once TCT_PLUGIN_DIR . 'includes/Auth.php';
require_once TCT_PLUGIN_DIR . 'includes/Receipt.php';
require_once TCT_PLUGIN_DIR . 'includes/Cache.php';
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

// Machine resources cannot remain deterministic if PHP display_errors writes
// diagnostics into their response bytes. Logging remains under site policy.
if (tct_is_protocol_response_request() && function_exists('ini_set')) {
    @ini_set('display_errors', '0');
}

add_action('save_post', 'tct_refresh_post_identity', 20, 3);
add_action('delete_post', 'tct_invalidate_post_identity');
add_action('trashed_post', 'tct_invalidate_post_identity');
add_action('untrashed_post', 'tct_invalidate_post_identity');

/**
 * Invalidate the exact post representation and every catalog that names it.
 */
function tct_invalidate_post_identity($post_id = null) {
    $post_id = (int) $post_id;
    if ($post_id > 0 && (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id))) {
        return;
    }

    if ($post_id > 0) {
        tct_delete_cached_identity($post_id);
    }
    tct_delete_cached_identity(0);
    tct_delete_cached_sitemap_identity();
    delete_transient('tct_sitemap_recent_cache_v2');
}

/**
 * Build and cache one certified identity representation on the write path.
 */
function tct_refresh_post_identity($post_id, $post, $update) {
    unset($update);
    $post_id = (int) $post_id;
    if ($post_id <= 0 || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    tct_invalidate_post_identity($post_id);

    // Alpha.1 metadata/transients are historical implementation details and
    // are never consulted by the alpha.2/alpha.3 wire generation.
    delete_post_meta($post_id, '_tct_etag');
    delete_transient('tct_payload_' . $post_id);

    if (($post->post_type ?? '') === 'attachment') {
        tct_invalidate_protocol_generation();
        return;
    }

    if (!tct_post_is_exposable($post)) {
        return;
    }

    $c_url = tct_c_url_for_post($post);
    if (!is_string($c_url) || $c_url === '') {
        return;
    }
    $m_url = tct_m_url_for_c_url($c_url);

    try {
        [, $identity] = tct_build_murl_identity($post, $c_url, $m_url);
        tct_set_cached_identity($post_id, $identity);
        tct_record_change(
            $post,
            $c_url,
            $m_url,
            $identity->catalogEtag(),
            get_post_modified_time('c', true, $post)
        );
    } catch (\Throwable $exception) {
        error_log('TCT Draft-03 write-path build failed: ' . $exception->getMessage());
    }
}

/**
 * Changes to shared representation dependencies invalidate the entire current
 * generation without enumerating transient rows.
 */
function tct_invalidate_protocol_generation() {
    tct_bump_cache_epoch();
}

add_action('profile_update', 'tct_invalidate_protocol_generation');
add_action('edited_term', 'tct_invalidate_protocol_generation');
add_action('delete_term', 'tct_invalidate_protocol_generation');
add_action('created_term', 'tct_invalidate_protocol_generation');
add_action('update_option_blogname', 'tct_invalidate_protocol_generation');
add_action('update_option_blogdescription', 'tct_invalidate_protocol_generation');
add_action('update_option_page_on_front', 'tct_invalidate_protocol_generation');
add_action('update_option_page_for_posts', 'tct_invalidate_protocol_generation');
add_action('update_option_permalink_structure', 'tct_invalidate_protocol_generation');
add_action('update_option_tct_endpoint_slug', 'tct_invalidate_protocol_generation');
add_action('update_option_tct_sitemap_path', 'tct_invalidate_protocol_generation');
add_action('update_option_tct_include_headings', 'tct_invalidate_protocol_generation');
add_action('update_option_tct_force_full_content', 'tct_invalidate_protocol_generation');

function tct_invalidate_post_term_dependency($object_id) {
    tct_invalidate_post_identity((int) $object_id);
}
add_action('set_object_terms', 'tct_invalidate_post_term_dependency');

function tct_invalidate_attachment_alt_dependency($meta_id, $object_id, $meta_key) {
    unset($meta_id);
    if ($meta_key === '_wp_attachment_image_alt') {
        tct_invalidate_protocol_generation();
    } elseif ($meta_key === '_thumbnail_id') {
        tct_invalidate_post_identity((int) $object_id);
    }
}
add_action('added_post_meta', 'tct_invalidate_attachment_alt_dependency', 10, 3);
add_action('updated_post_meta', 'tct_invalidate_attachment_alt_dependency', 10, 3);
add_action('deleted_post_meta', 'tct_invalidate_attachment_alt_dependency', 10, 3);


// CRITICAL: Prevent WordPress from setting 404 on TCT endpoints
// Use pre_handle_404 filter (WP 5.5+) to prevent 404 before LiteSpeed Cache sees it
add_filter('pre_handle_404', 'tct_prevent_404_on_endpoints', 10, 2);

function tct_request_is_protocol_route($path) {
    if (!is_string($path)) {
        return false;
    }

    $path = '/' . ltrim($path, '/');
    $singleton_paths = [
        (string) get_option('tct_sitemap_path', '/llm-sitemap.json'),
        (string) get_option('tct_manifest_path', '/llm-manifest.json'),
        (string) get_option('tct_llms_path', '/llms.txt'),
        '/llm-policy.json',
        '/llm-stats.json',
        '/llm-changes.json',
    ];
    foreach ($singleton_paths as $singleton_path) {
        $singleton_path = (string) parse_url($singleton_path, PHP_URL_PATH);
        if ($singleton_path !== '' && $path === '/' . ltrim($singleton_path, '/')) {
            return true;
        }
    }

    $endpoint = sanitize_title((string) get_option('tct_endpoint_slug', 'llm'));
    if ($endpoint === '') {
        $endpoint = 'llm';
    }

    return preg_match('~^/(?:.+/)?' . preg_quote($endpoint, '~') . '/?$~', $path) === 1;
}

function tct_is_protocol_response_request() {
    if (
        isset($_GET['tct_m_url'])
        && is_string($_GET['tct_m_url'])
        && wp_unslash($_GET['tct_m_url']) === '1'
    ) {
        return true;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url((string) $uri, PHP_URL_PATH);
    return tct_request_is_protocol_route($path);
}

function tct_prevent_404_on_endpoints($preempt, $wp_query) {
    unset($wp_query);
    if (tct_is_protocol_response_request()) {
        return true;
    }

    return $preempt;
}

// Fallback for WP < 5.5: Clear 404 flag on wp hook as backup
add_action('wp', 'tct_clear_404_fallback', 5);

function tct_clear_404_fallback() {
    global $wp_query;
    if (!isset($wp_query)) return;

    if (tct_is_protocol_response_request() && $wp_query->is_404) {
        $wp_query->is_404 = false;
        status_header(200);
    }
}



// Central request router: handle /llm-sitemap.json, /llms.txt, and */llm/
add_action('template_redirect', 'tct_handle_requests', 0);

// Add HTML rel="alternate" link for pages/front page (optional but recommended)
add_action('wp_head', 'tct_output_html_alternate_link', 5);

// Add C-URL discovery only after WordPress has established query state.
add_action('template_redirect', 'tct_add_root_link_header', -1);

function tct_add_root_link_header() {
    if (tct_is_protocol_response_request()) {
        return;
    }

    // Only add Link header on homepage (root)
    if (!is_front_page() && !is_home()) {
        return;
    }

    // Get sitemap path from settings
    $sitemap_path = get_option('tct_sitemap_path', '/llm-sitemap.json');

    header(
        'Link: <' . esc_url_raw(home_url($sitemap_path)) . '>; rel="index"; type="application/json"',
        false
    );
}

function tct_activate_plugin() {
    if (PHP_VERSION_ID < 80100 || PHP_INT_SIZE < 8 || !extension_loaded('mbstring')) {
        wp_die(
            esc_html(
                'TCT alpha.3 requires 64-bit PHP 8.1 or newer with the mbstring extension.'
            )
        );
    }

    add_option('tct_endpoint_slug', 'llm');
    add_option('tct_sitemap_path', '/llm-sitemap.json');
    add_option('tct_manifest_path', '/llm-manifest.json');
    add_option('tct_llms_path', '/llms.txt');
    add_option('tct_terms_url', '');
    add_option('tct_pricing_url', '');
    add_option('tct_auth_mode', 'off');
    add_option('tct_api_key_hashes', []);
    add_option('tct_v03_cache_epoch', 1, '', false);
    add_option('tct_receipts_enabled', 0);
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
    tct_register_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'tct_activate_plugin');

function tct_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'tct_deactivate_plugin');

/**
 * Register configured protocol routes before activation flushes them.
 */
function tct_register_rewrite_rules() {
    $enabled = (int) get_option('tct_root_rewrite_enabled', 1);
    if (!$enabled) return;
    $slug = sanitize_title((string) get_option('tct_endpoint_slug', 'llm'));
    if ($slug === '') $slug = 'llm';

    add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?tct_llm_root=1', 'top');

    $routes = [
        [(string) get_option('tct_sitemap_path', '/llm-sitemap.json'), 'tct_sitemap'],
        [(string) get_option('tct_manifest_path', '/llm-manifest.json'), 'tct_manifest'],
        [(string) get_option('tct_llms_path', '/llms.txt'), 'tct_llms'],
        ['/llm-policy.json', 'tct_policy'],
        ['/llm-stats.json', 'tct_stats'],
        ['/llm-changes.json', 'tct_changes'],
    ];

    foreach ($routes as [$path, $query_var]) {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
        if ($path !== '') {
            add_rewrite_rule(
                '^' . preg_quote($path, '/') . '$',
                'index.php?' . $query_var . '=1',
                'top'
            );
        }
    }
}
add_action('init', 'tct_register_rewrite_rules');

function tct_flush_rewrites_after_setting_change($old_value, $new_value) {
    if ($old_value === $new_value) {
        return;
    }
    tct_register_rewrite_rules();
    flush_rewrite_rules();
}
add_action('update_option_tct_endpoint_slug', 'tct_flush_rewrites_after_setting_change', 20, 2);
add_action('update_option_tct_sitemap_path', 'tct_flush_rewrites_after_setting_change', 20, 2);
add_action('update_option_tct_manifest_path', 'tct_flush_rewrites_after_setting_change', 20, 2);
add_action('update_option_tct_llms_path', 'tct_flush_rewrites_after_setting_change', 20, 2);

// Allow tct_llm_root as a public query var
add_filter('query_vars', function($vars) {
    if (is_array($vars)) { $vars[] = 'tct_llm_root'; }
    if (is_array($vars)) { $vars[] = 'tct_sitemap'; }
    if (is_array($vars)) { $vars[] = 'tct_manifest'; }
    if (is_array($vars)) { $vars[] = 'tct_llms'; }
    if (is_array($vars)) { $vars[] = 'tct_policy'; }
    if (is_array($vars)) { $vars[] = 'tct_stats'; }
    if (is_array($vars)) { $vars[] = 'tct_changes'; }
    if (is_array($vars)) { $vars[] = 'tct_m_url'; }
    return $vars;
});
