<?php

declare(strict_types=1);

namespace TCT\Draft03;

final readonly class IdentityRepresentation
{
    private function __construct(
        public string $body,
        public string $etag,
        public string $contentDigest
    ) {
    }

    public static function fromValue(mixed $value, ?JcsEncoder $encoder = null): self
    {
        $body = ($encoder ?? new JcsEncoder())->encode($value);
        $binaryDigest = hash('sha256', $body, true);
        $opaqueTag = 'sha256-' . bin2hex($binaryDigest);

        return new self(
            $body,
            '"' . $opaqueTag . '"',
            'sha-256=:' . base64_encode($binaryDigest) . ':'
        );
    }

    public function catalogEtag(): string
    {
        return trim($this->etag, '"');
    }
}
