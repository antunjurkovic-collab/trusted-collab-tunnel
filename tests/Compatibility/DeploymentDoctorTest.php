<?php

declare(strict_types=1);

namespace TCT\Tests\Compatibility;

use PHPUnit\Framework\TestCase;
use TCT\Compatibility\Doctor\BoundedGzipDecoder;
use TCT\Compatibility\Doctor\DeploymentDoctor;
use TCT\Compatibility\Doctor\DoctorClock;
use TCT\Compatibility\Doctor\DoctorContext;
use TCT\Compatibility\Doctor\DoctorLimits;
use TCT\Compatibility\Doctor\DoctorReport;
use TCT\Compatibility\Doctor\DoctorTransport;
use TCT\Compatibility\Doctor\Outcome;
use TCT\Compatibility\Doctor\ProbeRequest;
use TCT\Compatibility\Doctor\ProbeResponse;
use TCT\Compatibility\Doctor\ProviderSignal;
use TCT\Compatibility\Doctor\SameOriginUrl;
use TCT\Compatibility\Doctor\TransportFailure;
use TCT\Draft03\IdentityRepresentation;
use TCT\Draft03\Protocol;

final class DeploymentDoctorTest extends TestCase
{
    private const ROOT = 'https://example.com/';
    private const SITEMAP = 'https://example.com/llm-sitemap.json';
    private const MURL = 'https://example.com/article/llm/';
    private const CURL = 'https://example.com/article/';

