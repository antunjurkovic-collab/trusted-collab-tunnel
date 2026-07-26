<?php

declare(strict_types=1);

namespace TCT\Compatibility\WordPress;

use Closure;

final class DoctorReportStore
{
    private const TTL_SECONDS = 3600;

    private Closure $setter;
    private Closure $getter;
    private Closure $random;

    public function __construct(
        ?Closure $setter = null,
        ?Closure $getter = null,
        ?Closure $random = null
    ) {
        $this->setter = $setter ?? static fn(string $key, string $json, int $ttl): bool =>
            set_transient($key, $json, $ttl);
        $this->getter = $getter ?? static fn(string $key): mixed => get_transient($key);
        $this->random = $random ?? static fn(): string => bin2hex(random_bytes(16));
    }

    public function save(int $userId, string $json, int $maximumBytes): string
    {
        if ($userId < 1 || strlen($json) > $maximumBytes) {
            throw new \InvalidArgumentException('Doctor report cannot be stored safely.');
        }

        $jobId = ($this->random)();
        if (preg_match('/^[0-9a-f]{32}$/D', $jobId) !== 1) {
            throw new \RuntimeException('Doctor job identifier is invalid.');
        }

        $stored = ($this->setter)(
            $this->key($userId, $jobId),
            $json,
            self::TTL_SECONDS
        );
        if ($stored !== true) {
            throw new \RuntimeException('Doctor report could not be stored.');
        }

        return $jobId;
    }

    public function load(int $userId, string $jobId, int $maximumBytes): ?string
    {
        if (
            $userId < 1
            || preg_match('/^[0-9a-f]{32}$/D', $jobId) !== 1
        ) {
            return null;
        }

        $value = ($this->getter)($this->key($userId, $jobId));

        return is_string($value) && strlen($value) <= $maximumBytes ? $value : null;
    }

    private function key(int $userId, string $jobId): string
    {
        return 'tct_doctor_' . $userId . '_' . $jobId;
    }
}
