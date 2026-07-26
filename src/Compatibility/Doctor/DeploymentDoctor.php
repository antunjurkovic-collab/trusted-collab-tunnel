<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

use TCT\Draft03\IdentityRepresentation;
use TCT\Draft03\JcsEncoder;
use TCT\Draft03\MSitemapDocument;
use TCT\Draft03\MUrlDocument;
use TCT\Draft03\Protocol;

/**
 * Internal, non-stable Checkpoint 1 public-path verifier.
 */
final class DeploymentDoctor
{
    private DoctorContext $context;
    private float $startedMonotonic;
    private string $startedAt;
    private int $requestCount = 0;
    private int $redirectCount = 0;
    private int $responseBytes = 0;
    private int $sampleCount = 0;

    /**
     * @var list<DoctorCheck>
     */
    private array $checks = [];

    /**
     * @var list<ProviderSignal>
     */
    private array $signals = [];

    public function __construct(
        private readonly DoctorLimits $limits = new DoctorLimits(),
        private readonly DoctorClock $clock = new SystemDoctorClock(),
        private readonly BoundedGzipDecoder $gzipDecoder = new BoundedGzipDecoder(),
        private readonly BoundedJsonPreflight $jsonPreflight = new BoundedJsonPreflight()
    ) {
    }

    public function run(DoctorContext $context, DoctorTransport $transport): DoctorReport
    {
        $this->reset($context);

        if (!$context->publicResourcesEnabled) {
            $this->addCheck(new DoctorCheck(
                'public_resources',
                Outcome::NotTested,
                $context->sitemapUrl,
                'Publicly accessible core resources',
                'Protected deployment; Checkpoint 1 does not use credentials',
                'protected_deployment'
            ));
            $this->addSignalsCheck();

            return $this->buildReport();
        }

        try {
            $root = $this->perform(
                $transport,
                $context->homeRootUrl,
                'GET',
                ['Accept' => 'text/html', 'Accept-Encoding' => 'identity'],
                3.0
            );

            if ($root instanceof TransportFailure) {
                $this->addTransportCheck('root_discovery', $root);
                $this->addUnavailablePublicChecks('Origin-root request was unavailable');
                $this->addSignalsCheck();

                return $this->buildReport();
            }

            $rootLink = $root->header('link');
            $rootPass = $root->status === 200
                && $this->hasLink($rootLink, $context->sitemapUrl, 'index')
                && stripos($rootLink, 'type="application/json"') !== false;
            $this->addCheck(new DoctorCheck(
                'root_discovery',
                $rootPass ? Outcome::Pass : Outcome::Fail,
                $context->homeRootUrl,
                '200 and generic rel=index application/json link to configured M-Sitemap',
                'status=' . $root->status . '; link=' . $rootLink,
                $rootPass ? '' : 'invalid_header'
            ));

            $sitemap = $this->perform(
                $transport,
                $context->sitemapUrl,
                'GET',
                [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'identity',
                    'Cache-Control' => 'no-cache',
                ]
            );

            if ($sitemap instanceof TransportFailure) {
                $this->addTransportCheck('sitemap_profile_structure', $sitemap);
                $this->addUnavailableSitemapChecks('Identity M-Sitemap was unavailable');
                $this->addSignalsCheck();

                return $this->buildReport();
            }

            $this->collectResponseSignals($sitemap);
            $sitemapCertification = $this->certifyIdentity($sitemap, 'sitemap');
            $sitemapIdentity = IdentityRepresentation::fromBody($sitemap->body);
            $this->evaluateIdentityResponse(
                'sitemap',
                $sitemap,
                $sitemapCertification,
                $sitemapIdentity,
                Protocol::M_SITEMAP_PROFILE,
                ''
            );

            $this->evaluateGzipSelection(
                $transport,
                'sitemap',
                $context->sitemapUrl,
                $sitemap,
                $sitemapIdentity
            );
            $this->evaluateIdentityForbidden($transport, $context->sitemapUrl);
            $this->evaluateConditional(
                $transport,
                'sitemap_conditional',
                $context->sitemapUrl,
                $sitemapIdentity
            );
            $this->evaluateHead(
                $transport,
                'sitemap_head',
                $context->sitemapUrl,
                $sitemap,
                $sitemapIdentity
            );
            $this->evaluateUnsafeMethod($transport, $context->sitemapUrl);
            $this->evaluateRepeated(
                $transport,
                $context->sitemapUrl,
                $sitemap,
                $sitemapIdentity
            );

            if ($sitemapCertification['ok'] === true) {
                /** @var array<string, mixed> $sitemapValue */
                $sitemapValue = $sitemapCertification['value'];
                $this->evaluateMurlSamples(
                    $transport,
                    $sitemapValue['items'],
                    $sitemapIdentity
                );
            } else {
                $this->addCheck(new DoctorCheck(
                    'murl_samples',
                    Outcome::NotTested,
                    $context->sitemapUrl,
                    'Authoritative bounded M-URL samples',
                    'M-Sitemap body could not be certified',
                    'invalid_schema'
                ));
            }
        } catch (\Throwable) {
            $this->addCheck(new DoctorCheck(
                'doctor_internal',
                Outcome::Inconclusive,
                $context->sitemapUrl,
                'Bounded Doctor completion',
                'Internal failure was safely contained',
                'transport_error'
            ));
        }

        $this->addSignalsCheck();

        return $this->buildReport();
    }

