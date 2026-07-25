<?php
if (!defined('ABSPATH')) { exit; }

function tct_auth_required() {
    $mode = get_option('tct_auth_mode', 'off');
    return apply_filters('tct_auth_required', $mode !== 'off');
}

/**
 * Return request headers with lowercase names.
 *
 * FastCGI and Apache expose Authorization differently, so no single source is
 * sufficient on all supported WordPress deployments.
 *
 * @return array<string, string>
 */
function tct_request_headers() {
    $headers = [];
    if (function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[strtolower($name)] = $value;
            }
        }
    }

    foreach ($_SERVER as $name => $value) {
        if (!is_string($value)) {
            continue;
        }

        if (str_starts_with($name, 'HTTP_')) {
            $header_name = strtolower(str_replace('_', '-', substr($name, 5)));
            $headers[$header_name] = $value;
        }
    }

    if (
        !isset($headers['authorization'])
        && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
        && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
    ) {
        $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    return $headers;
}

/**
 * Load valid SHA-256 key digests from configuration.
 *
 * Persistent configuration contains digests only. TCT_API_KEYS is an optional
 * comma-separated runtime secret source for local/internal deployments.
 *
 * @return string[]
 */
function tct_api_key_hashes() {
    $hashes = get_option('tct_api_key_hashes', []);
    if (!is_array($hashes)) {
        $hashes = [];
    }

    $environment_keys = getenv('TCT_API_KEYS');
    if (is_string($environment_keys) && $environment_keys !== '') {
        foreach (explode(',', $environment_keys) as $key) {
            $key = trim($key);
            if ($key !== '') {
                $hashes[] = hash('sha256', $key);
            }
        }
    }

    $valid = [];
    foreach ($hashes as $hash) {
        if (is_string($hash) && preg_match('/^[0-9a-f]{64}$/D', $hash) === 1) {
            $valid[$hash] = true;
        }
    }

    return array_keys($valid);
}

function tct_auth_ok() {
    $mode = get_option('tct_auth_mode', 'off');
    if ($mode === 'off') { return true; }
    if ($mode !== 'api_key') { return false; }

    $headers = tct_request_headers();
    $auth = $headers['authorization'] ?? '';
    $bearer = '';
    if ($auth !== '' && stripos($auth, 'Bearer ') === 0) {
        $bearer = trim(substr($auth, 7));
    }
    $x_api_key = trim($headers['x-api-key'] ?? '');

    $candidate_hashes = [];
    foreach ([$bearer, $x_api_key] as $candidate) {
        if ($candidate !== '' && strlen($candidate) <= 4096) {
            $candidate_hashes[] = hash('sha256', $candidate);
        }
    }

    $configured_hashes = tct_api_key_hashes();
    foreach ($configured_hashes as $configured_hash) {
        foreach ($candidate_hashes as $candidate_hash) {
            if (hash_equals($configured_hash, $candidate_hash)) {
                return true;
            }
        }
    }

    /**
     * Filter for custom auth validation (e.g., JWT). Return true to accept.
     */
    $ok = apply_filters('tct_validate_auth', false, $auth, $x_api_key, $configured_hashes);
    return (bool)$ok;
}

/**
 * Apply one exposure decision consistently to endpoint, catalog, and links.
 */
function tct_post_is_exposable($post) {
    if (!is_object($post)) {
        return false;
    }

    $post_id = isset($post->ID) ? (int) $post->ID : 0;
    $post_type = isset($post->post_type) ? (string) $post->post_type : '';
    $post_status = isset($post->post_status) ? (string) $post->post_status : '';
    $password = isset($post->post_password) ? (string) $post->post_password : '';

    // The internal synthetic homepage is a public protocol resource.
    if ($post_id === 0 && $post_type === 'homepage' && $post_status === 'publish') {
        return (bool) apply_filters('tct_post_is_exposable', true, $post);
    }

    $exposable = $post_id > 0
        && $post_status === 'publish'
        && $password === '';

    $blocked_types = apply_filters('tct_block_post_types', ['attachment', 'product']);
    if (!is_array($blocked_types)) {
        $blocked_types = ['attachment', 'product'];
    }
    if (in_array($post_type, $blocked_types, true)) {
        $exposable = false;
    }

    // Archive placeholders do not have a corresponding singular C-URL.
    if ($post_id === (int) get_option('page_for_posts', 0)) {
        $exposable = false;
    }

    if (function_exists('wc_get_page_id') && $post_id === (int) wc_get_page_id('shop')) {
        $exposable = false;
    }

    if ($exposable && function_exists('is_post_publicly_viewable')) {
        $exposable = (bool) is_post_publicly_viewable($post);
    } elseif ($exposable && function_exists('get_post_type_object')) {
        $type = get_post_type_object($post_type);
        $exposable = is_object($type)
            && (!empty($type->public) || !empty($type->publicly_queryable));
    }

    return (bool) apply_filters('tct_post_is_exposable', $exposable, $post);
}

