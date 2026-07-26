<?php

declare(strict_types=1);

namespace TCT\Compatibility\WordPress;

use TCT\Compatibility\Doctor\BoundedText;
use TCT\Compatibility\Doctor\DoctorLimits;
use TCT\Compatibility\Doctor\DoctorReport;
use TCT\Compatibility\Doctor\ReportLimitException;
use TCT\Compatibility\Doctor\ReportSerializer;

final class WordPressReportSerializer implements ReportSerializer
{
    private const OPTIONS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR;

    public function __construct(private readonly DoctorLimits $limits = new DoctorLimits())
    {
    }

    public function serialize(DoctorReport $report): string
    {
        if (
            count($report->checks) > $this->limits->maxChecks
            || count($report->signals) > $this->limits->maxSignals
        ) {
            throw new ReportLimitException('Diagnostic collection count exceeds its ceiling.');
        }

        $value = $report->toArray();
        if ($this->textBytes($value) > $this->limits->maxDiagnosticTextBytes) {
            throw new ReportLimitException('Diagnostic text exceeds its byte ceiling.');
        }

        $json = wp_json_encode($value, self::OPTIONS);
        if (!is_string($json) || strlen($json) > $this->limits->maxDiagnosticBytes) {
            throw new ReportLimitException('Serialized diagnostic exceeds its byte ceiling.');
        }

        return $json;
    }

    public function minimalFailure(
        string $pluginVersion,
        string $routeSignature,
        string $failureCode
    ): string {
        $value = [
            'schema' => DoctorReport::SCHEMA,
            'doctor_version' => DoctorReport::IMPLEMENTATION_VERSION,
            'plugin_version' => BoundedText::utf8($pluginVersion, 128),
            'route_signature' => BoundedText::identifier($routeSignature),
            'overall' => 'inconclusive',
            'layers' => [
                'origin_implementation' => ['outcome' => 'not_tested'],
                'wordpress_cache_integration' => ['outcome' => 'not_tested'],
                'public_delivery_path' => ['outcome' => 'inconclusive'],
            ],
            'checks' => [[
                'id' => 'diagnostic_serialization',
                'outcome' => 'inconclusive',
                'mandatory' => true,
                'failure_code' => BoundedText::identifier($failureCode),
            ]],
            'signals' => [],
        ];

        $json = wp_json_encode($value, self::OPTIONS);
        if (!is_string($json) || strlen($json) > $this->limits->maxDiagnosticBytes) {
            throw new ReportLimitException('Minimal diagnostic cannot be serialized safely.');
        }

        return $json;
    }

    private function textBytes(mixed $value): int
    {
        if (is_string($value)) {
            return strlen($value);
        }
        if (!is_array($value)) {
            return 0;
        }

        $bytes = 0;
        foreach ($value as $key => $member) {
            if (is_string($key)) {
                $bytes += strlen($key);
            }
            $bytes += $this->textBytes($member);
            if ($bytes > $this->limits->maxDiagnosticTextBytes) {
                return $bytes;
            }
        }

        return $bytes;
    }
}
