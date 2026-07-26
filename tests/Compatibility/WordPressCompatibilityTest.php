<?php

declare(strict_types=1);

namespace {
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
        {
            return json_encode($value, $flags, $depth);
        }
    }

    if (!function_exists('get_option')) {
        function get_option(string $name, mixed $default = false): mixed
        {
            return $GLOBALS['tct_test_options'][$name] ?? $default;
        }
    }

    if (!function_exists('is_multisite')) {
        function is_multisite(): bool
        {
            return false;
        }
    }
}

namespace TCT\Tests\Compatibility {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use TCT\Compatibility\Doctor\DoctorCheck;
    use TCT\Compatibility\Doctor\DoctorLimits;
    use TCT\Compatibility\Doctor\DoctorReport;
    use TCT\Compatibility\Doctor\LayerResult;
    use TCT\Compatibility\Doctor\Outcome;
    use TCT\Compatibility\Doctor\ProbeRequest;
    use TCT\Compatibility\Doctor\ProviderSignal;
    use TCT\Compatibility\Doctor\ReportLimitException;
    use TCT\Compatibility\WordPress\DoctorReportStore;
    use TCT\Compatibility\WordPress\ProviderSignals;
    use TCT\Compatibility\WordPress\WordPressHttpTransport;
    use TCT\Compatibility\WordPress\WordPressReportSerializer;

    final class WordPressCompatibilityTest extends TestCase
    {
        public function testTransportUsesFixedSafeRawByteArguments(): void
        {
            $capturedUrl = '';
            $capturedArguments = [];
            $transport = new WordPressHttpTransport(
                static function (string $url, array $arguments) use (
                    &$capturedUrl,
                    &$capturedArguments
                ): array {
                    $capturedUrl = $url;
                    $capturedArguments = $arguments;

                    return [
                        'response' => ['code' => 200],
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'ETag' => '"sha256-test"',
                            'Set-Cookie' => 'must-not-be-collected',
                        ],
                        'body' => "\x1f\x8braw-bytes",
                    ];
                },
                static fn(string $url): bool => $url === 'https://example.com/test'
            );
            $request = new ProbeRequest(
                'https://example.com/test',
                'GET',
                [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip',
                    'Authorization' => 'Bearer must-not-be-forwarded',
                ],
                4.5,
                16
            );

            $response = $transport->request($request);

            self::assertSame('https://example.com/test', $capturedUrl);
            self::assertSame('GET', $capturedArguments['method']);
            self::assertSame(4.5, $capturedArguments['timeout']);
            self::assertSame(0, $capturedArguments['redirection']);
            self::assertFalse($capturedArguments['decompress']);
            self::assertFalse($capturedArguments['compress']);
            self::assertTrue($capturedArguments['sslverify']);
            self::assertTrue($capturedArguments['reject_unsafe_urls']);
            self::assertSame([], $capturedArguments['cookies']);
            self::assertSame(17, $capturedArguments['limit_response_size']);
            self::assertSame(
                ['Accept' => 'application/json', 'Accept-Encoding' => 'gzip'],
                $capturedArguments['headers']
            );
            self::assertSame("\x1f\x8braw-bytes", $response->body);
            self::assertSame('application/json', $response->header('content-type'));
            self::assertSame('', $response->header('set-cookie'));
        }

        public function testUnsafeUrlFailsBeforeTransportCall(): void
        {
            $called = false;
            $transport = new WordPressHttpTransport(
                static function () use (&$called): array {
                    $called = true;
                    return [];
                },
                static fn(string $url): bool => false
            );

            $failure = $transport->request(new ProbeRequest(
                'https://example.com/test',
                'GET',
                [],
                1.0,
                10
            ));

            self::assertSame('unsafe_url', $failure->code);
            self::assertFalse($called);
        }

