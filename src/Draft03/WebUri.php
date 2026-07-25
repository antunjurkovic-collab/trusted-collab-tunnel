<?php

declare(strict_types=1);

namespace TCT\Draft03;

final class WebUri
{
    public static function requireAbsoluteHttp(string $value, string $member): void
    {
        $parts = parse_url($value);
        if (
            $parts === false
            || preg_match('/[^\x21-\x7e]/D', $value) === 1
            || filter_var($value, FILTER_VALIDATE_URL) === false
            || !isset($parts['scheme'], $parts['host'])
            || $parts['host'] === ''
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            throw new SchemaException(sprintf('%s must be an absolute HTTP(S) URI.', $member));
        }
    }

    private function __construct()
    {
    }
}
