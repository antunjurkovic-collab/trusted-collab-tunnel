<?php

declare(strict_types=1);

namespace TCT\Compatibility\WordPress;

use TCT\Compatibility\Doctor\DeploymentDoctor;
use TCT\Compatibility\Doctor\DoctorContext;
use TCT\Compatibility\Doctor\DoctorLimits;
use TCT\Compatibility\Doctor\ReportLimitException;

final class CompatibilityAdminController
{
    public function __construct(
        private readonly DoctorLimits $limits = new DoctorLimits(),
        private readonly DoctorReportStore $store = new DoctorReportStore(),
        private readonly WordPressReportSerializer $serializer = new WordPressReportSerializer()
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_tct_run_deployment_doctor', [$this, 'run']);
        add_action('admin_post_tct_export_deployment_doctor', [$this, 'export']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'options-general.php',
            'TCT Compatibility',
            'TCT Compatibility',
            'manage_options',
            'tct-compatibility',
            [$this, 'render']
        );
    }

    public function run(): never
    {
        $this->requireAdministrator();
        check_admin_referer('tct_run_deployment_doctor');

        $routeSignature = $this->routeSignature();
        try {
            $context = $this->context($routeSignature);
            $report = (new DeploymentDoctor($this->limits))->run(
                $context,
                new WordPressHttpTransport()
            );
            $json = $this->serializer->serialize($report);
        } catch (ReportLimitException) {
            $json = $this->serializer->minimalFailure(
                TCT_VERSION,
                $routeSignature,
                'diagnostic_bytes'
            );
        } catch (\Throwable) {
            $json = $this->serializer->minimalFailure(
                TCT_VERSION,
                $routeSignature,
                'transport_error'
            );
        }

        try {
            $jobId = $this->store->save(
                get_current_user_id(),
                $json,
                $this->limits->maxDiagnosticBytes
            );
        } catch (\Throwable) {
            wp_die(
                esc_html('The bounded Deployment Doctor report could not be stored.'),
                esc_html('TCT Compatibility'),
                ['response' => 500]
            );
        }

        wp_safe_redirect(add_query_arg(
            ['page' => 'tct-compatibility', 'doctor_job' => $jobId],
            admin_url('options-general.php')
        ));
        exit;
    }

