<?php

declare(strict_types=1);

use TCT\Compatibility\Doctor\BoundedGzipDecoder;
use TCT\Compatibility\Doctor\DeploymentDoctor;
use TCT\Compatibility\Doctor\DoctorCheck;
use TCT\Compatibility\Doctor\DoctorContext;
use TCT\Compatibility\Doctor\DoctorLimits;
use TCT\Compatibility\Doctor\DoctorReport;
use TCT\Compatibility\Doctor\DoctorTransport;
use TCT\Compatibility\Doctor\LayerResult;
use TCT\Compatibility\Doctor\Outcome;
use TCT\Compatibility\Doctor\ProbeRequest;
use TCT\Compatibility\Doctor\ProbeResponse;
use TCT\Compatibility\Doctor\TransportFailure;
use TCT\Compatibility\WordPress\WordPressReportSerializer;
use TCT\Draft03\IdentityRepresentation;
use TCT\Draft03\Protocol;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

final class SubprocessDoctorTransport implements DoctorTransport
{
    private int $calls = 0;

    public function __construct(private readonly string $mode)
    {
    }

    public function request(ProbeRequest $request): ProbeResponse|TransportFailure
    {
        ++$this->calls;
        if ($this->calls === 1) {
            $bytes = match ($this->mode) {
                'raw_at' => 16 * DoctorLimits::MIB,
                'raw_over' => 16 * DoctorLimits::MIB + 1,
                default => 0,
            };

            return new ProbeResponse(
                200,
                [
                    'Link' => '<https://example.com/llm-sitemap.json>; rel="index"; type="application/json"',
                ],
                str_repeat('x', $bytes),
                $request->url,
                0.01,
                $bytes > $request->maximumBodyBytes
            );
        }

        if ($this->calls === 2 && $this->mode === 'deep_json') {
            $body = '{"items":[],"profile":'
                . json_encode(Protocol::M_SITEMAP_PROFILE, JSON_THROW_ON_ERROR)
                . ',"version":2,"x":' . str_repeat('[', 1000) . '0'
                . str_repeat(']', 1000) . '}';
            $identity = IdentityRepresentation::fromBody($body);

            return new ProbeResponse(
                200,
                [
                    'Content-Type' => 'application/json',
                    'ETag' => $identity->etag,
                    'Content-Digest' => $identity->contentDigest,
                    'Content-Length' => (string) strlen($body),
                    'Cache-Control' => 'public, no-transform',
                    'Vary' => 'Accept-Encoding',
                    'Link' => '<' . Protocol::M_SITEMAP_PROFILE . '>; rel="profile"',
                ],
                $body,
                $request->url,
                0.01
            );
        }

        return new TransportFailure('loopback_unavailable', $request->url);
    }
}

$mode = $argv[1] ?? '';

if ($mode === 'gzip_bomb') {
    $expanded = str_repeat('x', 2 * DoctorLimits::MIB);
    $coded = gzencode($expanded, 9);
    unset($expanded);
    if (!is_string($coded)) {
        throw new RuntimeException('Unable to construct gzip subprocess fixture.');
    }

    $result = (new BoundedGzipDecoder())->decode($coded, DoctorLimits::MIB);
    echo json_encode([
        'ok' => $result['ok'] === false,
        'code' => $result['code'],
    ], JSON_THROW_ON_ERROR);
    exit($result['ok'] === false ? 0 : 1);
}

if (in_array($mode, ['raw_at', 'raw_over', 'deep_json'], true)) {
    $transport = new SubprocessDoctorTransport($mode);
    $context = new DoctorContext(
        'https://example.com/',
        'https://example.com/llm-sitemap.json',
        '3.0.0-alpha.3',
        str_repeat('a', 64),
        'external validator'
    );
    $report = (new DeploymentDoctor())->run($context, $transport);
    $checks = [];
    foreach ($report->toArray()['checks'] as $check) {
        $checks[$check['id']] = $check;
    }

    $expected = match ($mode) {
        'raw_at' => ($checks['root_discovery']['outcome'] ?? '') === 'pass'
            && $report->toArray()['counters']['response_bytes'] === 16 * DoctorLimits::MIB,
        'raw_over' => ($checks['root_discovery']['failure_code'] ?? '') === 'response_bytes',
        'deep_json' => ($checks['sitemap_profile_structure']['failure_code'] ?? '') === 'json_depth',
    };

    echo json_encode([
        'ok' => $expected,
        'mode' => $mode,
        'overall' => $report->publicOutcome()->value,
    ], JSON_THROW_ON_ERROR);
    exit($expected ? 0 : 1);
}

if ($mode === 'diagnostic') {
    $limits = new DoctorLimits();
    $escaped = str_repeat("\x01", 1000);
    $checks = [];
    for ($index = 0; $index < 30; ++$index) {
        $checks[] = new DoctorCheck(
            'escaped_' . $index,
            Outcome::Fail,
            'https://example.com/',
            $escaped,
            $escaped
        );
    }
    $report = new DoctorReport(
        '3.0.0-alpha.3',
        str_repeat('a', 64),
        'https://example.com:443',
        'https://example.com/llm-sitemap.json',
        '2026-07-26T12:00:00+00:00',
        '2026-07-26T12:00:01+00:00',
        $limits,
        ['requests' => 0, 'redirects' => 0, 'response_bytes' => 0, 'sampled_murls' => 0],
        [
            'origin_implementation' => new LayerResult(Outcome::NotTested, 'not tested'),
            'wordpress_cache_integration' => new LayerResult(Outcome::NotTested, 'not tested'),
            'public_delivery_path' => new LayerResult(Outcome::Fail, 'failed'),
        ],
        $checks,
        [],
        'external validator'
    );
    $json = (new WordPressReportSerializer($limits))->serialize($report);
    $expected = strlen($json) < $limits->maxDiagnosticBytes;
    echo json_encode([
        'ok' => $expected,
        'bytes' => strlen($json),
    ], JSON_THROW_ON_ERROR);
    exit($expected ? 0 : 1);
}

throw new InvalidArgumentException('Unknown subprocess probe mode.');
