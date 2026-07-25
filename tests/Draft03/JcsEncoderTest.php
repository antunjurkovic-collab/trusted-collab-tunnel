<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TCT\Draft03\CanonicalizationException;
use TCT\Draft03\JcsEncoder;

final class JcsEncoderTest extends TestCase
{
    private JcsEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new JcsEncoder();
    }

    #[DataProvider('appendixBFiniteNumbers')]
    public function testOfficialRfc8785AppendixBNumbers(string $bits, string $expected): void
    {
        $number = unpack('Evalue', hex2bin($bits));
        self::assertIsArray($number);
        self::assertSame($expected, $this->encoder->encode($number['value']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function appendixBFiniteNumbers(): iterable
    {
        yield 'zero' => ['0000000000000000', '0'];
        yield 'minus zero' => ['8000000000000000', '0'];
        yield 'minimum positive' => ['0000000000000001', '5e-324'];
        yield 'minimum negative' => ['8000000000000001', '-5e-324'];
        yield 'maximum positive' => ['7fefffffffffffff', '1.7976931348623157e+308'];
        yield 'maximum negative' => ['ffefffffffffffff', '-1.7976931348623157e+308'];
        yield 'maximum positive integer' => ['4340000000000000', '9007199254740992'];
        yield 'maximum negative integer' => ['c340000000000000', '-9007199254740992'];
        yield 'approximately two to the power 68' => ['4430000000000000', '295147905179352830000'];
        yield 'below 1e23' => ['44b52d02c7e14af5', '9.999999999999997e+22'];
        yield '1e23' => ['44b52d02c7e14af6', '1e+23'];
        yield 'above 1e23' => ['44b52d02c7e14af7', '1.0000000000000001e+23'];
        yield 'below fixed threshold one' => ['444b1ae4d6e2ef4e', '999999999999999700000'];
        yield 'below fixed threshold two' => ['444b1ae4d6e2ef4f', '999999999999999900000'];
        yield 'fixed threshold' => ['444b1ae4d6e2ef50', '1e+21'];
        yield 'below 1e-6' => ['3eb0c6f7a0b5ed8c', '9.999999999999997e-7'];
        yield '1e-6' => ['3eb0c6f7a0b5ed8d', '0.000001'];
        yield 'rounding sample one' => ['41b3de4355555553', '333333333.3333332'];
        yield 'rounding sample two' => ['41b3de4355555554', '333333333.33333325'];
        yield 'rounding sample three' => ['41b3de4355555555', '333333333.3333333'];
        yield 'rounding sample four' => ['41b3de4355555556', '333333333.3333334'];
        yield 'rounding sample five' => ['41b3de4355555557', '333333333.33333343'];
        yield 'negative small number' => ['becbf647612f3696', '-0.0000033333333333333333'];
        yield 'round to even' => ['43143ff3c1cb0959', '1424953923781206.2'];
    }

    #[DataProvider('appendixBNonfiniteNumbers')]
    public function testOfficialNonfiniteRowsFailClosed(string $bits): void
    {
        $number = unpack('Evalue', hex2bin($bits));
        self::assertIsArray($number);

        $this->expectException(CanonicalizationException::class);
        $this->encoder->encode($number['value']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function appendixBNonfiniteNumbers(): iterable
    {
        yield 'NaN' => ['7fffffffffffffff'];
        yield 'Infinity' => ['7ff0000000000000'];
    }

    public function testPropertyNamesUseRecursiveUtf16CodeUnitOrdering(): void
    {
        $value = [
            "\u{20ac}" => 'Euro Sign',
            "\r" => 'Carriage Return',
            "\u{fb33}" => 'Hebrew Letter Dalet With Dagesh',
            '1' => 'One',
            "\u{1f600}" => 'Emoji: Grinning Face',
            "\u{0080}" => 'Control',
            "\u{00f6}" => 'Latin Small Letter O With Diaeresis',
        ];

        self::assertSame(
            '{"\r":"Carriage Return","1":"One","":"Control","ö":"Latin Small Letter O With Diaeresis","€":"Euro Sign","😀":"Emoji: Grinning Face","דּ":"Hebrew Letter Dalet With Dagesh"}',
            $this->encoder->encode($value)
        );
    }

    public function testUnknownMembersRemainInTheCanonicalValue(): void
    {
        self::assertSame(
            '{"content":"Hello","hash":"extension-value"}',
            $this->encoder->encode(['hash' => 'extension-value', 'content' => 'Hello'])
        );
    }

    public function testInvalidUtf8FailsClosed(): void
    {
        $this->expectException(CanonicalizationException::class);
        $this->encoder->encode("\xB1\x31");
    }

    public function testUnsupportedTypesFailClosed(): void
    {
        $resource = fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            $this->encoder->encode($resource);
            self::fail('Expected unsupported resource to fail.');
        } catch (DomainException) {
            self::assertTrue(true);
        } finally {
            fclose($resource);
        }
    }

    public function testIntegersOutsideTheInteroperableRangeFailClosed(): void
    {
        $this->expectException(CanonicalizationException::class);
        $this->encoder->encode(9_007_199_254_740_992);
    }
}
