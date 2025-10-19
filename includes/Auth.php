<?php
if (!defined('ABSPATH')) { exit; }

function tct_auth_required() {
    $mode = get_option('tct_auth_mode', 'off');
    return apply_filters('tct_auth_required', $mode !== 'off');
}

function tct_auth_ok() {
    $mode = get_option('tct_auth_mode', 'off');
    if ($mode === 'off') { return true; }

    $api_keys = get_option('tct_api_keys', []);
    if (!is_array($api_keys)) { $api_keys = []; }

    // Check Bearer token or X-API-Key header
    $hdrs = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth && isset($hdrs['Authorization'])) {
        $auth = $hdrs['Authorization'];
    }
    $bearer = '';
    if ($auth && stripos($auth, 'Bearer ') === 0) {
        $bearer = trim(substr($auth, 7));
    }
    $x_api_key = $_SERVER['HTTP_X_API_KEY'] ?? ($hdrs['X-API-Key'] ?? '');

    if ($bearer && in_array($bearer, $api_keys, true)) { return true; }
    if ($x_api_key && in_array($x_api_key, $api_keys, true)) { return true; }

    /**
     * Filter for custom auth validation (e.g., JWT). Return true to accept.
     */
    $ok = apply_filters('tct_validate_auth', false, $auth, $x_api_key, $api_keys);
    return (bool)$ok;
}

