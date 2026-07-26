<?php
/**
 * Admin controls for the versioned Draft-03 representation cache.
 */

if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', 'tct_add_cache_admin_menu');
add_action('admin_post_tct_clear_cache', 'tct_handle_clear_cache');

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

function tct_handle_clear_cache() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    check_admin_referer('tct_clear_cache');

    $epoch = tct_bump_cache_epoch();
    wp_safe_redirect(add_query_arg([
        'page' => 'tct-cache',
        'cache_cleared' => '1',
        'epoch' => $epoch,
    ], admin_url('options-general.php')));
    exit;
}

function tct_cache_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $epoch = tct_cache_epoch();
    $sitemap_key = tct_sitemap_cache_key();
    $sitemap_identity = tct_get_cached_sitemap_identity();
    ?>
    <div class="wrap">
        <h1>TCT Draft-03 Cache</h1>

        <?php if (isset($_GET['cache_cleared'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    Cache generation advanced to epoch
                    <strong><?php echo esc_html((string) $epoch); ?></strong>.
                    Historical internal transient rows are no longer addressable and will expire
                    naturally. The new generation will rebuild on demand. External caches were
                    not purged.
                </p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 800px;">
            <h2>Current Generation</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row">Plugin version</th>
                        <td><code><?php echo esc_html(TCT_VERSION); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">Cache namespace</th>
                        <td><code><?php echo esc_html(\TCT\Draft03\Protocol::CACHE_NAMESPACE); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">Epoch</th>
                        <td><code><?php echo esc_html((string) $epoch); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">M-Sitemap key</th>
                        <td><code><?php echo esc_html($sitemap_key); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">M-Sitemap status</th>
                        <td>
                            <?php echo $sitemap_identity
                                ? 'Certified and cached for the current epoch'
                                : 'Not cached; rebuilt on demand'; ?>
                        </td>
                    </tr>
                    <?php if ($sitemap_identity): ?>
                        <tr>
                            <th scope="row">M-Sitemap bytes</th>
                            <td><?php echo esc_html(number_format(strlen($sitemap_identity->body))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">M-Sitemap ETag</th>
                            <td><code><?php echo esc_html($sitemap_identity->etag); ?></code></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Invalidate TCT Representation Cache</h2>
            <p>
                This advances the internal epoch from
                <code><?php echo esc_html((string) $epoch); ?></code> to
                <code><?php echo esc_html((string) ($epoch + 1)); ?></code>. All current TCT post
                and sitemap cache keys become unreachable atomically, without scanning or
                deleting transient storage. Representations are rebuilt when they are next
                requested.
            </p>
            <div class="notice notice-warning inline">
                <p>
                    <strong>External caches are separate.</strong>
                    This action does not purge browser, LiteSpeed, reverse-proxy, or Cloudflare
                    caches. Purge those separately when required. The first request to the new
                    generation may be slower while representations rebuild.
                </p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tct_clear_cache'); ?>
                <input type="hidden" name="action" value="tct_clear_cache">
                <button
                    type="submit"
                    class="button button-primary"
                    onclick="return window.confirm('Invalidate the current TCT cache generation? The next TCT request may be slower while representations rebuild.');"
                >Invalidate TCT Cache Generation</button>
            </form>
        </div>
    </div>
    <?php
}
