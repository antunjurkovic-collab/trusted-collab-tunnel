<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

use TCT\Draft03\Protocol;

/**
 * Zero-copy structural preflight before allocating a decoded JSON tree.
 *
 * Full JSON grammar and exact decoded string limits remain the responsibility
 * of json_decode() and the Draft-03 certifiers. This pass prevents excessive
 * depth or node allocation first.
 */
final class BoundedJsonPreflight
{
    /**
     * @return array{ok: bool, code: string}
     */
    public function inspect(string $json): array
    {
        $length = strlen($json);
        $depth = 0;
        $nodes = 0;

        for ($offset = 0; $offset < $length; ++$offset) {
            $character = $json[$offset];
            if ($character === '"') {
                $start = $offset;
                $escaped = false;
                for (++$offset; $offset < $length; ++$offset) {
                    $stringCharacter = $json[$offset];
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($stringCharacter === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($stringCharacter === '"') {
                        break;
                    }
                }
                if ($offset >= $length) {
                    return ['ok' => false, 'code' => 'invalid_json'];
                }

                $lookahead = $offset + 1;
                while ($lookahead < $length && $this->isWhitespace($json[$lookahead])) {
                    ++$lookahead;
                }
                $isObjectKey = $lookahead < $length && $json[$lookahead] === ':';
                if ($isObjectKey) {
                    $encodedKeyBytes = $offset - $start - 1;
                    $maximumEncodedKeyBytes = (6 * Protocol::MAX_JSON_KEY_BYTES) + 12;
                    if ($encodedKeyBytes > $maximumEncodedKeyBytes) {
                        return ['ok' => false, 'code' => 'json_key_bytes'];
                    }
                } else {
                    if ($depth > Protocol::MAX_JSON_DEPTH) {
                        return ['ok' => false, 'code' => 'json_depth'];
                    }
                    if (!$this->chargeNode($nodes)) {
                        return ['ok' => false, 'code' => 'json_nodes'];
                    }
                }
                continue;
            }

            if ($character === '{' || $character === '[') {
                if (!$this->chargeNode($nodes)) {
                    return ['ok' => false, 'code' => 'json_nodes'];
                }
                ++$depth;
                if ($depth > Protocol::MAX_JSON_DEPTH + 1) {
                    return ['ok' => false, 'code' => 'json_depth'];
                }
                continue;
            }

            if ($character === '}' || $character === ']') {
                --$depth;
                if ($depth < 0) {
                    return ['ok' => false, 'code' => 'invalid_json'];
                }
                continue;
            }

            if (
                $character === '-'
                || ($character >= '0' && $character <= '9')
                || $character === 't'
                || $character === 'f'
                || $character === 'n'
            ) {
                if ($depth > Protocol::MAX_JSON_DEPTH) {
                    return ['ok' => false, 'code' => 'json_depth'];
                }
                if (!$this->chargeNode($nodes)) {
                    return ['ok' => false, 'code' => 'json_nodes'];
                }
                while (
                    $offset + 1 < $length
                    && !$this->isScalarDelimiter($json[$offset + 1])
                ) {
                    ++$offset;
                }
            }
        }

        return $depth === 0
            ? ['ok' => true, 'code' => '']
            : ['ok' => false, 'code' => 'invalid_json'];
    }

    private function chargeNode(int &$nodes): bool
    {
        ++$nodes;

        return $nodes <= Protocol::MAX_JSON_NODES;
    }

    private function isWhitespace(string $character): bool
    {
        return $character === ' '
            || $character === "\t"
            || $character === "\r"
            || $character === "\n";
    }

    private function isScalarDelimiter(string $character): bool
    {
        return $this->isWhitespace($character)
            || $character === ','
            || $character === ']'
            || $character === '}';
    }
}