    private function reset(DoctorContext $context): void
    {
        $this->context = $context;
        $this->startedMonotonic = $this->clock->monotonic();
        $this->startedAt = $this->clock->timestamp();
        $this->requestCount = 0;
        $this->redirectCount = 0;
        $this->responseBytes = 0;
        $this->sampleCount = 0;
        $this->checks = [];
        $this->signals = array_slice($context->providerSignals, 0, $this->limits->maxSignals);
    }

    /**
     * @param array<string, string> $headers
     */
    private function perform(
        DoctorTransport $transport,
        string $url,
        string $method,
        array $headers,
        ?float $timeout = null
    ): ProbeResponse|TransportFailure {
        $redirects = 0;
        $currentUrl = $url;
        $currentMethod = $method;

        while (true) {
            if (!SameOriginUrl::isSame($this->context->homeRootUrl, $currentUrl)) {
                return new TransportFailure('cross_origin_redirect', $currentUrl);
            }

            if ($this->requestCount >= $this->limits->maxRequests) {
                return new TransportFailure('request_limit', $currentUrl);
            }

            $remainingSeconds = $this->remainingSeconds();
            if ($remainingSeconds <= 0) {
                return new TransportFailure('total_time', $currentUrl);
            }

            $remainingBytes = $this->limits->maxTotalResponseBytes - $this->responseBytes;
            if ($remainingBytes <= 0) {
                return new TransportFailure('total_response_bytes', $currentUrl);
            }

            $requestBodyMaximum = min($this->limits->maxResponseBytes, $remainingBytes);
            $requestTimeout = min(
                $timeout ?? $this->limits->maxRequestSeconds,
                $this->limits->maxRequestSeconds,
                $remainingSeconds
            );

            ++$this->requestCount;
            $result = $transport->request(new ProbeRequest(
                $currentUrl,
                $currentMethod,
                $headers,
                $requestTimeout,
                $requestBodyMaximum
            ));

            if ($result instanceof TransportFailure) {
                return $result;
            }

            $bodyLength = strlen($result->body);
            $this->responseBytes += $bodyLength;
            if ($result->possiblyTruncated || $bodyLength > $requestBodyMaximum) {
                $failureCode = $remainingBytes < $this->limits->maxResponseBytes
                    ? 'total_response_bytes'
                    : 'response_bytes';
                return new TransportFailure(
                    $failureCode,
                    $currentUrl,
                    $result->elapsedSeconds,
                    'Public response exceeded the available byte ceiling'
                );
            }
            if ($this->responseBytes > $this->limits->maxTotalResponseBytes) {
                return new TransportFailure(
                    'total_response_bytes',
                    $currentUrl,
                    $result->elapsedSeconds
                );
            }
            if ($this->remainingSeconds() <= 0) {
                return new TransportFailure(
                    'total_time',
                    $currentUrl,
                    $result->elapsedSeconds
                );
            }

            if (!$this->isRedirect($result->status)) {
                return $result;
            }

            $location = $result->header('location');
            if ($location === '') {
                return $result;
            }
            if ($redirects >= $this->limits->maxRedirectsPerProbe) {
                return new TransportFailure('redirect_limit', $currentUrl);
            }

            $resolved = SameOriginUrl::resolve($currentUrl, $location);
            if (
                $resolved === null
                || !SameOriginUrl::isSame($this->context->homeRootUrl, $resolved)
            ) {
                return new TransportFailure('cross_origin_redirect', $currentUrl);
            }

            ++$redirects;
            ++$this->redirectCount;
            if ($result->status === 303 || (
                in_array($result->status, [301, 302], true)
                && !in_array($currentMethod, ['GET', 'HEAD'], true)
            )) {
                $currentMethod = 'GET';
            }
            $currentUrl = $resolved;
        }
    }

