<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class ProbeResponse
{
    /**
     * @var array<string, string>
     */
    private array $normalizedHeaders = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        array $headers,
        public readonly string $body,
        public readonly string $requestUrl,
        public readonly float $elapsedSeconds,
        public readonly bool $possiblyTruncated = false
    ) {
        if ($status < 100 || $status > 599 || $elapsedSeconds < 0) {
            throw new \InvalidArgumentException('Doctor probe response is invalid.');
        }

        foreach ($headers as $name => $value) {
            $normalizedName = strtolower(trim((string) $name));
            if (preg_match('/^[a-z0-9!#$%&\'*+.^_`|~-]+$/D', $normalizedName) !== 1) {
                continue;
            }
            $this->normalizedHeaders[$normalizedName] = BoundedText::utf8((string) $value, 16384);
        }
    }

    public function header(string $name): string
    {
        return $this->normalizedHeaders[strtolower($name)] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->normalizedHeaders;
    }
}
