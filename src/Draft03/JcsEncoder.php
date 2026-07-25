<?php

declare(strict_types=1);

namespace TCT\Draft03;

use JsonException;
use JsonSerializable;
use Throwable;

/**
 * RFC 8785 JSON Canonicalization Scheme encoder.
 *
 * PHP's JSON encoder already uses a shortest-round-trip conversion for binary64
 * values. JCS additionally requires ECMAScript's fixed/scientific notation
 * thresholds and formatting, which formatFloat() applies to those digits.
 */
final class JcsEncoder
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const MAX_INTEROPERABLE_INTEGER = 9_007_199_254_740_991;

    public function encode(mixed $value): string
    {
        try {
            $nodes = 0;
            $bytes = 0;
            $encoded = $this->serialize($value, 0, $nodes, $bytes);
            if (strlen($encoded) !== $bytes) {
                throw new CanonicalizationException('Internal JCS byte counter mismatch.');
            }

            return $encoded;
        } catch (CanonicalizationException $exception) {
            throw $exception;
        } catch (JsonException $exception) {
            throw new CanonicalizationException('Value is outside the JCS/I-JSON string domain.', 0, $exception);
        } catch (Throwable $exception) {
            throw new CanonicalizationException('Value cannot be serialized as JCS.', 0, $exception);
        }
    }

    private function serialize(mixed $value, int $depth, int &$nodes, int &$bytes): string
    {
        if ($depth > Protocol::MAX_JSON_DEPTH) {
            throw new CanonicalizationException('JSON nesting depth exceeds the internal Draft-03 limit.');
        }
        ++$nodes;
        if ($nodes > Protocol::MAX_JSON_NODES) {
            throw new CanonicalizationException('JSON node count exceeds the internal Draft-03 limit.');
        }

        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        if ($value === null) {
            return $this->charged('null', $bytes);
        }

        if (is_bool($value)) {
            return $this->charged($value ? 'true' : 'false', $bytes);
        }

        if (is_string($value)) {
            if (strlen($value) > Protocol::MAX_JSON_STRING_BYTES) {
                throw new CanonicalizationException('JSON string exceeds the internal Draft-03 byte limit.');
            }

            return $this->charged(json_encode($value, self::JSON_FLAGS), $bytes);
        }

        if (is_int($value)) {
            if ($value > self::MAX_INTEROPERABLE_INTEGER || $value < -self::MAX_INTEROPERABLE_INTEGER) {
                throw new CanonicalizationException('Integer is outside the interoperable I-JSON range.');
            }

            return $this->charged((string) $value, $bytes);
        }

        if (is_float($value)) {
            return $this->charged($this->formatFloat($value), $bytes);
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $this->charge(2, $bytes);
                $encoded = [];
                foreach ($value as $index => $member) {
                    if ($index > 0) {
                        $this->charge(1, $bytes);
                    }
                    $encoded[] = $this->serialize($member, $depth + 1, $nodes, $bytes);
                }

                return '[' . implode(',', $encoded) . ']';
            }

            return $this->serializeObject($value, $depth, $nodes, $bytes);
        }

        throw new CanonicalizationException(sprintf('Unsupported JCS value type: %s.', get_debug_type($value)));
    }

    /**
     * @param array<array-key, mixed> $members
     */
    private function serializeObject(array $members, int $depth, int &$nodes, int &$bytes): string
    {
        $records = [];
        $this->charge(2, $bytes);
        foreach (array_keys($members) as $index => $sourceKey) {
            $key = (string) $sourceKey;
            if (strlen($key) > Protocol::MAX_JSON_KEY_BYTES) {
                throw new CanonicalizationException('JSON object key exceeds the internal Draft-03 byte limit.');
            }
            if (!mb_check_encoding($key, 'UTF-8')) {
                throw new CanonicalizationException('JSON object key is not valid UTF-8.');
            }
            if ($index > 0) {
                $this->charge(1, $bytes);
            }
            $encodedKey = json_encode($key, self::JSON_FLAGS);
            $this->charge(strlen($encodedKey) + 1, $bytes);
            $records[] = [
                'source' => $sourceKey,
                'encoded' => $encodedKey,
                'sort' => mb_convert_encoding($key, 'UTF-16BE', 'UTF-8'),
            ];
        }

        usort(
            $records,
            static fn (array $left, array $right): int => strcmp($left['sort'], $right['sort'])
        );

        $encoded = [];
        foreach ($records as $record) {
            $encoded[] = $record['encoded']
                . ':'
                . $this->serialize(
                    $members[$record['source']],
                    $depth + 1,
                    $nodes,
                    $bytes
                );
        }

        return '{' . implode(',', $encoded) . '}';
    }

    private function charged(string $encoded, int &$bytes): string
    {
        $this->charge(strlen($encoded), $bytes);
        return $encoded;
    }

    private function charge(int $additionalBytes, int &$bytes): void
    {
        if ($additionalBytes > Protocol::MAX_IDENTITY_BYTES - $bytes) {
            throw new CanonicalizationException('JCS output exceeds the internal Draft-03 byte limit.');
        }
        $bytes += $additionalBytes;
    }

    private function formatFloat(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            throw new CanonicalizationException('NaN and Infinity are outside the JCS domain.');
        }

        if ($value == 0.0) {
            return '0';
        }

        $negative = $value < 0.0;
        if ($negative) {
            $value = -$value;
        }

        $raw = strtolower(json_encode($value, self::JSON_FLAGS | JSON_PRESERVE_ZERO_FRACTION));
        [$mantissa, $explicitExponent] = array_pad(explode('e', $raw, 2), 2, '0');

        $dot = strpos($mantissa, '.');
        $digitsBeforeDot = $dot === false ? strlen($mantissa) : $dot;
        $digits = str_replace('.', '', $mantissa);
        $decimalPosition = $digitsBeforeDot + (int) $explicitExponent;

        while (strlen($digits) > 1 && $digits[0] === '0') {
            $digits = substr($digits, 1);
            --$decimalPosition;
        }

        $digits = rtrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        $sign = $negative ? '-' : '';
        if ($value >= 1.0e21 || $value < 1.0e-6) {
            $exponent = $decimalPosition - 1;
            $coefficient = $digits[0];
            if (strlen($digits) > 1) {
                $coefficient .= '.' . substr($digits, 1);
            }

            return $sign . $coefficient . 'e' . ($exponent >= 0 ? '+' : '-') . abs($exponent);
        }

        if ($decimalPosition <= 0) {
            return $sign . '0.' . str_repeat('0', -$decimalPosition) . $digits;
        }

        if ($decimalPosition >= strlen($digits)) {
            return $sign . $digits . str_repeat('0', $decimalPosition - strlen($digits));
        }

        return $sign
            . substr($digits, 0, $decimalPosition)
            . '.'
            . substr($digits, $decimalPosition);
    }
}
