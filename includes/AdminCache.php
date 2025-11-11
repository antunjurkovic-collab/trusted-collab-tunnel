<?php
/**
 * Admin Cache Management Utilities
 * Adds "Clear TCT Cache" button to WordPress admin
 */

if (!defined('ABSPATH')) { exit; }

// Add admin menu item
add_action('admin_menu', 'tct_add_cache_admin_menu');

function tct_add_cache_admin_menu() {
    add_submenu_page(
        'options-general.php',
        'TCT Cache',
        'TCT Cache',
        'manage_options',
        'tct-cache',
        'tct_cache_admin_page'
    );
}

// Handle cache clearing
add_action('admin_post_tct_clear_cache', 'tct_handle_clear_cache');

function tct_handle_clear_cache() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('tct_clear_cache');

    // Clear all TCT caches
    delete_transient('tct_sitemap_cache_v2');
    delete_transient('tct_sitemap_cache_v3');
    delete_transient('tct_sitemap_recent_cache_v2');

    // Get cache info before clearing
    $cleared = [];
    if (get_transient('tct_sitemap_cache_v2') === false) {
        $cleared[] = 'sitemap_v2';
    }
    if (get_transient('tct_sitemap_cache_v3') === false) {
        $cleared[] = 'sitemap_v3';
    }

    // Redirect back with success message
    wp_redirect(add_query_arg([
        'page' => 'tct-cache',
        'cache_cleared' => '1',
        'cleared_count' => count($cleared)
    ], admin_url('options-general.php')));
    exit;
}

// Admin page HTML
function tct_cache_admin_page() {
    ?>
    <div class="wrap">
        <h1>TCT Cache Management</h1>

        <?php if (isset($_GET['cache_cleared'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Success!</strong> TCT cache has been cleared. Next sitemap request will regenerate with fresh hashes.</p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 800px;">
            <h2>Cache Status</h2>

            <?php
            $v2_exists = get_transient('tct_sitemap_cache_v2');
            $v3_exists = get_transient('tct_sitemap_cache_v3');
            $recent_exists = get_transient('tct_sitemap_recent_cache_v2');

            $v2_timeout = get_option('_transient_timeout_tct_sitemap_cache_v2');
            $v3_timeout = get_option('_transient_timeout_tct_sitemap_cache_v3');
            ?>

            <table class="widefat" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Cache Key</th>
                        <th>Status</th>
                        <th>Size</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>tct_sitemap_cache_v2</code> (old)</td>
                        <td>
                            <?php if ($v2_exists !== false): ?>
                                <span style="color: #d63638;">● Active</span>
                            <?php else: ?>
                                <span style="color: #00a32a;">○ Cleared</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($v2_exists !== false): ?>
                                <?php echo number_format(strlen($v2_exists) / 1024, 1); ?> KB
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($v2_timeout && $v2_exists !== false): ?>
                                <?php
                                $remaining = $v2_timeout - time();
                                if ($remaining > 0) {
                                    echo floor($remaining / 60) . 'm ' . ($remaining % 60) . 's';
                                } else {
                                    echo 'Expired';
                                }
                                ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><code>tct_sitemap_cache_v3</code> (current)</td>
                        <td>
                            <?php if ($v3_exists !== false): ?>
                                <span style="color: #00a32a;">● Active</span>
                            <?php else: ?>
                                <span style="color: #d63638;">○ Empty</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($v3_exists !== false): ?>
                                <?php echo number_format(strlen($v3_exists) / 1024, 1); ?> KB
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($v3_timeout && $v3_exists !== false): ?>
                                <?php
                                $remaining = $v3_timeout - time();
                                if ($remaining > 0) {
                                    echo floor($remaining / 60) . 'm ' . ($remaining % 60) . 's';
                                } else {
                                    echo 'Expired';
                                }
                                ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><code>tct_sitemap_recent_cache_v2</code></td>
                        <td>
                            <?php if ($recent_exists !== false): ?>
                                <span style="color: #00a32a;">● Active</span>
                            <?php else: ?>
                                <span style="color: #999;">○ Empty</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($recent_exists !== false): ?>
                                <?php echo number_format(strlen($recent_exists) / 1024, 1); ?> KB
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>-</td>
                    </tr>
                </tbody>
            </table>

            <?php if ($v2_exists !== false): ?>
                <div class="notice notice-warning inline" style="margin-top: 20px;">
                    <p><strong>Warning:</strong> Old cache (v2) is still active. This may cause ETag parity mismatches.</p>
                    <p>Click "Clear All Caches" below to force regeneration with the latest unified hashing.</p>
                </div>
            <?php endif; ?>

            <?php if ($v3_exists !== false): ?>
                <?php
                // Parse cached sitemap to show stats
                $sitemap_data = json_decode($v3_exists, true);
                if ($sitemap_data && isset($sitemap_data['items'])):
                ?>
                <div style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-left: 4px solid #00a32a;">
                    <strong>Cached Sitemap Info:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li>Total items: <?php echo count($sitemap_data['items']); ?></li>
                        <li>Version: <?php echo $sitemap_data['version'] ?? 'N/A'; ?></li>
                        <li>Profile: <?php echo $sitemap_data['profile'] ?? 'N/A'; ?></li>
                        <li>First item: <?php echo $sitemap_data['items'][0]['cUrl'] ?? 'N/A'; ?></li>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Actions</h2>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('tct_clear_cache'); ?>
                <input type="hidden" name="action" value="tct_clear_cache">

                <p>
                    <button type="submit" class="button button-primary button-large">
                        🗑️ Clear All TCT Caches
                    </button>
                </p>

                <p class="description">
                    This will clear all TCT-related caches. The next request to <code>/llm-sitemap.json</code>
                    will regenerate the sitemap with fresh hashes using the unified hashing pipeline.
                </p>
            </form>

            <hr style="margin: 20px 0;">

            <h3>Why Clear Cache?</h3>
            <ul style="line-height: 1.8;">
                <li><strong>After plugin updates</strong> - Ensures new code is used</li>
                <li><strong>ETag parity mismatches</strong> - Forces hash regeneration</li>
                <li><strong>Testing changes</strong> - See immediate results</li>
                <li><strong>Manual refresh</strong> - When auto-invalidation doesn't trigger</li>
            </ul>

            <p class="description">
                <strong>Note:</strong> Cache automatically clears when you save/update/delete posts.
                Default cache duration: 60 minutes.
            </p>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Quick Links</h2>
            <ul style="line-height: 2;">
                <li>
                    <a href="<?php echo home_url('/llm-sitemap.json'); ?>" target="_blank" class="button button-secondary">
                        📄 View Sitemap
                    </a>
                    <span class="description"> - Opens in new tab</span>
                </li>
                <li>
                    <a href="<?php echo home_url('/llm/'); ?>" target="_blank" class="button button-secondary">
                        🏠 View Homepage M-URL
                    </a>
                    <span class="description"> - Opens in new tab</span>
                </li>
                <li>
                    <a href="<?php echo admin_url('options-general.php?page=tct-settings'); ?>" class="button button-secondary">
                        ⚙️ TCT Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <style>
        .card h2 {
            margin-top: 0;
        }
        .widefat th {
            font-weight: 600;
        }
    </style>
    <?php
}
