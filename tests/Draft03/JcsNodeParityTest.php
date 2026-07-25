<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use PHPUnit\Framework\TestCase;
use TCT\Draft03\JcsEncoder;

final class JcsNodeParityTest extends TestCase
{
    public function testGeneratedBinary64ValuesMatchEcmascriptJsonStringify(): void
    {
        $vectors = [];
        for ($index = 0; $index < 1_024; ++$index) {
            $bits = substr(hash('sha256', 'tct-draft03-jcs-' . $index), 0, 16);
            $unpacked = unpack('Evalue', hex2bin($bits));
            self::assertIsArray($unpacked);

            if (is_finite($unpacked['value'])) {
                $vectors[] = $bits;
            }
        }

        $script = <<<'JAVASCRIPT'
const vectors = JSON.parse(Buffer.from(process.argv[1], 'base64').toString('utf8'));
for (const bits of vectors) {
    const buffer = Buffer.from(bits, 'hex');
    process.stdout.write(JSON.stringify(buffer.readDoubleBE(0)) + '\n');
}
JAVASCRIPT;

        $payload = base64_encode(json_encode($vectors, JSON_THROW_ON_ERROR));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['node', '-e', $script, $payload], $descriptors, $pipes);
        self::assertIsResource($process, 'Node.js is required for generated JCS parity evidence.');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), $stderr);
        $expected = explode("\n", rtrim($stdout, "\n"));
        self::assertCount(count($vectors), $expected);

        $encoder = new JcsEncoder();
        foreach ($vectors as $index => $bits) {
            $unpacked = unpack('Evalue', hex2bin($bits));
            self::assertIsArray($unpacked);
            self::assertSame($expected[$index], $encoder->encode($unpacked['value']), 'bits=' . $bits);
        }
    }
}