    private function remainingSeconds(): float
    {
        return $this->limits->maxTotalSeconds
            - ($this->clock->monotonic() - $this->startedMonotonic);
    }

    /**
     * @return array{ok: bool, code: string, observed: string, value?: array<string, mixed>}
     */
    private function certifyIdentity(ProbeResponse $response, string $kind): array
    {
        if ($response->status !== 200) {
            return [
                'ok' => false,
                'code' => 'http_status',
                'observed' => 'status=' . $response->status,
            ];
        }

        if (trim($response->header('content-encoding')) !== '') {
            return [
                'ok' => false,
                'code' => 'unexpected_content_coding',
                'observed' => 'content-encoding=' . $response->header('content-encoding'),
            ];
        }

        if (strtolower(trim($response->header('content-type'))) !== Protocol::CONTENT_TYPE) {
            return [
                'ok' => false,
                'code' => 'invalid_header',
                'observed' => 'content-type=' . $response->header('content-type'),
            ];
        }

        if (!mb_check_encoding($response->body, 'UTF-8')) {
            return [
                'ok' => false,
                'code' => 'invalid_utf8',
                'observed' => 'body is not valid UTF-8',
            ];
        }

        $preflight = $this->jsonPreflight->inspect($response->body);
        if ($preflight['ok'] === false) {
            return [
                'ok' => false,
                'code' => $preflight['code'],
                'observed' => 'body exceeds the bounded JSON structural domain',
            ];
        }

        try {
            $value = json_decode(
                $response->body,
                true,
                Protocol::MAX_JSON_DEPTH + 2,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return [
                'ok' => false,
                'code' => 'invalid_json',
                'observed' => 'body is not valid bounded JSON',
            ];
        }

        if (!is_array($value) || array_is_list($value)) {
            return [
                'ok' => false,
                'code' => 'invalid_schema',
                'observed' => 'top-level JSON value is not an object',
            ];
        }

        try {
            if ($kind === 'sitemap') {
                MSitemapDocument::fromArray($value);
                $this->certifySitemapItems($value);
            } else {
                MUrlDocument::fromArray($value);
            }
            $canonical = (new JcsEncoder())->encode($value);
        } catch (\Throwable) {
            return [
                'ok' => false,
                'code' => 'invalid_schema',
                'observed' => 'body failed Draft-03 certification',
            ];
        }

        if (!hash_equals($canonical, $response->body)) {
            return [
                'ok' => false,
                'code' => 'noncanonical_json',
                'observed' => 'body differs from its certified JCS encoding',
            ];
        }

        return [
            'ok' => true,
            'code' => '',
            'observed' => 'certified Draft-03 JCS identity',
            'value' => $value,
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function certifySitemapItems(array $value): void
    {
        $cUrls = [];
        $mUrls = [];
        foreach ($value['items'] as $item) {
            $cUrl = (string) $item['cUrl'];
            $mUrl = (string) $item['mUrl'];
            if (
                !SameOriginUrl::isSame($this->context->homeRootUrl, $cUrl)
                || !SameOriginUrl::isSame($this->context->homeRootUrl, $mUrl)
            ) {
                throw new \DomainException('M-Sitemap item is outside the configured origin.');
            }
            if (isset($cUrls[$cUrl]) || isset($mUrls[$mUrl])) {
                throw new \DomainException('M-Sitemap item URL is duplicated.');
            }
            $cUrls[$cUrl] = true;
            $mUrls[$mUrl] = true;
        }
    }

    /**
     * @param array{ok: bool, code: string, observed: string, value?: array<string, mixed>} $certification
     */
    private function evaluateIdentityResponse(
        string $prefix,
        ProbeResponse $response,
        array $certification,
        IdentityRepresentation $identity,
        string $profile,
        string $canonicalUrl
    ): void {
        $this->addCheck(new DoctorCheck(
            $prefix . '_profile_structure',
            $certification['ok'] ? Outcome::Pass : Outcome::Fail,
            $response->requestUrl,
            '200, exact application/json, valid canonical Draft-03 ' . $prefix,
            $certification['observed'],
            $certification['code']
        ));

        $this->addExactHeaderCheck(
            $prefix . '_etag',
            $response,
            'etag',
            $identity->etag,
            'etag_mismatch'
        );
        $this->addExactHeaderCheck(
            $prefix . '_digest',
            $response,
            'content-digest',
            $identity->contentDigest,
            'digest_mismatch'
        );
        $this->addExactHeaderCheck(
            $prefix . '_length',
            $response,
            'content-length',
            (string) strlen($response->body),
            'length_mismatch'
        );

        $encoding = trim($response->header('content-encoding'));
        $this->addCheck(new DoctorCheck(
            $prefix . '_identity_encoding',
            $encoding === '' ? Outcome::Pass : Outcome::Fail,
            $response->requestUrl,
            'No Content-Encoding for identity selection',
            $encoding === '' ? 'none' : $encoding,
            $encoding === '' ? '' : 'unexpected_content_coding'
        ));

        $varyPass = $this->hasHeaderToken($response->header('vary'), 'accept-encoding');
        $this->addCheck(new DoctorCheck(
            $prefix . '_vary',
            $varyPass ? Outcome::Pass : Outcome::Fail,
            $response->requestUrl,
            'Vary contains Accept-Encoding',
            $response->header('vary'),
            $varyPass ? '' : 'invalid_header'
        ));

        $noTransform = $this->hasHeaderToken(
            $response->header('cache-control'),
            'no-transform'
        );
        $this->addCheck(new DoctorCheck(
            $prefix . '_no_transform',
            $noTransform ? Outcome::Pass : Outcome::Fail,
            $response->requestUrl,
            'Cache-Control contains no-transform',
            $response->header('cache-control'),
            $noTransform ? '' : 'invalid_header'
        ));

        $link = $response->header('link');
        $linksPass = $this->hasLink($link, $profile, 'profile')
            && ($canonicalUrl === '' || $this->hasLink($link, $canonicalUrl, 'canonical'));
        $this->addCheck(new DoctorCheck(
            $prefix . '_links',
            $linksPass ? Outcome::Pass : Outcome::Fail,
            $response->requestUrl,
            $canonicalUrl === ''
                ? 'Exact Draft-03 profile link'
                : 'Exact Draft-03 profile and canonical links',
            $link,
            $linksPass ? '' : 'invalid_header'
        ));
    }

    private function evaluateGzipSelection(
        DoctorTransport $transport,
        string $prefix,
        string $url,
        ProbeResponse $identityResponse,
        IdentityRepresentation $identity
    ): void {
        $response = $this->perform(
            $transport,
            $url,
            'GET',
            [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
                'Cache-Control' => 'no-cache',
            ]
        );

        if ($response instanceof TransportFailure) {
            $this->addTransportCheck($prefix . '_gzip_identity', $response);
            $this->addCheck(new DoctorCheck(
                $prefix . '_gzip_etag',
                Outcome::NotTested,
                $url,
                'Same strong identity ETag',
                'Gzip-advertised response unavailable',
                $response->code
            ));
            return;
        }

        $coding = strtolower(trim($response->header('content-encoding')));
        $sameBytes = $coding === '' && hash_equals($identityResponse->body, $response->body);
        $observed = 'status=' . $response->status
            . '; coding=' . ($coding === '' ? 'identity' : $coding);
        $failureCode = '';

        if ($coding === 'gzip') {
            $decoded = $this->gzipDecoder->decode(
                $response->body,
                $this->limits->maxResponseBytes
            );
            if ($decoded['ok'] === false) {
                $observed .= '; diagnostic_decode=gzip_decode_failed_or_limit';
            } else {
                $observed .= '; decoded_matches_identity='
                    . (hash_equals($identityResponse->body, $decoded['body']) ? 'yes' : 'no');
            }
            $failureCode = 'unexpected_content_coding';
        } elseif ($coding !== '') {
            $failureCode = 'unsupported_content_coding';
        } elseif (!$sameBytes || $response->status !== 200) {
            $failureCode = 'selected_representation_mismatch';
        }

        $this->addCheck(new DoctorCheck(
            $prefix . '_gzip_identity',
            $failureCode === '' ? Outcome::Pass : Outcome::Fail,
            $url,
            'Advertised gzip still selects exact identity 200 response bytes',
            $observed,
            $failureCode
        ));

        $etag = $response->header('etag');
        $etagPass = $etag === $identity->etag && !str_starts_with(strtolower($etag), 'w/');
        $this->addCheck(new DoctorCheck(
            $prefix . '_gzip_etag',
            $etagPass ? Outcome::Pass : Outcome::Fail,
            $url,
            $identity->etag,
            $etag === '' ? 'missing' : $etag,
            $etagPass ? '' : 'etag_mismatch'
        ));
    }

    private function evaluateIdentityForbidden(DoctorTransport $transport, string $url): void
    {
        $response = $this->perform(
            $transport,
            $url,
            'GET',
            ['Accept' => 'application/json', 'Accept-Encoding' => 'identity;q=0']
        );
        if ($response instanceof TransportFailure) {
            $this->addTransportCheck('sitemap_identity_forbidden', $response);
            return;
        }

        $pass = $response->status === 406;
        $this->addCheck(new DoctorCheck(
            'sitemap_identity_forbidden',
            $pass ? Outcome::Pass : Outcome::Fail,
            $url,
            '406',
            'status=' . $response->status,
            $pass ? '' : 'http_status'
        ));
    }

    private function evaluateConditional(
        DoctorTransport $transport,
        string $id,
        string $url,
        IdentityRepresentation $identity
    ): void {
        $response = $this->perform(
            $transport,
            $url,
            'GET',
            [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'If-None-Match' => $identity->etag,
            ]
        );
        if ($response instanceof TransportFailure) {
            $this->addTransportCheck($id, $response);
            return;
        }

        $pass = $response->status === 304
            && $response->header('etag') === $identity->etag
            && $response->body === '';
        $this->addCheck(new DoctorCheck(
            $id,
            $pass ? Outcome::Pass : Outcome::Fail,
            $url,
            '304, current strong ETag, empty body',
            'status=' . $response->status
                . '; etag=' . $response->header('etag')
                . '; bytes=' . strlen($response->body),
            $pass ? '' : 'conditional_mismatch'
        ));
    }

    private function evaluateHead(
        DoctorTransport $transport,
        string $id,
        string $url,
        ProbeResponse $get,
        IdentityRepresentation $identity
    ): void {
        $response = $this->perform(
            $transport,
            $url,
            'HEAD',
            ['Accept' => 'application/json', 'Accept-Encoding' => 'identity']
        );
        if ($response instanceof TransportFailure) {
            $this->addTransportCheck($id, $response);
            return;
        }

        $headers = [
            'content-type',
            'content-digest',
            'content-length',
            'cache-control',
            'vary',
            'link',
        ];
        $matching = $response->status === 200
            && $response->header('etag') === $identity->etag
            && $response->body === '';
        foreach ($headers as $name) {
            $matching = $matching && $response->header($name) === $get->header($name);
        }

        $this->addCheck(new DoctorCheck(
            $id,
            $matching ? Outcome::Pass : Outcome::Fail,
            $url,
            'HEAD 200 with empty body and selected GET metadata',
            'status=' . $response->status
                . '; etag=' . $response->header('etag')
                . '; bytes=' . strlen($response->body),
            $matching ? '' : 'head_mismatch'
        ));
    }

    private function evaluateUnsafeMethod(DoctorTransport $transport, string $url): void
    {
        $response = $this->perform(
            $transport,
            $url,
            'POST',
            ['Accept' => 'application/json', 'Accept-Encoding' => 'identity']
        );
        if ($response instanceof TransportFailure) {
            $this->addTransportCheck('sitemap_unsafe_method', $response);
            return;
        }

        $pass = $response->status === 405
            && $this->hasHeaderToken($response->header('allow'), 'get')
            && $this->hasHeaderToken($response->header('allow'), 'head');
        $this->addCheck(new DoctorCheck(
            'sitemap_unsafe_method',
            $pass ? Outcome::Pass : Outcome::Fail,
            $url,
            '405 and Allow: GET, HEAD',
            'status=' . $response->status . '; allow=' . $response->header('allow'),
            $pass ? '' : 'http_status'
        ));
    }

    private function evaluateRepeated(
        DoctorTransport $transport,
        string $url,
        ProbeResponse $first,
        IdentityRepresentation $identity
    ): void {
        $response = $this->perform(
            $transport,
            $url,
            'GET',
            [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'Cache-Control' => 'no-cache',
            ]
        );
        if ($response instanceof TransportFailure) {
            $this->addTransportCheck('sitemap_repeated_stability', $response);
            return;
        }

        $pass = $response->status === 200
            && hash_equals($first->body, $response->body)
            && $response->header('etag') === $identity->etag
            && $response->header('content-digest') === $identity->contentDigest;
        $this->addCheck(new DoctorCheck(
            'sitemap_repeated_stability',
            $pass ? Outcome::Pass : Outcome::Fail,
            $url,
            'Repeated identity bytes and validators remain exact',
            'status=' . $response->status
                . '; etag=' . $response->header('etag')
                . '; bytes=' . strlen($response->body),
            $pass ? '' : 'stability_mismatch'
        ));
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function evaluateMurlSamples(
        DoctorTransport $transport,
        array $items,
        IdentityRepresentation $sitemapIdentity
    ): void {
        unset($sitemapIdentity);
        $selected = [];
        foreach ($items as $item) {
            $mUrl = (string) $item['mUrl'];
            if (isset($selected[$mUrl])) {
                continue;
            }
            $selected[$mUrl] = $item;
            if (count($selected) >= $this->limits->maxSampledMurls) {
                break;
            }
        }

        $this->sampleCount = count($selected);
        if ($selected === []) {
            $this->addCheck(new DoctorCheck(
                'murl_samples',
                Outcome::Pass,
                $this->context->sitemapUrl,
                'All available bounded M-URL samples',
                'No M-URL items were available',
                '',
                'public_delivery_path',
                'observed',
                '',
                'Run Deployment Doctor again after content is published.'
            ));
            return;
        }

        $index = 0;
        foreach ($selected as $mUrl => $item) {
            ++$index;
            $prefix = 'murl_' . $index;
            $response = $this->perform(
                $transport,
                $mUrl,
                'GET',
                ['Accept' => 'application/json', 'Accept-Encoding' => 'identity']
            );
            if ($response instanceof TransportFailure) {
                $this->addTransportCheck($prefix . '_profile_structure', $response);
                $this->addCheck(new DoctorCheck(
                    $prefix . '_dependent',
                    Outcome::NotTested,
                    $mUrl,
                    'M-URL validators, links, hint, conditional, and HEAD',
                    'Identity M-URL was unavailable',
                    $response->code
                ));
                continue;
            }

            $certification = $this->certifyIdentity($response, 'murl');
            $identity = IdentityRepresentation::fromBody($response->body);
            $canonicalUrl = '';
            if ($certification['ok'] === true) {
                /** @var array<string, mixed> $value */
                $value = $certification['value'];
                $canonicalUrl = (string) $value['canonical_url'];
            }
            $this->evaluateIdentityResponse(
                $prefix,
                $response,
                $certification,
                $identity,
                Protocol::M_URL_PROFILE,
                $canonicalUrl
            );

            $hint = (string) ($item['etag'] ?? '');
            $hintPass = $hint === '' || $hint === $identity->catalogEtag();
            $this->addCheck(new DoctorCheck(
                $prefix . '_catalog_hint',
                $hintPass ? Outcome::Pass : Outcome::Fail,
                $mUrl,
                'Optional catalog hint equals authoritative identity ETag',
                $hint === '' ? 'hint absent' : $hint,
                $hintPass ? '' : 'etag_mismatch'
            ));
            $this->evaluateConditional(
                $transport,
                $prefix . '_conditional',
                $mUrl,
                $identity
            );
            $this->evaluateHead(
                $transport,
                $prefix . '_head',
                $mUrl,
                $response,
                $identity
            );

            if ($index === 1) {
                $this->evaluateGzipSelection(
                    $transport,
                    $prefix,
                    $mUrl,
                    $response,
                    $identity
                );
            }
        }
    }

    /**
     * @param array{ok: bool, code: string, observed: string, value?: array<string, mixed>} $certification
     */
    private function addExactHeaderCheck(
        string $id,
        ProbeResponse $response,
        string $header,
        string $expected,
        string $failureCode
    ): void {
        $observed = $response->header($header);
        $pass = hash_equals($expected, $observed);
        $this->addCheck(new DoctorCheck(
            $id,
            $pass ? Outcome::Pass : Outcome::Fail,
            $response->requestUrl,
            $expected,
            $observed === '' ? 'missing' : $observed,
            $pass ? '' : $failureCode
        ));
    }

    private function addTransportCheck(string $id, TransportFailure $failure): void
    {
        $outcome = in_array(
            $failure->code,
            [
                'cross_origin_redirect',
                'redirect_limit',
                'response_bytes',
                'total_response_bytes',
            ],
            true
        ) ? Outcome::Fail : Outcome::Inconclusive;

        $this->addCheck(new DoctorCheck(
            $id,
            $outcome,
            $failure->requestUrl,
            'Successful bounded same-origin public response',
            $failure->detail === '' ? $failure->code : $failure->detail,
            $failure->code
        ));
    }

    private function addUnavailablePublicChecks(string $reason): void
    {
        foreach ([
            'sitemap_profile_structure',
            'sitemap_etag',
            'sitemap_digest',
            'sitemap_length',
            'sitemap_identity_encoding',
            'sitemap_vary',
            'sitemap_no_transform',
            'sitemap_links',
            'sitemap_gzip_identity',
            'sitemap_gzip_etag',
            'sitemap_identity_forbidden',
            'sitemap_conditional',
            'sitemap_head',
            'sitemap_unsafe_method',
            'sitemap_repeated_stability',
            'murl_samples',
        ] as $id) {
            $this->addCheck(new DoctorCheck(
                $id,
                Outcome::NotTested,
                $this->context->sitemapUrl,
                'Required public-path check',
                $reason,
                'loopback_unavailable'
            ));
        }
    }

    private function addUnavailableSitemapChecks(string $reason): void
    {
        foreach ([
            'sitemap_etag',
            'sitemap_digest',
            'sitemap_length',
            'sitemap_identity_encoding',
            'sitemap_vary',
            'sitemap_no_transform',
            'sitemap_links',
            'sitemap_gzip_identity',
            'sitemap_gzip_etag',
            'sitemap_identity_forbidden',
            'sitemap_conditional',
            'sitemap_head',
            'sitemap_unsafe_method',
            'sitemap_repeated_stability',
            'murl_samples',
        ] as $id) {
            $this->addCheck(new DoctorCheck(
                $id,
                Outcome::NotTested,
                $this->context->sitemapUrl,
                'Required M-Sitemap-dependent check',
                $reason,
                'transport_error'
            ));
        }
    }

    private function addSignalsCheck(): void
    {
        $this->addCheck(new DoctorCheck(
            'provider_signals',
            Outcome::NotTested,
            $this->context->homeRootUrl,
            'Diagnostic observations only; detection is never conformance',
            count($this->signals) . ' allowlisted signal(s)',
            '',
            'wordpress_cache_integration',
            'inference',
            '',
            'Rerun after any delivery-path configuration change.',
            false
        ));
    }

    private function collectResponseSignals(ProbeResponse $response): void
    {
        $allowlist = [
            'server',
            'via',
            'age',
            'cf-cache-status',
            'x-cache',
            'x-litespeed-cache',
            'x-turbo-charged-by',
            'content-encoding',
        ];

        foreach ($allowlist as $header) {
            $value = $response->header($header);
            if ($value === '') {
                continue;
            }
            $this->addSignal(new ProviderSignal(
                'response_' . str_replace('-', '_', $header),
                $value,
                'public_response'
            ));
        }
    }

    private function addSignal(ProviderSignal $signal): void
    {
        if (count($this->signals) >= $this->limits->maxSignals) {
            return;
        }
        foreach ($this->signals as $existing) {
            if ($existing->id === $signal->id && $existing->value === $signal->value) {
                return;
            }
        }
        $this->signals[] = $signal;
    }

    private function addCheck(DoctorCheck $check): void
    {
        if (count($this->checks) < $this->limits->maxChecks) {
            $this->checks[] = $check;
        }
    }

    private function buildReport(): DoctorReport
    {
        $publicOutcome = $this->derivePublicOutcome();

        return new DoctorReport(
            $this->context->pluginVersion,
            $this->context->routeSignature,
            (string) SameOriginUrl::origin($this->context->homeRootUrl),
            $this->context->sitemapUrl,
            $this->startedAt,
            $this->clock->timestamp(),
            $this->limits,
            [
                'requests' => $this->requestCount,
                'redirects' => $this->redirectCount,
                'response_bytes' => $this->responseBytes,
                'sampled_murls' => $this->sampleCount,
                'elapsed_seconds' => max(
                    0,
                    round($this->clock->monotonic() - $this->startedMonotonic, 6)
                ),
            ],
            [
                'origin_implementation' => new LayerResult(
                    Outcome::NotTested,
                    'Checkpoint 1 has no independently observed origin-only bypass.'
                ),
                'wordpress_cache_integration' => new LayerResult(
                    Outcome::NotTested,
                    'Provider detection is diagnostic and no cache adapter is active.'
                ),
                'public_delivery_path' => new LayerResult(
                    $publicOutcome,
                    $this->publicDetail($publicOutcome)
                ),
            ],
            $this->checks,
            $this->signals,
            $this->context->externalValidatorCommand
        );
    }

    private function derivePublicOutcome(): Outcome
    {
        $mandatory = array_filter(
            $this->checks,
            static fn(DoctorCheck $check): bool => $check->mandatory
        );

        foreach ([Outcome::Fail, Outcome::Inconclusive, Outcome::NotTested] as $outcome) {
            foreach ($mandatory as $check) {
                if ($check->outcome === $outcome) {
                    return $outcome;
                }
            }
        }

        return $mandatory === [] ? Outcome::NotTested : Outcome::Pass;
    }

    private function publicDetail(Outcome $outcome): string
    {
        return match ($outcome) {
            Outcome::Pass => 'All mandatory bounded public-path checks passed.',
            Outcome::Fail => 'At least one observed mandatory public-path check failed.',
            Outcome::Inconclusive => 'The public path could not be completely observed.',
            Outcome::NotTested => 'One or more mandatory public-path checks were not run.',
            Outcome::Stale => 'Stored public-path evidence no longer matches current settings.',
        };
    }

    private function hasHeaderToken(string $value, string $token): bool
    {
        foreach (explode(',', strtolower($value)) as $candidate) {
            if (trim(explode(';', $candidate, 2)[0]) === strtolower($token)) {
                return true;
            }
        }

        return false;
    }

    private function hasLink(string $value, string $uri, string $relation): bool
    {
        $pattern = '/<' . preg_quote($uri, '/') . '>\s*;[^,]*\brel=(?:"[^"]*\b'
            . preg_quote($relation, '/') . '\b[^"]*"|'
            . preg_quote($relation, '/') . ')(?:;|,|$)/i';

        return preg_match($pattern, $value) === 1;
    }

    private function isRedirect(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }
}
