<?php

declare(strict_types=1);

namespace TCT\Draft03;

final class AcceptEncoding
{
    public static function identityIsAllowed(?string $fieldValue): bool
    {
        if ($fieldValue === null || trim($fieldValue) === '') {
            return true;
        }

        $identityQuality = null;
        $wildcardQuality = null;

        foreach (explode(',', strtolower($fieldValue)) as $codingRange) {
            $parts = array_map('trim', explode(';', $codingRange));
            $coding = array_shift($parts);
            $quality = 1.0;

            foreach ($parts as $parameter) {
                if (preg_match('/^q=(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/D', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            if ($coding === 'identity') {
                $identityQuality = $quality;
            } elseif ($coding === '*') {
                $wildcardQuality = $quality;
            }
        }

        if ($identityQuality !== null) {
            return $identityQuality > 0.0;
        }

        return $wildcardQuality !== 0.0;
    }

    private function __construct()
    {
    }
}