        public function testTransportDetectsTimeoutAndBodyTruncation(): void
        {
            $timeout = new WordPressHttpTransport(
                static fn(): FakeWordPressError => new FakeWordPressError('http_request_timeout'),
                static fn(): bool => true
            );
            $failure = $timeout->request(new ProbeRequest(
                'https://example.com/test',
                'GET',
                [],
                1.0,
                10
            ));
            self::assertSame('transport_timeout', $failure->code);

            $oversized = new WordPressHttpTransport(
                static fn(): array => [
                    'response' => ['code' => 200],
                    'headers' => [],
                    'body' => str_repeat('x', 11),
                ],
                static fn(): bool => true
            );
            $response = $oversized->request(new ProbeRequest(
                'https://example.com/test',
                'GET',
                [],
                1.0,
                10
            ));
            self::assertTrue($response->possiblyTruncated);
        }

        public function testReportStoreIsBoundedAndIsolatedByUserAndJob(): void
        {
            $values = [];
            $ttl = 0;
            $store = new DoctorReportStore(
                static function (string $key, string $json, int $expiration) use (
                    &$values,
                    &$ttl
                ): bool {
                    $values[$key] = $json;
                    $ttl = $expiration;
                    return true;
                },
                static function (string $key) use (&$values): mixed {
                    return $values[$key] ?? false;
                },
                static fn(): string => str_repeat('a', 32)
            );

            $jobId = $store->save(7, '{"safe":true}', 1024);

            self::assertSame(str_repeat('a', 32), $jobId);
            self::assertSame(3600, $ttl);
            self::assertSame('{"safe":true}', $store->load(7, $jobId, 1024));
            self::assertNull($store->load(8, $jobId, 1024));
            self::assertNull($store->load(7, '../invalid', 1024));
            self::assertNull($store->load(7, $jobId, 2));
        }

