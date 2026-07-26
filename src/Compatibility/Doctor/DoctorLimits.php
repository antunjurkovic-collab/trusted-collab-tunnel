<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class DoctorLimits
{
    public const MIB = 1048576;

    public function __construct(
        public readonly int $maxRequests = 20,
        public readonly int $maxConcurrentRequests = 2,
        public readonly int $maxSampledMurls = 3,
        public readonly int $maxRedirectsPerProbe = 3,
        public readonly int $maxResponseBytes = 16 * self::MIB,
        public readonly int $maxTotalResponseBytes = 32 * self::MIB,
        public readonly float $maxRequestSeconds = 10.0,
        public readonly float $maxTotalSeconds = 60.0,
        public readonly int $maxDiagnosticBytes = 2 * self::MIB,
        public readonly int $maxDiagnosticTextBytes = 131072,
        public readonly int $maxChecks = 64,
        public readonly int $maxSignals = 32
    ) {
        if (
            $maxRequests < 1
            || $maxConcurrentRequests < 1
            || $maxSampledMurls < 0
            || $maxRedirectsPerProbe < 0
            || $maxResponseBytes < 1
            || $maxTotalResponseBytes < $maxResponseBytes
            || $maxRequestSeconds <= 0
            || $maxTotalSeconds <= 0
            || $maxDiagnosticBytes < 1024
            || $maxDiagnosticTextBytes < 1024
            || $maxChecks < 1
            || $maxSignals < 0
        ) {
            throw new \InvalidArgumentException('Deployment Doctor limits are invalid.');
        }
    }

    /**
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'requests' => $this->maxRequests,
            'concurrent_requests' => $this->maxConcurrentRequests,
            'sampled_murls' => $this->maxSampledMurls,
            'redirects_per_probe' => $this->maxRedirectsPerProbe,
            'response_bytes' => $this->maxResponseBytes,
            'total_response_bytes' => $this->maxTotalResponseBytes,
            'request_seconds' => $this->maxRequestSeconds,
            'total_seconds' => $this->maxTotalSeconds,
            'diagnostic_bytes' => $this->maxDiagnosticBytes,
            'diagnostic_text_bytes' => $this->maxDiagnosticTextBytes,
            'checks' => $this->maxChecks,
            'signals' => $this->maxSignals,
        ];
    }
}
