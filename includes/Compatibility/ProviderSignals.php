<?php

declare(strict_types=1);

namespace TCT\Compatibility\WordPress;

use TCT\Compatibility\Doctor\ProviderSignal;

final class ProviderSignals
{
    /**
     * @return list<ProviderSignal>
     */
    public static function collect(): array
    {
        $signals = [];
        $plugins = get_option('active_plugins', []);
        if (!is_array($plugins)) {
            $plugins = [];
        }
        if (function_exists('is_multisite') && is_multisite()) {
            $networkPlugins = get_site_option('active_sitewide_plugins', []);
            if (is_array($networkPlugins)) {
                $plugins = array_merge($plugins, array_keys($networkPlugins));
            }
        }

        $knownPlugins = [
            'litespeed-cache/litespeed-cache.php' => 'wordpress_litespeed_cache',
            'wp-rocket/wp-rocket.php' => 'wordpress_wp_rocket',
            'w3-total-cache/w3-total-cache.php' => 'wordpress_w3_total_cache',
            'wp-super-cache/wp-cache.php' => 'wordpress_wp_super_cache',
            'cloudflare/cloudflare.php' => 'wordpress_cloudflare',
        ];
        foreach ($knownPlugins as $plugin => $id) {
            if (in_array($plugin, $plugins, true)) {
                $signals[] = new ProviderSignal($id, 'detected', 'wordpress_plugin');
            }
        }

        $presence = [
            'constant_lscwp' => defined('LSCWP_V'),
            'constant_wp_rocket' => defined('WP_ROCKET_VERSION'),
            'constant_w3tc' => defined('W3TC'),
            'constant_wp_super_cache' => defined('WPCACHEHOME'),
        ];
        foreach ($presence as $id => $detected) {
            if ($detected) {
                $signals[] = new ProviderSignal($id, 'present', 'wordpress_runtime');
            }
        }

        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (is_string($serverSoftware) && $serverSoftware !== '') {
            $signals[] = new ProviderSignal(
                'server_software',
                $serverSoftware,
                'wordpress_server'
            );
        }

        usort(
            $signals,
            static fn(ProviderSignal $left, ProviderSignal $right): int =>
                [$left->id, $left->value] <=> [$right->id, $right->value]
        );

        return array_slice($signals, 0, 32);
    }

    private function __construct()
    {
    }
}
