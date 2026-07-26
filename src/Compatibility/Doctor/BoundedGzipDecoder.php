<?php

declare(strict_types=1);

namespace TCT\Compatibility\Doctor;

final class BoundedGzipDecoder
{
    /**
     * @return array{ok: bool, code: string, body: string}
     */
    public function decode(string $codedBody, int $maximumDecodedBytes): array
    {
        if ($maximumDecodedBytes < 1) {
            return ['ok' => false, 'code' => 'gzip_decode_failed_or_limit', 'body' => ''];
        }

        $decoded = @gzdecode($codedBody, $maximumDecodedBytes + 1);
        if (
            !is_string($decoded)
            || strlen($decoded) > $maximumDecodedBytes
        ) {
            return ['ok' => false, 'code' => 'gzip_decode_failed_or_limit', 'body' => ''];
        }

        return ['ok' => true, 'code' => '', 'body' => $decoded];
    }
}
