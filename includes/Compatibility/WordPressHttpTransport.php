<?php

declare(strict_types=1);

namespace TCT\Compatibility\WordPress;

use Closure;
use TCT\Compatibility\Doctor\DoctorReport;
use TCT\Compatibility\Doctor\DoctorTransport;
use TCT\Compatibility\Doctor\ProbeRequest;
use TCT\Compatibility\Doctor\ProbeResponse;
use TCT\Compatibility\Doctor\TransportFailure;

final class WordPressHttpTransport implements DoctorTransport
{
    private const REQUEST_HEADERS = [
        'Accept',
        'Accept-Encoding',
        'Cache-Control',
        'If-None-Match',
    ];

    private const RESPONSE_HEADERS = [
        'age',
        'allow',
        'cache-control',
        'cf-cache-status',
        'content-digest',
        'content-encoding',
        'content-length',
        'content-type',
        'etag',
        'link',
        'location',
        'server',
        'vary',
        'via',
        'x-cache',
        'x-litespeed-cache',
        'x-turbo-charged-by',
    ];

    private Closure $requester;
    private Closure $urlValidator;

    public function __construct(?Closure $requester = null, ?Closure $urlValidator = null)
    {
        $this->requester = $requester ?? static fn(string $url, array $arguments): mixed =>
            wp_safe_remote_request($url, $arguments);
        $this->urlValidator = $urlValidator ?? static fn(string $url): bool =>
            wp_http_validate_url($url) !== false;
    }

    public function request(ProbeRequest $request): ProbeResponse|TransportFailure
    {
        if (!(($this->urlValidator)($request->url))) {
            return new TransportFailure('unsafe_url', $request->url);
        }

        $headers = [];
        foreach (self::REQUEST_HEADERS as $name) {
            if (!array_key_exists($name, $request->headers)) {
                continue;
            }
            $value = $request->headers[$name];
            if (
                strlen($value) > 4096
                || preg_match('/[\r\n\x00]/D', $value) === 1
            ) {
                return new TransportFailure('invalid_header', $request->url);
            }
            $headers[$name] = $value;
        }

        $arguments = [
            'method' => $request->method,
            'timeout' => $request->timeoutSeconds,
            'redirection' => 0,
            'httpversion' => '1.1',
            'user-agent' => 'TCT-Deployment-Doctor/' . DoctorReport::IMPLEMENTATION_VERSION,
            'reject_unsafe_urls' => true,
            'blocking' => true,
            'headers' => $headers,
            'cookies' => [],
            'compress' => false,
            'decompress' => false,
            'sslverify' => true,
            'stream' => false,
            'limit_response_size' => $request->maximumBodyBytes + 1,
        ];

        $started = hrtime(true);
        $result = ($this->requester)($request->url, $arguments);
        $elapsed = max(0, (hrtime(true) - $started) / 1_000_000_000);

        if ($this->isError($result)) {
            $errorCode = $this->errorCode($result);
            $code = str_contains(strtolower($errorCode), 'timeout')
                ? 'transport_timeout'
                : 'transport_error';

            return new TransportFailure($code, $request->url, $elapsed);
        }
        if (!is_array($result)) {
            return new TransportFailure('transport_error', $request->url, $elapsed);
        }

        $status = $this->responseCode($result);
        $body = $this->responseBody($result);
        if ($status < 100 || $status > 599 || !is_string($body)) {
            return new TransportFailure('transport_error', $request->url, $elapsed);
        }

        $availableHeaders = $this->responseHeaders($result);
        $responseHeaders = [];
        foreach (self::RESPONSE_HEADERS as $name) {
            if (!array_key_exists($name, $availableHeaders)) {
                continue;
            }
            $value = $availableHeaders[$name];
            $responseHeaders[$name] = is_array($value)
                ? implode(', ', array_map('strval', $value))
                : (string) $value;
        }

        return new ProbeResponse(
            $status,
            $responseHeaders,
            $body,
            $request->url,
            $elapsed,
            strlen($body) > $request->maximumBodyBytes
        );
    }

    private function isError(mixed $result): bool
    {
        if (function_exists('is_wp_error')) {
            return is_wp_error($result);
        }

        return is_object($result) && method_exists($result, 'get_error_code');
    }

    private function errorCode(mixed $result): string
    {
        return is_object($result) && method_exists($result, 'get_error_code')
            ? (string) $result->get_error_code()
            : '';
    }

    /**
     * @param array<string, mixed> $result
     */
    private function responseCode(array $result): int
    {
        if (function_exists('wp_remote_retrieve_response_code')) {
            return (int) wp_remote_retrieve_response_code($result);
        }

        return (int) ($result['response']['code'] ?? 0);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function responseBody(array $result): mixed
    {
        if (function_exists('wp_remote_retrieve_body')) {
            return wp_remote_retrieve_body($result);
        }

        return $result['body'] ?? null;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function responseHeaders(array $result): array
    {
        $headers = function_exists('wp_remote_retrieve_headers')
            ? wp_remote_retrieve_headers($result)
            : ($result['headers'] ?? []);

        if ($headers instanceof \Traversable) {
            $headers = iterator_to_array($headers);
        } elseif (is_object($headers) && method_exists($headers, 'getAll')) {
            $headers = $headers->getAll();
        }
        if (!is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = $value;
        }

        return $normalized;
    }
}
