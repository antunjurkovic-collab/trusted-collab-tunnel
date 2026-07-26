<?php

declare(strict_types=1);

namespace TCT\Tests\Compatibility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctorSubprocessSafetyTest extends TestCase
{
    #[DataProvider('modeProvider')]
    public function testProbeCompletesWithoutPanicAbortOrMemoryExhaustion(
        string $mode,
        string $memoryLimit
    ): void {
        $fixture = dirname(__DIR__) . '/fixtures/doctor_subprocess_probe.php';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            [PHP_BINARY, '-d', 'memory_limit=' . $memoryLimit, $fixture, $mode],
            $descriptors,
            $pipes
        );
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $stderr . "\n" . $stdout);
        $result = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($result['ok']);
        self::assertStringNotContainsString('Allowed memory size', $stderr);
        self::assertStringNotContainsString('Stack overflow', $stderr);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function modeProvider(): iterable
    {
        yield 'maximum raw body' => ['raw_at', '64M'];
        yield 'raw body over maximum' => ['raw_over', '64M'];
        yield 'gzip expansion' => ['gzip_bomb', '32M'];
        yield 'deep JSON' => ['deep_json', '32M'];
        yield 'worst escaped diagnostic' => ['diagnostic', '32M'];
    }
}