    public function testCompleteIdentityLanePassesWithTruthfulLayerSeparation(): void
    {
        [$sitemapValue, $sitemapIdentity, $murlValue, $murlIdentity] = $this->values();
        unset($sitemapValue, $murlValue);

        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"'],
                '<html></html>'
            ),
            $this->identityResponse(self::SITEMAP, $sitemapIdentity, Protocol::M_SITEMAP_PROFILE),
            $this->identityResponse(self::SITEMAP, $sitemapIdentity, Protocol::M_SITEMAP_PROFILE),
            $this->response(self::SITEMAP, 406, ['Vary' => 'Accept-Encoding']),
            $this->response(self::SITEMAP, 304, ['ETag' => $sitemapIdentity->etag]),
            $this->identityResponse(
                self::SITEMAP,
                $sitemapIdentity,
                Protocol::M_SITEMAP_PROFILE,
                '',
                true
            ),
            $this->response(self::SITEMAP, 405, ['Allow' => 'GET, HEAD']),
            $this->identityResponse(self::SITEMAP, $sitemapIdentity, Protocol::M_SITEMAP_PROFILE),
            $this->identityResponse(
                self::MURL,
                $murlIdentity,
                Protocol::M_URL_PROFILE,
                self::CURL
            ),
            $this->response(self::MURL, 304, ['ETag' => $murlIdentity->etag]),
            $this->identityResponse(
                self::MURL,
                $murlIdentity,
                Protocol::M_URL_PROFILE,
                self::CURL,
                true
            ),
            $this->identityResponse(
                self::MURL,
                $murlIdentity,
                Protocol::M_URL_PROFILE,
                self::CURL
            ),
        ]);

        $report = $this->doctor()->run($this->context(), $transport);
        $value = $report->toArray();

        self::assertSame(Outcome::Pass, $report->publicOutcome());
        self::assertSame('pass', $value['overall']);
        self::assertSame('not_tested', $value['layers']['origin_implementation']['outcome']);
        self::assertSame('not_tested', $value['layers']['wordpress_cache_integration']['outcome']);
        self::assertSame('pass', $value['layers']['public_delivery_path']['outcome']);
        self::assertSame(12, $value['counters']['requests']);
        self::assertSame(1, $value['counters']['sampled_murls']);
        self::assertCount(12, $transport->requests);
        self::assertSame('identity', $transport->requests[1]->headers['Accept-Encoding']);
        self::assertSame('gzip', $transport->requests[2]->headers['Accept-Encoding']);
        self::assertSame('identity;q=0', $transport->requests[3]->headers['Accept-Encoding']);
        self::assertSame('POST', $transport->requests[6]->method);
        self::assertSame('HEAD', $transport->requests[10]->method);
        self::assertContains(
            'diagnostic_only',
            array_column($value['signals'], 'effect')
        );
        self::assertLessThanOrEqual(64, count($value['checks']));
        self::assertSame(DoctorReport::SCHEMA, $value['schema']);
    }

    public function testGzipTransformationAndWeakEtagFailWithoutUtf8Decoding(): void
    {
        [, $sitemapIdentity] = $this->values();
        $gzip = gzencode($sitemapIdentity->body);
        self::assertIsString($gzip);

        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"']
            ),
            $this->identityResponse(self::SITEMAP, $sitemapIdentity, Protocol::M_SITEMAP_PROFILE),
            $this->response(
                self::SITEMAP,
                200,
                ['Content-Encoding' => 'gzip', 'ETag' => 'W/' . $sitemapIdentity->etag],
                $gzip
            ),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
        ]);

        $report = $this->doctor()->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame(Outcome::Fail, $report->publicOutcome());
        self::assertSame('fail', $checks['sitemap_gzip_identity']['outcome']);
        self::assertSame(
            'unexpected_content_coding',
            $checks['sitemap_gzip_identity']['failure_code']
        );
        self::assertStringContainsString(
            'decoded_matches_identity=yes',
            $checks['sitemap_gzip_identity']['observed']
        );
        self::assertSame('fail', $checks['sitemap_gzip_etag']['outcome']);
    }

    public function testLoopbackFailureIsInconclusiveAndDoesNotProbeFurther(): void
    {
        $transport = new QueueDoctorTransport([
            new TransportFailure(
                'loopback_unavailable',
                self::ROOT,
                3.0,
                'Public hostname could not be reached'
            ),
        ]);

        $report = $this->doctor()->run($this->context(), $transport);
        $value = $report->toArray();

        self::assertSame(Outcome::Inconclusive, $report->publicOutcome());
        self::assertSame(1, $value['counters']['requests']);
        self::assertSame('inconclusive', $value['checks'][0]['outcome']);
        self::assertSame('loopback_unavailable', $value['checks'][0]['failure_code']);
        self::assertCount(1, $transport->requests);
        self::assertStringContainsString('validate-live.ps1', $value['external_validator_command']);
    }

    public function testCrossOriginRedirectFailsBeforeFollowingIt(): void
    {
        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                302,
                ['Location' => 'https://attacker.example/redirect']
            ),
        ]);

        $report = $this->doctor()->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame(Outcome::Fail, $report->publicOutcome());
        self::assertSame('cross_origin_redirect', $checks['root_discovery']['failure_code']);
        self::assertCount(1, $transport->requests);
    }

    public function testSameOriginRedirectIsFollowedAndCharged(): void
    {
        $transport = new QueueDoctorTransport([
            $this->response(self::ROOT, 302, ['Location' => '/canonical/']),
            $this->response(
                'https://example.com/canonical/',
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"']
            ),
            new TransportFailure('loopback_unavailable', self::SITEMAP),
        ]);

        $report = $this->doctor()->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame('pass', $checks['root_discovery']['outcome']);
        self::assertSame(3, $report->toArray()['counters']['requests']);
        self::assertSame(1, $report->toArray()['counters']['redirects']);
        self::assertSame('https://example.com/canonical/', $transport->requests[1]->url);
    }

    public function testRedirectLimitFailsDeterministicallyAfterThreeHops(): void
    {
        $responses = [];
        for ($index = 0; $index < 4; ++$index) {
            $responses[] = $this->response(
                'https://example.com/redirect-' . $index,
                302,
                ['Location' => '/redirect-' . ($index + 1)]
            );
        }
        $transport = new QueueDoctorTransport($responses);

        $report = $this->doctor()->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame(Outcome::Fail, $report->publicOutcome());
        self::assertSame('redirect_limit', $checks['root_discovery']['failure_code']);
        self::assertSame(4, $report->toArray()['counters']['requests']);
        self::assertSame(3, $report->toArray()['counters']['redirects']);
    }

    public function testProtectedDeploymentIsNotTestedAndIssuesNoRequest(): void
    {
        $context = new DoctorContext(
            self::ROOT,
            self::SITEMAP,
            '3.0.0-alpha.3',
            str_repeat('a', 64),
            'external validator requires an ephemeral credential',
            false
        );
        $transport = new QueueDoctorTransport([]);

        $report = $this->doctor()->run($context, $transport);
        $checks = $this->checksById($report);

        self::assertSame(Outcome::NotTested, $report->publicOutcome());
        self::assertSame('protected_deployment', $checks['public_resources']['failure_code']);
        self::assertSame(0, $report->toArray()['counters']['requests']);
        self::assertSame([], $transport->requests);
    }

    public function testResponseLimitViolationFailsClosed(): void
    {
        $limits = new DoctorLimits(
            maxResponseBytes: 16,
            maxTotalResponseBytes: 32
        );
        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"'],
                str_repeat('x', 17)
            ),
        ]);

        $report = $this->doctor($limits)->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame(Outcome::Fail, $report->publicOutcome());
        self::assertSame('response_bytes', $checks['root_discovery']['failure_code']);
        self::assertSame(16, $transport->requests[0]->maximumBodyBytes);
    }

    /**
     * @dataProvider acceptedResponseBoundaryProvider
     */
    public function testResponseAtOrBelowLimitIsAcceptedByBudget(int $bytes): void
    {
        $limits = new DoctorLimits(
            maxResponseBytes: 16,
            maxTotalResponseBytes: 32
        );
        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"'],
                str_repeat('x', $bytes)
            ),
            new TransportFailure('loopback_unavailable', self::SITEMAP),
        ]);

        $report = $this->doctor($limits)->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame('pass', $checks['root_discovery']['outcome']);
        self::assertSame('inconclusive', $checks['sitemap_profile_structure']['outcome']);
        self::assertSame($bytes, $report->toArray()['counters']['response_bytes']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function acceptedResponseBoundaryProvider(): iterable
    {
        yield 'limit minus one' => [15];
        yield 'limit' => [16];
    }

    public function testBoundedGzipDecoderCoversLimitMinusOneLimitAndPlusOne(): void
    {
        $decoder = new BoundedGzipDecoder();
        foreach ([15, 16] as $bytes) {
            $coded = gzencode(str_repeat('x', $bytes));
            self::assertIsString($coded);
            $result = $decoder->decode($coded, 16);
            self::assertTrue($result['ok']);
            self::assertSame($bytes, strlen($result['body']));
        }

        $over = gzencode(str_repeat('x', 17));
        self::assertIsString($over);
        self::assertSame(
            'gzip_decode_failed_or_limit',
            $decoder->decode($over, 16)['code']
        );
        self::assertSame(
            'gzip_decode_failed_or_limit',
            $decoder->decode("\x1f\x8bnot-gzip", 16)['code']
        );
    }

    public function testTotalResponseBudgetIsDistinguishedFromPerBodyBudget(): void
    {
        $limits = new DoctorLimits(
            maxResponseBytes: 10,
            maxTotalResponseBytes: 15
        );
        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"'],
                str_repeat('r', 10)
            ),
            $this->response(self::SITEMAP, 200, [], str_repeat('s', 6)),
        ]);

        $report = $this->doctor($limits)->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame(
            'total_response_bytes',
            $checks['sitemap_profile_structure']['failure_code']
        );
        self::assertSame(5, $transport->requests[1]->maximumBodyBytes);
        self::assertSame(16, $report->toArray()['counters']['response_bytes']);
    }

    public function testTotalTimeFailureIsInconclusive(): void
    {
        $clock = new SequenceDoctorClock([0.0, 0.0, 61.0]);
        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"']
            ),
        ]);

        $report = $this->doctor(clock: $clock)->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame(Outcome::Inconclusive, $report->publicOutcome());
        self::assertSame('total_time', $checks['root_discovery']['failure_code']);
        self::assertSame('inconclusive', $checks['root_discovery']['outcome']);
    }

    public function testNoncanonicalSitemapBodyFailsCertification(): void
    {
        [$sitemapValue, $sitemapIdentity] = $this->values();
        $noncanonical = json_encode($sitemapValue, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $noncanonicalIdentity = IdentityRepresentation::fromBody($noncanonical);
        $transport = new QueueDoctorTransport([
            $this->response(
                self::ROOT,
                200,
                ['Link' => '<' . self::SITEMAP . '>; rel="index"; type="application/json"']
            ),
            $this->identityResponse(
                self::SITEMAP,
                $noncanonicalIdentity,
                Protocol::M_SITEMAP_PROFILE
            ),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
            new TransportFailure('request_limit', self::SITEMAP),
        ]);

        $report = $this->doctor()->run($this->context(), $transport);
        $checks = $this->checksById($report);

        self::assertSame('fail', $checks['sitemap_profile_structure']['outcome']);
        self::assertSame(
            'noncanonical_json',
            $checks['sitemap_profile_structure']['failure_code']
        );
        self::assertSame('not_tested', $checks['murl_samples']['outcome']);
        self::assertSame($sitemapIdentity->etag, IdentityRepresentation::fromValue($sitemapValue)->etag);
    }

    public function testSameOriginResolutionCoversRelativeAndRejectsFragments(): void
    {
        self::assertSame(
            'https://example.com/a/next?x=1',
            SameOriginUrl::resolve('https://example.com/a/page', 'next?x=1')
        );
        self::assertSame(
            'https://example.com/root/',
            SameOriginUrl::resolve('https://example.com/a/page', '/../root/')
        );
        self::assertNull(
            SameOriginUrl::resolve('https://example.com/a/page', '/next#fragment')
        );
        self::assertTrue(
            SameOriginUrl::isSame('https://EXAMPLE.com/path', 'https://example.com:443/other')
        );
        self::assertFalse(
            SameOriginUrl::isSame('https://example.com/', 'http://example.com/')
        );
    }

    private function doctor(
        ?DoctorLimits $limits = null,
        ?DoctorClock $clock = null
    ): DeploymentDoctor {
        return new DeploymentDoctor(
            $limits ?? new DoctorLimits(),
            $clock ?? new FixedDoctorClock()
        );
    }

    private function context(): DoctorContext
    {
        return new DoctorContext(
            self::ROOT,
            self::SITEMAP,
            '3.0.0-alpha.3',
            str_repeat('a', 64),
            "& ./scripts/validate-live.ps1 -BaseUrl 'https://example.com'",
            true,
            [new ProviderSignal('wordpress_litespeed_cache', 'detected', 'wordpress')]
        );
    }

    /**
     * @return array{array<string, mixed>, IdentityRepresentation, array<string, mixed>, IdentityRepresentation}
     */
    private function values(): array
    {
        $murlValue = [
            'profile' => Protocol::M_URL_PROFILE,
            'canonical_url' => self::CURL,
            'title' => 'Article',
            'content' => 'Exact content',
        ];
        $murlIdentity = IdentityRepresentation::fromValue($murlValue);
        $sitemapValue = [
            'profile' => Protocol::M_SITEMAP_PROFILE,
            'version' => Protocol::M_SITEMAP_VERSION,
            'items' => [[
                'cUrl' => self::CURL,
                'mUrl' => self::MURL,
                'etag' => $murlIdentity->catalogEtag(),
            ]],
        ];

        return [
            $sitemapValue,
            IdentityRepresentation::fromValue($sitemapValue),
            $murlValue,
            $murlIdentity,
        ];
    }

    private function identityResponse(
        string $url,
        IdentityRepresentation $identity,
        string $profile,
        string $canonical = '',
        bool $head = false
    ): ProbeResponse {
        $link = '<' . $profile . '>; rel="profile"';
        if ($canonical !== '') {
            $link = '<' . $canonical . '>; rel="canonical", ' . $link;
        }

        return $this->response(
            $url,
            200,
            [
                'Content-Type' => 'application/json',
                'ETag' => $identity->etag,
                'Content-Digest' => $identity->contentDigest,
                'Content-Length' => (string) strlen($identity->body),
                'Cache-Control' => 'public, max-age=0, no-transform',
                'Vary' => 'Accept-Encoding',
                'Link' => $link,
            ],
            $head ? '' : $identity->body
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function response(
        string $url,
        int $status,
        array $headers = [],
        string $body = ''
    ): ProbeResponse {
        return new ProbeResponse($status, $headers, $body, $url, 0.01);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function checksById(DoctorReport $report): array
    {
        $result = [];
        foreach ($report->toArray()['checks'] as $check) {
            $result[$check['id']] = $check;
        }

        return $result;
    }
}

final class QueueDoctorTransport implements DoctorTransport
{
    /**
     * @var list<ProbeResponse|TransportFailure>
     */
    private array $queue;

    /**
     * @var list<ProbeRequest>
     */
    public array $requests = [];

    /**
     * @param list<ProbeResponse|TransportFailure> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function request(ProbeRequest $request): ProbeResponse|TransportFailure
    {
        $this->requests[] = $request;
        if ($this->queue === []) {
            return new TransportFailure('transport_error', $request->url);
        }

        return array_shift($this->queue);
    }
}

final class FixedDoctorClock implements DoctorClock
{
    public function monotonic(): float
    {
        return 100.0;
    }

    public function timestamp(): string
    {
        return '2026-07-26T12:00:00+00:00';
    }
}

final class SequenceDoctorClock implements DoctorClock
{
    /**
     * @var list<float>
     */
    private array $values;

    /**
     * @param list<float> $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function monotonic(): float
    {
        if (count($this->values) > 1) {
            return array_shift($this->values);
        }

        return $this->values[0] ?? 0.0;
    }

    public function timestamp(): string
    {
        return '2026-07-26T12:00:00+00:00';
    }
}
