<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

interface DoctorClock
{
    public function monotonic(): float;

    public function timestamp(): string;
}
