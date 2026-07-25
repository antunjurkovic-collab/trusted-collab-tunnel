<?php

declare(strict_types=1);

namespace TCT\Draft03;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Certified Draft-03 M-Sitemap JSON value.
 */
final class MSitemapDocument implements JsonSerializable
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
        if (($value['profile'] ?? null) !== Protocol::M_SITEMAP_PROFILE) {
            throw new SchemaException('M-Sitemap profile must identify exact Draft-03.');
        }

        if (($value['version'] ?? null) !== Protocol::M_SITEMAP_VERSION) {
            throw new SchemaException('M-Sitemap version must be the integer 2.');
        }

        if (!isset($value['items']) || !is_array($value['items']) || !array_is_list($value['items'])) {
            throw new SchemaException('M-Sitemap items must be a JSON array.');
        }

        foreach ($value['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new SchemaException(sprintf('M-Sitemap item %d must be an object.', $index));
            }

            foreach (['cUrl', 'mUrl'] as $member) {
                if (!isset($item[$member]) || !is_string($item[$member])) {
                    throw new SchemaException(sprintf('M-Sitemap item %d member %s must be a string.', $index, $member));
                }
                WebUri::requireAbsoluteHttp($item[$member], sprintf('items[%d].%s', $index, $member));
            }

            if (
                array_key_exists('etag', $item)
                && (!is_string($item['etag']) || preg_match('/^sha256-[0-9a-f]{64}$/D', $item['etag']) !== 1)
            ) {
                throw new SchemaException(sprintf('M-Sitemap item %d has an invalid ETag hint.', $index));
            }

            if (array_key_exists('lastModified', $item)) {
                if (!is_string($item['lastModified']) || self::isRfc3339($item['lastModified']) === false) {
                    throw new SchemaException(sprintf('M-Sitemap item %d has an invalid lastModified value.', $index));
                }
            }
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

    private static function isRfc3339(string $value): bool
    {
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',
                $value
            ) !== 1
        ) {
            return false;
        }

        try {
            new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            return $errors === false
                || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
        } catch (\Exception) {
            return false;
        }
    }
}
