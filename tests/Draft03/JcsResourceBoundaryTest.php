<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use PHPUnit\Framework\TestCase;
use TCT\Draft03\CanonicalizationException;
use TCT\Draft03\JcsEncoder;
use TCT\Draft03\Protocol;

final class JcsResourceBoundaryTest extends TestCase
{
    public function testDepthBoundaryIsInclusive(): void
    {
        $accepted = null;
        for ($depth = 0; $depth < Protocol::MAX_JSON_DEPTH; ++$depth) {
            $accepted = [$accepted];
        }

        self::assertNotSame('', (new JcsEncoder())->encode($accepted));

        $rejected = [$accepted];
        $this->expectException(CanonicalizationException::class);
        $this->expectExceptionMessage('nesting depth');
        (new JcsEncoder())->encode($rejected);
    }

    public function testNodeBoundaryIsInclusive(): void
    {
        $accepted = array_fill(0, Protocol::MAX_JSON_NODES - 1, null);
        self::assertSame(
            5 * (Protocol::MAX_JSON_NODES - 1) + 1,
            strlen((new JcsEncoder())->encode($accepted))
        );

        $this->expectException(CanonicalizationException::class);
        $this->expectExceptionMessage('node count');
        (new JcsEncoder())->encode([...$accepted, null]);
    }

    public function testKeyByteBoundaryIsInclusive(): void
    {
        $key = str_repeat('k', Protocol::MAX_JSON_KEY_BYTES);
        self::assertStringStartsWith('{"kk', (new JcsEncoder())->encode([$key => true]));

        $this->expectException(CanonicalizationException::class);
        $this->expectExceptionMessage('object key');
        (new JcsEncoder())->encode([$key . 'k' => true]);
    }

    public function testStringByteBoundaryIsInclusive(): void
    {
        $value = str_repeat('s', Protocol::MAX_JSON_STRING_BYTES);
        self::assertSame(strlen($value) + 2, strlen((new JcsEncoder())->encode($value)));

        $this->expectException(CanonicalizationException::class);
        $this->expectExceptionMessage('JSON string');
        (new JcsEncoder())->encode($value . 's');
    }

    public function testAggregateOutputLimitFailsClosed(): void
    {
        $value = str_repeat('s', Protocol::MAX_JSON_STRING_BYTES);

        $this->expectException(CanonicalizationException::class);
        $this->expectExceptionMessage('JCS output');
        (new JcsEncoder())->encode([$value, $value, $value, $value]);
    }
}