        #[DataProvider('outcomeProvider')]
        public function testAuthoritativeSerializationParityForEveryOutcome(
            Outcome $outcome
        ): void {
            $limits = new DoctorLimits();
            $report = $this->report(
                $limits,
                [new DoctorCheck('variant', $outcome, 'https://example.com/', 'expected', 'observed')]
            );
            $serializer = new WordPressReportSerializer($limits);

            $json = $serializer->serialize($report);
            $expected = wp_json_encode(
                $report->toArray(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            self::assertSame($expected, $json);
            self::assertSame(strlen($expected), strlen($json));
            self::assertLessThanOrEqual($limits->maxDiagnosticBytes, strlen($json));
        }

        /**
         * @return iterable<string, array{Outcome}>
         */
        public static function outcomeProvider(): iterable
        {
            foreach (Outcome::cases() as $outcome) {
                yield $outcome->value => [$outcome];
            }
        }

        public function testWorstEscapingReportFitsAndAggregateOverflowIsRejected(): void
        {
            $limits = new DoctorLimits();
            $escaped = str_repeat("\x01", 1000);
            $checks = [];
            for ($index = 0; $index < 30; ++$index) {
                $checks[] = new DoctorCheck(
                    'escaped_' . $index,
                    Outcome::Fail,
                    'https://example.com/',
                    $escaped,
                    $escaped
                );
            }

            $serializer = new WordPressReportSerializer($limits);
            $json = $serializer->serialize($this->report($limits, $checks));
            self::assertLessThan($limits->maxDiagnosticBytes, strlen($json));
            self::assertSame(
                $json,
                wp_json_encode(
                    $this->report($limits, $checks)->toArray(),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
            );

            $tooMuch = [];
            for ($index = 0; $index < 64; ++$index) {
                $tooMuch[] = new DoctorCheck(
                    'large_' . $index,
                    Outcome::Fail,
                    'https://example.com/',
                    str_repeat('e', 4096),
                    str_repeat('o', 4096)
                );
            }

            $this->expectException(ReportLimitException::class);
            $serializer->serialize($this->report($limits, $tooMuch));
        }

        public function testCollectionCountAndMinimalFailureRemainBounded(): void
        {
            $limits = new DoctorLimits();
            $checks = [];
            for ($index = 0; $index < 65; ++$index) {
                $checks[] = new DoctorCheck(
                    'count_' . $index,
                    Outcome::Pass,
                    '',
                    '',
                    ''
                );
            }
            $serializer = new WordPressReportSerializer($limits);

            try {
                $serializer->serialize($this->report($limits, $checks));
                self::fail('Expected a collection limit failure.');
            } catch (ReportLimitException) {
                self::assertTrue(true);
            }

            $minimal = $serializer->minimalFailure(
                '3.0.0-alpha.3',
                str_repeat('a', 64),
                'diagnostic_bytes'
            );
            self::assertLessThan($limits->maxDiagnosticBytes, strlen($minimal));
            self::assertSame(
                'inconclusive',
                json_decode($minimal, true, flags: JSON_THROW_ON_ERROR)['overall']
            );
        }

        public function testProviderDetectionIsAllowlistedAndDeterministic(): void
        {
            $GLOBALS['tct_test_options']['active_plugins'] = [
                'unrelated/secret-plugin.php',
                'wp-rocket/wp-rocket.php',
                'litespeed-cache/litespeed-cache.php',
            ];
            $_SERVER['SERVER_SOFTWARE'] = 'LiteSpeed test';

            $signals = array_map(
                static fn(ProviderSignal $signal): array => $signal->toArray(),
                ProviderSignals::collect()
            );
            $ids = array_column($signals, 'id');

            self::assertContains('wordpress_litespeed_cache', $ids);
            self::assertContains('wordpress_wp_rocket', $ids);
            self::assertContains('server_software', $ids);
            self::assertNotContains('unrelated_secret_plugin', $ids);
            self::assertSame($ids, array_values(array_unique($ids)));
            self::assertSame(
                array_fill(0, count($signals), 'diagnostic_only'),
                array_column($signals, 'effect')
            );
        }

        public function testCompatibilityLayerContainsNoMutationOrPublicEndpoint(): void
        {
            $root = dirname(__DIR__, 2);
            $files = array_merge(
                glob($root . '/includes/Compatibility/*.php') ?: [],
                glob($root . '/src/Compatibility/Doctor/*.php') ?: []
            );
            $source = '';
            foreach ($files as $file) {
                $source .= file_get_contents($file);
            }

            foreach ([
                'update_option(',
                'delete_option(',
                'tct_bump_cache_epoch(',
                'tct_invalidate_protocol_generation(',
                'register_rest_route(',
                'wp_ajax_',
                'wp_schedule_event(',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }

            $plugin = file_get_contents($root . '/trusted-collab-tunnel.php');
            self::assertStringContainsString(
                "'TCT\\\\Compatibility\\\\Doctor\\\\'",
                $plugin
            );
            self::assertStringContainsString(
                "includes/Compatibility/bootstrap.php",
                $plugin
            );
        }

        /**
         * @param list<DoctorCheck> $checks
         */
        private function report(DoctorLimits $limits, array $checks): DoctorReport
        {
            return new DoctorReport(
                '3.0.0-alpha.3',
                str_repeat('a', 64),
                'https://example.com:443',
                'https://example.com/llm-sitemap.json',
                '2026-07-26T12:00:00+00:00',
                '2026-07-26T12:00:01+00:00',
                $limits,
                [
                    'requests' => 1,
                    'redirects' => 0,
                    'response_bytes' => 0,
                    'sampled_murls' => 0,
                    'elapsed_seconds' => 1.0,
                ],
                [
                    'origin_implementation' => new LayerResult(Outcome::NotTested, 'not tested'),
                    'wordpress_cache_integration' => new LayerResult(Outcome::NotTested, 'not tested'),
                    'public_delivery_path' => new LayerResult(Outcome::Pass, 'tested'),
                ],
                $checks,
                [new ProviderSignal('server', 'test', 'test')],
                "& ./scripts/validate-live.ps1 -BaseUrl 'https://example.com'"
            );
        }
    }

    final class FakeWordPressError
    {
        public function __construct(private readonly string $code)
        {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }
    }
}
