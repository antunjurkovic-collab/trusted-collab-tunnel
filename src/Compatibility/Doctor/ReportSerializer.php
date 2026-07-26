<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

interface ReportSerializer
{
    public function serialize(DoctorReport $report): string;

    public function minimalFailure(
        string $pluginVersion,
        string $routeSignature,
        string $failureCode
    ): string;
}
