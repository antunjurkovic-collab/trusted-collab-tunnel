<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TCT\Draft03\AcceptEncoding;
use TCT\Draft03\ConditionalRequest;

final class ConditionalRequestTest extends TestCase
{
    #[DataProvider('matchingFields')]
    public function testIfNoneMatchUsesRfcWeakComparison(string $fieldValue): void
    {
        self::assertTrue(ConditionalRequest::ifNoneMatchMatches($fieldValue, '"sha256-current"'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function matchingFields(): iterable
    {
        yield 'wildcard' => ['*'];
        yield 'quoted' => ['"sha256-current"'];
        yield 'weak' => ['W/"sha256-current"'];
        yield 'list' => ['"other", W/"sha256-current"'];
        yield 'comma inside opaque tag' => ['"other,tag", "sha256-current"'];
        yield 'trailing whitespace' => ["\"sha256-current\" \t"];
    }

    public function testMalformedOrDifferentFieldsDoNotMatch(): void
    {
        self::assertFalse(ConditionalRequest::ifNoneMatchMatches('"different"', '"sha256-current"'));
        self::assertFalse(ConditionalRequest::ifNoneMatchMatches('sha256-current', '"sha256-current"'));
        self::assertFalse(ConditionalRequest::ifNoneMatchMatches('"unterminated', '"sha256-current"'));
        self::assertFalse(ConditionalRequest::ifNoneMatchMatches('w/"sha256-current"', '"sha256-current"'));
    }

    public function testOnlyGetAndHeadAreReadMethods(): void
    {
        self::assertTrue(ConditionalRequest::isSafeReadMethod('GET'));
        self::assertTrue(ConditionalRequest::isSafeReadMethod('head'));
        self::assertFalse(ConditionalRequest::isSafeReadMethod('POST'));
    }

    #[DataProvider('acceptEncodingFields')]
    public function testIdentityNegotiation(?string $fieldValue, bool $expected): void
    {
        self::assertSame($expected, AcceptEncoding::identityIsAllowed($fieldValue));
    }

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function acceptEncodingFields(): iterable
    {
        yield 'absent' => [null, true];
        yield 'gzip leaves identity available' => ['gzip', true];
        yield 'explicit identity' => ['identity', true];
        yield 'identity prohibited' => ['gzip, identity;q=0', false];
        yield 'wildcard prohibited' => ['*;q=0', false];
        yield 'identity overrides wildcard' => ['*;q=0, identity;q=1', true];
    }
}
