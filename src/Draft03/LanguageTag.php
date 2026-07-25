<?php

declare(strict_types=1);

namespace TCT\Draft03;

final class LanguageTag
{
    public static function isWellFormed(string $value): bool
    {
        $grandfathered = '(?:art-lojban|cel-gaulish|en-gb-oed|i-ami|i-bnn|i-default|'
            . 'i-enochian|i-hak|i-klingon|i-lux|i-mingo|i-navajo|i-pwn|i-tao|i-tay|'
            . 'i-tsu|no-bok|no-nyn|sgn-be-fr|sgn-be-nl|sgn-ch-de|zh-guoyu|zh-hakka|'
            . 'zh-min|zh-min-nan|zh-xiang)';
        $privateUse = 'x(?:-[a-z0-9]{1,8})+';
        $language = '(?:[a-z]{2,3}(?:-[a-z]{3}){0,3}|[a-z]{4}|[a-z]{5,8})';
        $script = '(?:-[a-z]{4})?';
        $region = '(?:-(?:[a-z]{2}|\d{3}))?';
        $variants = '(?:-(?:[a-z0-9]{5,8}|\d[a-z0-9]{3}))*';
        $extensions = '(?:-[0-9a-wy-z](?:-[a-z0-9]{2,8})+)*';
        $privateSuffix = '(?:-x(?:-[a-z0-9]{1,8})+)?';

        return preg_match(
            '/^(?:'
                . $grandfathered
                . '|'
                . $privateUse
                . '|'
                . $language
                . $script
                . $region
                . $variants
                . $extensions
                . $privateSuffix
                . ')$/Di',
            $value
        ) === 1;
    }

    private function __construct()
    {
    }
}
