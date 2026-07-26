<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class TransportFailure
{
    public function __construct(
        public readonly string $code,
        public readonly string $requestUrl,
        public readonly float $elapsedSeconds = 0.0,
        public readonly string $detail = ''
    ) {
    }
}
