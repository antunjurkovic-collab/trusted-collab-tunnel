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
            return $this->serialize($value);
        } catch (CanonicalizationException $exception) {
            throw $exception;
        } catch (JsonException $exception) {
            throw new CanonicalizationException('Value is outside the JCS/I-JSON string domain.', 0, $exception);
        } catch (Throwable $exception) {
            throw new CanonicalizationException('Value cannot be serialized as JCS.', 0, $exception);
        }
    }

    private function serialize(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return json_encode($value, self::JSON_FLAGS);
        }

        if (is_int($value)) {
            if ($value > self::MAX_INTEROPERABLE_INTEGER || $value < -self::MAX_INTEROPERABLE_INTEGER) {
                throw new CanonicalizationException('Integer is outside the interoperable I-JSON range.');
            }

            return (string) $value;
        }

        if (is_float($value)) {
            return $this->formatFloat($value);
        }

        if ($value instanceof JsonSerializable) {
            return $this->serialize($value->jsonSerialize());
        }

        if (is_object($value)) {
            return $this->serializeObject(get_object_vars($value));
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(',', array_map($this->serialize(...), $value)) . ']';
            }

            return $this->serializeObject($value);
        }

        throw new CanonicalizationException(sprintf('Unsupported JCS value type: %s.', get_debug_type($value)));
    }

    /**
     * @param array<array-key, mixed> $members
     */
    private function serializeObject(array $members): string
    {
        $keys = array_keys($members);
        usort(
            $keys,
            static fn (int|string $left, int|string $right): int => strcmp(
                mb_convert_encoding((string) $left, 'UTF-16BE', 'UTF-8'),
                mb_convert_encoding((string) $right, 'UTF-16BE', 'UTF-8')
            )
        );

        $encoded = [];
        foreach ($keys as $key) {
            $encodedKey = json_encode((string) $key, self::JSON_FLAGS);
            $encoded[] = $encodedKey . ':' . $this->serialize($members[$key]);
        }

        return '{' . implode(',', $encoded) . '}';
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
