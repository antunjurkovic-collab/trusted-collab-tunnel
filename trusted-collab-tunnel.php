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

// CRITICAL: Set no-cache headers VERY early for M-URLs
// This runs before template_redirect to prevent server-level caching
add_action('send_headers', 'tct_prevent_server_cache', 1);

function tct_prevent_server_cache() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    
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
        // Set headers to prevent server-level caching
        // These run before any cache layer can intercept
        if (!headers_sent()) {
            header('X-LiteSpeed-Cache-Control: no-cache', false);
            header('X-Accel-Expires: 0', false); // Nginx cache
            header('Surrogate-Control: no-store', false); // Varnish/CDN
            // Don't set Cache-Control here - let Endpoint.php handle it properly
        }
    }
}

// Central request router: handle /llm-sitemap.json, /llms.txt, and */llm/
add_action('template_redirect', 'tct_handle_requests', 0);

// Automatically exclude M-URLs from caching plugins
// This ensures 304 Not Modified responses work correctly
add_action('init', 'tct_exclude_from_cache_plugins', 1);

function tct_exclude_from_cache_plugins() {
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    
    // LiteSpeed Cache plugin integration
    if (defined('LSCWP_V')) {
        add_filter('litespeed_cache_is_cacheable', function($cacheable) use ($endpoint) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            // Exclude M-URLs and TCT JSON endpoints from LiteSpeed cache
            if (strpos($uri, '/' . $endpoint . '/') !== false ||
                strpos($uri, '/llm-sitemap.json') !== false ||
                strpos($uri, '/llm-policy.json') !== false ||
                strpos($uri, '/llm-manifest.json') !== false ||
                strpos($uri, '/llm-stats.json') !== false ||
                strpos($uri, '/llm-changes.json') !== false) {
                return false; // Don't cache
            }
            return $cacheable;
        }, 10);
    }
    
    // WP Super Cache integration
    if (function_exists('wp_cache_serve_cache_file')) {
        add_filter('donotcachepage', function($donotcache) use ($endpoint) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/' . $endpoint . '/') !== false ||
                strpos($uri, '/llm-sitemap.json') !== false ||
                strpos($uri, '/llm-policy.json') !== false) {
                return true; // Don't cache
            }
            return $donotcache;
        }, 10);
    }
    
    // W3 Total Cache integration
    if (defined('W3TC')) {
        add_filter('w3tc_can_cache', function($can_cache, $type) use ($endpoint) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/' . $endpoint . '/') !== false ||
                strpos($uri, '/llm-sitemap.json') !== false ||
                strpos($uri, '/llm-policy.json') !== false) {
                return false; // Don't cache
            }
            return $can_cache;
        }, 10, 2);
    }
    
    // WP Rocket integration
    if (defined('WP_ROCKET_VERSION')) {
        add_filter('rocket_cache_reject_uri', function($uris) use ($endpoint) {
            $uris[] = '/' . $endpoint . '/';
            $uris[] = '/llm-sitemap.json';
            $uris[] = '/llm-policy.json';
            return $uris;
        }, 10);
    }
    
    // WP Fastest Cache integration
    if (class_exists('WpFastestCache')) {
        add_filter('wpfc_exclude_current_page', function($exclude) use ($endpoint) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/' . $endpoint . '/') !== false ||
                strpos($uri, '/llm-sitemap.json') !== false ||
                strpos($uri, '/llm-policy.json') !== false) {
                return true; // Don't cache
            }
            return $exclude;
        }, 10);
    }
}

// Add HTML rel="alternate" link for pages/front page (optional but recommended)
add_action('wp_head', 'tct_output_html_alternate_link', 5);

// Optional: activation defaults
register_activation_hook(__FILE__, function() {
    add_option('tct_endpoint_slug', 'llm');
    add_option('tct_sitemap_path', '/llm-sitemap.json');
    // Default manifest path moved to JSON to avoid colliding with llms.txt
    
    // CRITICAL: Add .htaccess rules to prevent LiteSpeed caching M-URLs
    tct_add_htaccess_rules();
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

// Add .htaccess rules to prevent server-level caching of M-URLs
function tct_add_htaccess_rules() {
    $htaccess_file = ABSPATH . '.htaccess';
    
    // Check if .htaccess exists and is writable
    if (!file_exists($htaccess_file)) {
        return; // Can't add rules if file doesn't exist
    }
    
    if (!is_writable($htaccess_file)) {
        return; // Can't modify if not writable
    }
    
    $htaccess_content = file_get_contents($htaccess_file);
    
    // Check if our rules are already present
    if (strpos($htaccess_content, '# BEGIN Trusted Collaboration Tunnel') !== false) {
        return; // Rules already added
    }
    
    $endpoint = trim(get_option('tct_endpoint_slug', 'llm'));
    
    $rules = <<<HTACCESS

# BEGIN Trusted Collaboration Tunnel
# These rules prevent server-level caching of M-URLs to enable 304 Not Modified responses
<IfModule LiteSpeed>
  # Bypass LiteSpeed cache for TCT endpoints
  RewriteEngine On
  RewriteCond %{REQUEST_URI} /{$endpoint}/$ [OR]
  RewriteCond %{REQUEST_URI} /llm-sitemap\.json$ [OR]
  RewriteCond %{REQUEST_URI} /llm-policy\.json$ [OR]
  RewriteCond %{REQUEST_URI} /llm-manifest\.json$ [OR]
  RewriteCond %{REQUEST_URI} /llm-stats\.json$ [OR]
  RewriteCond %{REQUEST_URI} /llm-changes\.json$ [OR]
  RewriteCond %{REQUEST_URI} /llms\.txt$
  RewriteRule .* - [E=Cache-Control:no-cache,E=no-cache:1]
</IfModule>

<IfModule mod_headers.c>
  # Ensure If-None-Match header passes through for conditional GET
  <FilesMatch "\.(json|txt)$">
    Header set X-TCT-Conditional "enabled"
  </FilesMatch>
</IfModule>
# END Trusted Collaboration Tunnel

HTACCESS;
    
    // Add rules at the TOP of .htaccess (before WordPress rules)
    // This ensures they run before any other rewrite rules
    file_put_contents($htaccess_file, $rules . $htaccess_content);
}

// Remove .htaccess rules on deactivation
register_deactivation_hook(__FILE__, function() {
    $htaccess_file = ABSPATH . '.htaccess';
    
    if (!file_exists($htaccess_file) || !is_writable($htaccess_file)) {
        return;
    }
    
    $htaccess_content = file_get_contents($htaccess_file);
    
    // Remove our rules
    $htaccess_content = preg_replace(
        '/# BEGIN Trusted Collaboration Tunnel.*?# END Trusted Collaboration Tunnel
?/s',
        '',
        $htaccess_content
    );
    
    file_put_contents($htaccess_file, $htaccess_content);
});
