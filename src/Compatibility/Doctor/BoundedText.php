<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class BoundedText
{
    public static function utf8(string $value, int $maximumBytes): string
    {
        if ($maximumBytes < 1) {
            return '';
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        if (strlen($value) <= $maximumBytes) {
            return $value;
        }

        $marker = '...[truncated]';
        $available = max(0, $maximumBytes - strlen($marker));
        $prefix = mb_strcut($value, 0, $available, 'UTF-8');

        return $prefix . $marker;
    }

    public static function identifier(string $value): string
    {
        $value = preg_replace('/[^a-z0-9_.-]+/i', '_', $value) ?? '';

        return substr($value, 0, 64);
    }

    private function __construct()
    {
    }
}
