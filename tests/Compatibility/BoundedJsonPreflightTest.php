<?php

declare(strict_types=1);

namespace TCT\Tests\Compatibility;

use PHPUnit\Framework\TestCase;
use TCT\Compatibility\Doctor\BoundedJsonPreflight;
use TCT\Draft03\Protocol;

final class BoundedJsonPreflightTest extends TestCase
{
    public function testNodeBoundaryIsInclusive(): void
    {
        $accepted = '[' . implode(',', array_fill(
            0,
            Protocol::MAX_JSON_NODES - 1,
            'null'
        )) . ']';
        $preflight = new BoundedJsonPreflight();

        self::assertSame(['ok' => true, 'code' => ''], $preflight->inspect($accepted));
        self::assertSame(
            'json_nodes',
            $preflight->inspect(substr($accepted, 0, -1) . ',null]')['code']
        );
    }

    public function testScalarDepthBoundaryIsInclusive(): void
    {
        $accepted = str_repeat('[', Protocol::MAX_JSON_DEPTH)
            . 'null'
            . str_repeat(']', Protocol::MAX_JSON_DEPTH);
        $rejected = '[' . $accepted . ']';
        $preflight = new BoundedJsonPreflight();

        self::assertTrue($preflight->inspect($accepted)['ok']);
        self::assertSame('json_depth', $preflight->inspect($rejected)['code']);
    }

    public function testEmptyContainerAtMaximumLogicalDepthIsAccepted(): void
    {
        $accepted = str_repeat('[', Protocol::MAX_JSON_DEPTH + 1)
            . str_repeat(']', Protocol::MAX_JSON_DEPTH + 1);

        self::assertTrue((new BoundedJsonPreflight())->inspect($accepted)['ok']);
        self::assertIsArray(json_decode(
            $accepted,
            true,
            Protocol::MAX_JSON_DEPTH + 2,
            JSON_THROW_ON_ERROR
        ));
    }

    public function testEncodedKeyAndMalformedStructureFailClosed(): void
    {
        $oversizedKey = '{"'
            . str_repeat('\\u0061', Protocol::MAX_JSON_KEY_BYTES + 3)
            . '":null}';
        $preflight = new BoundedJsonPreflight();

        self::assertSame('json_key_bytes', $preflight->inspect($oversizedKey)['code']);
        self::assertSame('invalid_json', $preflight->inspect('{"open":"string}')['code']);
        self::assertSame('invalid_json', $preflight->inspect(']')['code']);
    }

    public function testObjectKeysAreNotChargedAsValueNodes(): void
    {
        $members = [];
        for ($index = 0; $index < Protocol::MAX_JSON_NODES - 1; ++$index) {
            $members[] = '"k' . $index . '":null';
        }
        $accepted = '{' . implode(',', $members) . '}';

        self::assertTrue((new BoundedJsonPreflight())->inspect($accepted)['ok']);
    }
}
