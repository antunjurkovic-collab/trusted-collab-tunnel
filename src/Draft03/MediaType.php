<?php

declare(strict_types=1);

namespace TCT\Draft03;

final class MediaType
{
    public static function isWellFormed(string $value): bool
    {
        $token = "[!#$%&'*+\\-.^_`|~0-9A-Za-z]+";
        $quoted = '"(?:[\t !#-\[\]-~]|\\\\[\t !-~])*"';

        return preg_match(
            '/^'
                . $token
                . '\/'
                . $token
                . '(?:[ \t]*;[ \t]*'
                . $token
                . '=(?:'
                . $token
                . '|'
                . $quoted
                . '))*[ \t]*$/D',
            $value
        ) === 1;
    }

    private function __construct()
    {
    }
}
