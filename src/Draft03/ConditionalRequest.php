<?php

declare(strict_types=1);

namespace TCT\Draft03;

/**
 * RFC 9110 If-None-Match matching for an existing selected representation.
 */
final class ConditionalRequest
{
    public static function ifNoneMatchMatches(string $fieldValue, string $currentEtag): bool
    {
        $fieldValue = trim($fieldValue);
        if ($fieldValue === '*') {
            return true;
        }

        $currentOpaqueTag = self::opaqueTag($currentEtag);
        if ($currentOpaqueTag === null) {
            throw new SchemaException('Current ETag is malformed.');
        }

        foreach (self::parseEntityTagList($fieldValue) as $candidate) {
            if (self::opaqueTag($candidate) === $currentOpaqueTag) {
                return true;
            }
        }

        return false;
    }

    public static function isSafeReadMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD'], true);
    }

    /**
     * @return list<string>
     */
    private static function parseEntityTagList(string $value): array
    {
        $length = strlen($value);
        $position = 0;
        $tags = [];
        $expectTag = true;

        while ($position < $length) {
            while ($position < $length && ($value[$position] === ' ' || $value[$position] === "\t")) {
                ++$position;
            }

            if ($position >= $length) {
                return $expectTag ? [] : $tags;
            }

            if (!$expectTag) {
                if ($value[$position] !== ',') {
                    return [];
                }
                ++$position;
                $expectTag = true;
                continue;
            }

            $start = $position;
            if (
                $position + 1 < $length
                && $value[$position] === 'W'
                && $value[$position + 1] === '/'
            ) {
                $position += 2;
            }

            if ($position >= $length || $value[$position] !== '"') {
                return [];
            }
            ++$position;

            while ($position < $length && $value[$position] !== '"') {
                $code = ord($value[$position]);
                if ($code === 0x22 || $code < 0x21 || $code === 0x7f) {
                    return [];
                }
                ++$position;
            }

            if ($position >= $length) {
                return [];
            }
            ++$position;

            $tags[] = substr($value, $start, $position - $start);
            $expectTag = false;
        }

        return $expectTag ? [] : $tags;
    }

    private static function opaqueTag(string $etag): ?string
    {
        $etag = trim($etag);
        if (strncmp($etag, 'W/', 2) === 0) {
            $etag = substr($etag, 2);
        }

        if (strlen($etag) < 2 || $etag[0] !== '"' || $etag[strlen($etag) - 1] !== '"') {
            return null;
        }

        return substr($etag, 1, -1);
    }

    private function __construct()
    {
    }
}
