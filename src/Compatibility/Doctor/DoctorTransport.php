<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

interface DoctorTransport
{
    public function request(ProbeRequest $request): ProbeResponse|TransportFailure;
}
