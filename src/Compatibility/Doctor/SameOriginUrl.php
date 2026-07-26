<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class SameOriginUrl
{
    public static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        return $scheme . '://' . $host . ':' . $port;
    }

    public static function isSame(string $left, string $right): bool
    {
        $leftOrigin = self::origin($left);

        return $leftOrigin !== null && hash_equals($leftOrigin, (string) self::origin($right));
    }

    public static function resolve(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if (
            $location === ''
            || strlen($location) > 2048
            || preg_match('/[\x00-\x20\x7f]/D', $location) === 1
        ) {
            return null;
        }

        $locationParts = parse_url($location);
        if ($locationParts === false || isset($locationParts['fragment'])) {
            return null;
        }

        if (isset($locationParts['scheme'])) {
            return self::origin($location) !== null ? $location : null;
        }

        $base = parse_url($baseUrl);
        if ($base === false || !isset($base['scheme'], $base['host'])) {
            return null;
        }

        $authority = strtolower((string) $base['scheme']) . '://' . (string) $base['host'];
        if (isset($base['port'])) {
            $authority .= ':' . (int) $base['port'];
        }

        if (str_starts_with($location, '//')) {
            $absolute = strtolower((string) $base['scheme']) . ':' . $location;
            return self::origin($absolute) !== null ? $absolute : null;
        }

        $query = '';
        $path = $location;
        $queryPosition = strpos($location, '?');
        if ($queryPosition !== false) {
            $path = substr($location, 0, $queryPosition);
            $query = substr($location, $queryPosition);
        }

        if ($path === '') {
            $path = (string) ($base['path'] ?? '/');
        } elseif (!str_starts_with($path, '/')) {
            $basePath = (string) ($base['path'] ?? '/');
            $path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $path;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalizedPath = '/' . implode('/', $segments);
        if (str_ends_with($path, '/') && $normalizedPath !== '/') {
            $normalizedPath .= '/';
        }

        $absolute = $authority . $normalizedPath . $query;

        return self::origin($absolute) !== null ? $absolute : null;
    }

    private function __construct()
    {
    }
}
