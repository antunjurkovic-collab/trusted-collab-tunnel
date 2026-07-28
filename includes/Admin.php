<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', static function() {
    add_options_page(
        'Collaboration Content Transfer (TCT)',
        'TCT',
        'manage_options',
        'tct-settings',
        'tct_render_settings_page'
    );
});

function tct_update_option_bool($key) {
    update_option($key, isset($_POST[$key]) ? 1 : 0);
}

function tct_sanitize_endpoint_slug($value) {
    $slug = sanitize_title((string) $value);
    return $slug !== '' ? $slug : 'llm';
}

function tct_sanitize_resource_path($value, $fallback) {
    $value = trim((string) $value);
    $path = parse_url($value, PHP_URL_PATH);
    if (
        !is_string($path)
        || $path === ''
        || $path !== $value
        || preg_match('~^/[A-Za-z0-9._\~!$&()*+,;=:@%/-]+$~D', $path) !== 1
    ) {
        return $fallback;
    }

    return '/' . ltrim($path, '/');
}

function tct_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    $error = '';
    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && isset($_POST['tct_action'])
    ) {
        check_admin_referer('tct_settings');
        $action = sanitize_key((string) wp_unslash($_POST['tct_action']));

        if ($action === 'save') {
            $endpoint = tct_sanitize_endpoint_slug(wp_unslash($_POST['tct_endpoint_slug'] ?? 'llm'));
            $sitemap_path = tct_sanitize_resource_path(
                wp_unslash($_POST['tct_sitemap_path'] ?? ''),
                '/llm-sitemap.json'
            );
            update_option('tct_endpoint_slug', $endpoint);
            update_option('tct_sitemap_path', $sitemap_path);

            $auth_mode = sanitize_key((string) wp_unslash($_POST['tct_auth_mode'] ?? 'off'));
            update_option('tct_auth_mode', $auth_mode === 'api_key' ? 'api_key' : 'off');

            // Environment keys are not persisted back into WordPress.
            $stored_hashes = get_option('tct_api_key_hashes', []);
            if (!is_array($stored_hashes)) {
                $stored_hashes = [];
            }
            if (isset($_POST['tct_clear_api_key_hashes'])) {
                $stored_hashes = [];
            }
            $new_key = (string) wp_unslash($_POST['tct_new_api_key'] ?? '');
            if ($new_key !== '') {
                if (strlen($new_key) < 16 || strlen($new_key) > 4096) {
                    $error = 'A new API key must contain between 16 and 4,096 bytes.';
                } else {
                    $stored_hashes[] = hash('sha256', $new_key);
                }
            }
            $stored_hashes = array_values(array_unique(array_filter(
                $stored_hashes,
                static fn($hash) => is_string($hash)
                    && preg_match('/^[0-9a-f]{64}$/D', $hash) === 1
            )));
            update_option('tct_api_key_hashes', $stored_hashes);
            unset($new_key);

            tct_update_option_bool('tct_include_headings');
            tct_update_option_bool('tct_stats_enabled');
            tct_update_option_bool('tct_changes_enabled');
            tct_update_option_bool('tct_receipts_enabled');
            update_option(
                'tct_terms_url',
                esc_url_raw((string) wp_unslash($_POST['tct_terms_url'] ?? ''))
            );
            update_option(
                'tct_pricing_url',
                esc_url_raw((string) wp_unslash($_POST['tct_pricing_url'] ?? ''))
            );

            tct_invalidate_protocol_generation();
            $notice = $error === '' ? 'TCT reference settings saved.' : '';
        }
    }

    $endpoint = (string) get_option('tct_endpoint_slug', 'llm');
    $sitemap_path = (string) get_option('tct_sitemap_path', '/llm-sitemap.json');
    $auth_mode = (string) get_option('tct_auth_mode', 'off');
    $stored_hashes = get_option('tct_api_key_hashes', []);
    $stored_key_count = is_array($stored_hashes) ? count($stored_hashes) : 0;
    $environment_keys = getenv('TCT_API_KEYS');
    $environment_key_source = is_string($environment_keys) && trim($environment_keys) !== '';
    unset($environment_keys);
    $receipt_key = tct_receipt_hmac_key();
    $receipt_key_available = $receipt_key !== '';
    unset($receipt_key);
    ?>
    <div class="wrap">
        <h1>Collaboration Content Transfer (TCT)</h1>
        <p>
            Non-stable WordPress reference implementation for the published
            Collaboration Content Transfer Internet-Draft revision 03.
        </p>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('tct_settings'); ?>
            <input type="hidden" name="tct_action" value="save">

            <h2>Core Draft-03 Surface</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tct_endpoint_slug">M-URL suffix</label></th>
                    <td>
                        <input id="tct_endpoint_slug" name="tct_endpoint_slug" type="text"
                            value="<?php echo esc_attr($endpoint); ?>" class="regular-text">
                        <p class="description">
                            Example: <code><?php echo esc_html(home_url('/article/' . $endpoint . '/')); ?></code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tct_sitemap_path">M-Sitemap path</label></th>
                    <td>
                        <input id="tct_sitemap_path" name="tct_sitemap_path" type="text"
                            value="<?php echo esc_attr($sitemap_path); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Optional envelope extensions</th>
                    <td>
                        <label>
                            <input type="checkbox" name="tct_include_headings"
                                <?php checked((int) get_option('tct_include_headings', 1), 1); ?>>
                            Include extracted heading metadata
                        </label>
                    </td>
                </tr>
            </table>

            <h2>Deployment Authentication</h2>
            <p>
                Authentication is a deployment concern, not part of core TCT. When enabled,
                the same gate protects M-URLs and the M-Sitemap.
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tct_auth_mode">Mode</label></th>
                    <td>
                        <select id="tct_auth_mode" name="tct_auth_mode">
                            <option value="off" <?php selected($auth_mode, 'off'); ?>>Public/off</option>
                            <option value="api_key" <?php selected($auth_mode, 'api_key'); ?>>API key</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tct_new_api_key">Add API key</label></th>
                    <td>
                        <input id="tct_new_api_key" name="tct_new_api_key" type="password"
                            value="" class="regular-text" autocomplete="new-password">
                        <p class="description">
                            Entered once and persisted only as SHA-256. Stored digests:
                            <?php echo esc_html((string) $stored_key_count); ?>.
                            Runtime <code>TCT_API_KEYS</code> source:
                            <?php echo $environment_key_source ? 'available' : 'not configured'; ?>.
                        </p>
                        <label>
                            <input type="checkbox" name="tct_clear_api_key_hashes">
                            Remove all stored key digests
                        </label>
                    </td>
                </tr>
            </table>

            <h2>Non-Core Experimental Extensions</h2>
            <p>
                These surfaces are not defined by Draft-03 and are disabled by default.
                Their data must not be interpreted as authorization or enforceable policy.
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Local telemetry</th>
                    <td>
                        <label><input type="checkbox" name="tct_stats_enabled"
                            <?php checked((int) get_option('tct_stats_enabled', 0), 1); ?>>
                            Enable bounded request statistics</label><br>
                        <label><input type="checkbox" name="tct_changes_enabled"
                            <?php checked((int) get_option('tct_changes_enabled', 0), 1); ?>>
                            Enable bounded write-path change records</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Usage receipt experiment</th>
                    <td>
                        <label><input type="checkbox" name="tct_receipts_enabled"
                            <?php checked((int) get_option('tct_receipts_enabled', 0), 1); ?>>
                            Emit experimental receipt headers</label>
                        <p class="description">
                            Runtime <code>TCT_RECEIPT_HMAC_KEY</code> source:
                            <?php echo $receipt_key_available ? 'available' : 'not configured'; ?>.
                            No receipt secret is accepted or displayed here.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Human policy links</th>
                    <td>
                        <input name="tct_terms_url" type="url" class="regular-text"
                            value="<?php echo esc_attr((string) get_option('tct_terms_url', '')); ?>"
                            placeholder="https://example.com/terms"><br>
                        <input name="tct_pricing_url" type="url" class="regular-text"
                            value="<?php echo esc_attr((string) get_option('tct_pricing_url', '')); ?>"
                            placeholder="https://example.com/pricing">
                    </td>
                </tr>
            </table>

            <?php submit_button('Save TCT Reference Settings'); ?>
        </form>

        <h2>Protocol Identities</h2>
        <p>
            M-URL profile:
            <code><?php echo esc_html(\TCT\Draft03\Protocol::M_URL_PROFILE); ?></code><br>
            M-Sitemap profile:
            <code><?php echo esc_html(\TCT\Draft03\Protocol::M_SITEMAP_PROFILE); ?></code>
        </p>
    </div>
    <?php
}
