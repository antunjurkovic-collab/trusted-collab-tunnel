<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class ProbeRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly array $headers,
        public readonly float $timeoutSeconds,
        public readonly int $maximumBodyBytes
    ) {
        if (
            SameOriginUrl::origin($url) === null
            || preg_match('/^[A-Z]+$/D', $method) !== 1
            || $timeoutSeconds <= 0
            || $maximumBodyBytes < 1
        ) {
            throw new \InvalidArgumentException('Doctor probe request is invalid.');
        }
    }
}