    public function export(): never
    {
        $this->requireAdministrator();
        $jobId = $this->requestedJobId();
        check_admin_referer('tct_export_deployment_doctor_' . $jobId);

        $json = $this->store->load(
            get_current_user_id(),
            $jobId,
            $this->limits->maxDiagnosticBytes
        );
        if ($json === null) {
            wp_die(
                esc_html('The Deployment Doctor report is unavailable or expired.'),
                esc_html('TCT Compatibility'),
                ['response' => 404]
            );
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8', true);
        header(
            'Content-Disposition: attachment; filename="tct-deployment-doctor-report.json"',
            true
        );
        header('Cache-Control: private, no-store, max-age=0', true);
        header('Content-Length: ' . strlen($json), true);
        echo $json;
        exit;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $jobId = $this->requestedJobId();
        $json = $jobId === '' ? null : $this->store->load(
            get_current_user_id(),
            $jobId,
            $this->limits->maxDiagnosticBytes
        );
        $report = $this->decodeReport($json);
        $stale = is_array($report) && (
            ($report['plugin_version'] ?? '') !== TCT_VERSION
            || ($report['route_signature'] ?? '') !== $this->routeSignature()
        );

        $sitemapPath = $this->sitemapPath();
        $endpoint = sanitize_title((string) get_option('tct_endpoint_slug', 'llm'));
        $endpoint = $endpoint !== '' ? $endpoint : 'llm';
        $epoch = tct_cache_epoch();
        $sitemapKey = tct_sitemap_cache_key();
        $rawSitemapCache = get_transient($sitemapKey);
        $cachePresent = is_array($rawSitemapCache)
            && isset($rawSitemapCache['body'], $rawSitemapCache['etag'])
            && is_string($rawSitemapCache['body'])
            && is_string($rawSitemapCache['etag']);
        unset($rawSitemapCache);
        ?>
        <div class="wrap">
            <h1>TCT Compatibility</h1>
            <p>
                Read-only, bounded diagnostics for the public TCT Draft-03 delivery path.
                This checkpoint does not configure or purge WordPress, server, proxy, or
                CDN caches.
            </p>

            <div class="card" style="max-width: 1000px;">
                <h2>Current Diagnostic Boundary</h2>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <th scope="row">Compatibility mode</th>
                            <td>Diagnostics only; no cache adapter is active</td>
                        </tr>
                        <tr>
                            <th scope="row">M-Sitemap</th>
                            <td><code><?php echo esc_html(home_url($sitemapPath)); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">M-URL route forms</th>
                            <td>
                                <code><?php echo esc_html('/{canonical}/' . $endpoint . '/'); ?></code>
                                and <code><?php echo esc_html('?tct_m_url=1'); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Internal representation cache</th>
                            <td>
                                Namespace <code><?php echo esc_html(\TCT\Draft03\Protocol::CACHE_NAMESPACE); ?></code>,
                                epoch <code><?php echo esc_html((string) $epoch); ?></code>,
                                sitemap row <?php echo $cachePresent ? 'present' : 'not present'; ?>
                                (<code><?php echo esc_html($sitemapKey); ?></code>)
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Public delivery evidence</th>
                            <td>Separate; run the Doctor below</td>
                        </tr>
                    </tbody>
                </table>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="margin-top: 16px;">
                    <?php wp_nonce_field('tct_run_deployment_doctor'); ?>
                    <input type="hidden" name="action" value="tct_run_deployment_doctor">
                    <button type="submit" class="button button-primary">
                        Run Deployment Doctor
                    </button>
                </form>
                <p class="description">
                    The run is explicit and sequential, with at most
                    <?php echo esc_html((string) $this->limits->maxRequests); ?> public requests,
                    <?php echo esc_html((string) $this->limits->maxTotalSeconds); ?> seconds, and
                    <?php echo esc_html(size_format($this->limits->maxTotalResponseBytes)); ?>
                    of response bodies.
                </p>
            </div>

            <?php if ($jobId !== '' && $report === null): ?>
                <div class="notice notice-warning"><p>
                    The requested report is unavailable, invalid, or expired.
                </p></div>
            <?php endif; ?>

            <?php if ($report !== null): ?>
                <?php $this->renderReport($report, $jobId, $stale); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderReport(array $report, string $jobId, bool $stale): void
    {
        $layers = is_array($report['layers'] ?? null) ? $report['layers'] : [];
        $checks = is_array($report['checks'] ?? null) ? $report['checks'] : [];
        $signals = is_array($report['signals'] ?? null) ? $report['signals'] : [];
        ?>
        <div class="card" style="max-width: 1000px; margin-top: 20px;">
            <h2>Deployment Doctor Result</h2>
            <?php if ($stale): ?>
                <div class="notice notice-warning inline"><p>
                    <strong>STALE:</strong> the plugin version or route configuration changed
                    after this report. Rerun before relying on it.
                </p></div>
            <?php endif; ?>
            <p>
                Started <code><?php echo esc_html((string) ($report['started_at'] ?? 'unknown')); ?></code>;
                completed <code><?php echo esc_html((string) ($report['completed_at'] ?? 'unknown')); ?></code>.
                Overall public path:
                <strong><?php echo esc_html(strtoupper($stale ? 'stale' : (string) ($report['overall'] ?? 'inconclusive'))); ?></strong>
            </p>

            <h3>Independent Layers</h3>
            <table class="widefat striped">
                <thead><tr><th>Layer</th><th>Outcome</th><th>Detail</th></tr></thead>
                <tbody>
                <?php foreach ([
                    'origin_implementation',
                    'wordpress_cache_integration',
                    'public_delivery_path',
                ] as $layerId): ?>
                    <?php $layer = is_array($layers[$layerId] ?? null) ? $layers[$layerId] : []; ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($layerId); ?></th>
                        <td><strong><?php echo esc_html(strtoupper((string) ($layer['outcome'] ?? 'not_tested'))); ?></strong></td>
                        <td><?php echo esc_html((string) ($layer['detail'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Checks</h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Check</th><th>Outcome</th><th>Expected</th>
                        <th>Observed</th><th>Attribution</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <?php if (!is_array($check)) { continue; } ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html((string) ($check['id'] ?? 'unknown')); ?></code>
                            <?php if (($check['failure_code'] ?? '') !== ''): ?>
                                <br><small><?php echo esc_html((string) $check['failure_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html(strtoupper((string) ($check['outcome'] ?? 'inconclusive'))); ?></strong></td>
                        <td><?php echo esc_html((string) ($check['expected'] ?? '')); ?></td>
                        <td>
                            <?php echo esc_html((string) ($check['observed'] ?? '')); ?>
                            <?php if (($check['uri'] ?? '') !== ''): ?>
                                <br><code><?php echo esc_html((string) $check['uri']); ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html((string) ($check['likely_layer'] ?? '')); ?>
                            (<?php echo esc_html((string) ($check['attribution'] ?? 'inference')); ?>)
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Diagnostic-Only Signals</h3>
            <?php if ($signals === []): ?>
                <p>No allowlisted provider or delivery signals were observed.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($signals as $signal): ?>
                        <?php if (!is_array($signal)) { continue; } ?>
                        <li>
                            <code><?php echo esc_html((string) ($signal['id'] ?? 'signal')); ?></code>:
                            <?php echo esc_html((string) ($signal['value'] ?? 'detected')); ?>
                            — diagnostic only
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>External Rerun</h3>
            <p>
                If this host cannot loop back to its own public name, run the equivalent
                validator externally:
            </p>
            <p><code><?php echo esc_html((string) ($report['external_validator_command'] ?? '')); ?></code></p>

            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                    'action' => 'tct_export_deployment_doctor',
                    'doctor_job' => $jobId,
                ], admin_url('admin-post.php')), 'tct_export_deployment_doctor_' . $jobId)); ?>">
                    Export Secret-Free JSON Evidence
                </a>
            </p>
        </div>
        <?php
    }

    private function requireAdministrator(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html('Unauthorized'),
                esc_html('TCT Compatibility'),
                ['response' => 403]
            );
        }
    }

    private function requestedJobId(): string
    {
        $value = isset($_GET['doctor_job'])
            ? (string) wp_unslash($_GET['doctor_job'])
            : '';

        return preg_match('/^[0-9a-f]{32}$/D', $value) === 1 ? $value : '';
    }

    private function context(string $routeSignature): DoctorContext
    {
        $rootUrl = home_url('/');
        $sitemapPath = $this->sitemapPath();
        $baseUrl = rtrim($rootUrl, '/');
        $escapedBaseUrl = str_replace("'", "''", $baseUrl);
        $escapedSitemapPath = str_replace("'", "''", $sitemapPath);
        $command = "& ./scripts/validate-live.ps1 -BaseUrl '"
            . $escapedBaseUrl . "' -SitemapPath '" . $escapedSitemapPath
            . "' -SampleMurls " . $this->limits->maxSampledMurls;

        return new DoctorContext(
            $rootUrl,
            home_url($sitemapPath),
            TCT_VERSION,
            $routeSignature,
            $command,
            (string) get_option('tct_auth_mode', 'off') === 'off',
            ProviderSignals::collect()
        );
    }

    private function routeSignature(): string
    {
        $parts = [
            TCT_VERSION,
            home_url('/'),
            $this->sitemapPath(),
            sanitize_title((string) get_option('tct_endpoint_slug', 'llm')),
            (string) get_option('permalink_structure', ''),
        ];

        return hash('sha256', implode("\n", $parts));
    }

    private function sitemapPath(): string
    {
        $value = trim((string) get_option('tct_sitemap_path', '/llm-sitemap.json'));
        $path = parse_url($value, PHP_URL_PATH);
        if (
            !is_string($path)
            || $path === ''
            || $path !== $value
            || preg_match('~^/[A-Za-z0-9._\~!$&()*+,;=:@%/-]+$~D', $path) !== 1
        ) {
            return '/llm-sitemap.json';
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeReport(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        try {
            $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($value) && ($value['schema'] ?? '') === \TCT\Compatibility\Doctor\DoctorReport::SCHEMA
            ? $value
            : null;
    }
}
