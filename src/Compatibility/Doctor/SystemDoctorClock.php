<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class SystemDoctorClock implements DoctorClock
{
    public function monotonic(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    public function timestamp(): string
    {
        return gmdate('c');
    }
}
