<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

enum Outcome: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case NotTested = 'not_tested';
    case Inconclusive = 'inconclusive';
    case Stale = 'stale';
}
