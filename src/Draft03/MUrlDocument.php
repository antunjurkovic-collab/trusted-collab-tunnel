<?php

declare(strict_types=1);

namespace TCT\Draft03;

use JsonSerializable;

/**
 * Certified Draft-03 M-URL JSON value.
 */
final class MUrlDocument implements JsonSerializable
{
    /**
     * @param array<string, mixed> $value
     */
    private function __construct(private readonly array $value)
    {
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function fromArray(array $value): self
    {
        if (($value['profile'] ?? null) !== Protocol::M_URL_PROFILE) {
            throw new SchemaException('M-URL profile must identify exact Draft-03.');
        }

        foreach (['canonical_url', 'title', 'content'] as $member) {
            if (!array_key_exists($member, $value) || !is_string($value[$member])) {
                throw new SchemaException(sprintf('M-URL member %s must be a string.', $member));
            }
        }

        WebUri::requireAbsoluteHttp($value['canonical_url'], 'canonical_url');

        if (
            array_key_exists('language', $value)
            && (
                !is_string($value['language'])
                || !LanguageTag::isWellFormed($value['language'])
            )
        ) {
            throw new SchemaException('language must be a well-formed BCP 47 language tag when present.');
        }

        if (
            array_key_exists('content_media_type', $value)
            && (
                !is_string($value['content_media_type'])
                || !MediaType::isWellFormed($value['content_media_type'])
            )
        ) {
            throw new SchemaException('content_media_type must be a well-formed media type when present.');
        }

        return new self($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->value;
    }
}
