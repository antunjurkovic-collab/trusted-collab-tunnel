<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use PHPUnit\Framework\TestCase;
use TCT\Draft03\LanguageTag;
use TCT\Draft03\MediaType;
use TCT\Draft03\MSitemapDocument;
use TCT\Draft03\MUrlDocument;
use TCT\Draft03\Protocol;
use TCT\Draft03\SchemaException;

final class DocumentTest extends TestCase
{
    public function testWellFormedLanguageTags(): void
    {
        foreach (['en', 'sr-Latn-RS', 'zh-cmn-Hans-CN', 'de-CH-1901', 'x-internal'] as $tag) {
            self::assertTrue(LanguageTag::isWellFormed($tag), $tag);
        }

        foreach (['', 'e', 'en_', 'en--US', 'en-US-'] as $tag) {
            self::assertFalse(LanguageTag::isWellFormed($tag), $tag);
        }
    }

    public function testWellFormedMediaTypes(): void
    {
        foreach (
            ['text/plain', 'text/plain; charset=utf-8', 'text/markdown; variant="GFM"']
            as $mediaType
        ) {
            self::assertTrue(MediaType::isWellFormed($mediaType), $mediaType);
        }

        foreach (['', 'text', 'text/plain;', 'text/plain; charset'] as $mediaType) {
            self::assertFalse(MediaType::isWellFormed($mediaType), $mediaType);
        }
    }

    public function testMUrlPreservesCertifiedCoreAndUnknownMembers(): void
    {
        $document = MUrlDocument::fromArray([
            'profile' => Protocol::M_URL_PROFILE,
            'canonical_url' => 'https://example.com/post/',
            'title' => 'Example',
            'content' => 'Hello',
            'extension' => ['enabled' => true],
        ]);

        self::assertSame('Hello', $document->jsonSerialize()['content']);
        self::assertSame(['enabled' => true], $document->jsonSerialize()['extension']);
    }

    public function testMUrlRejectsShortLegacyProfile(): void
    {
        $this->expectException(SchemaException::class);
        MUrlDocument::fromArray([
            'profile' => 'tct-1',
            'canonical_url' => 'https://example.com/post/',
            'title' => 'Example',
            'content' => 'Hello',
        ]);
    }

    public function testMUrlRejectsNonRfc3986WebUri(): void
    {
        $this->expectException(SchemaException::class);
        MUrlDocument::fromArray([
            'profile' => Protocol::M_URL_PROFILE,
            'canonical_url' => 'https://example.com/raw space/',
            'title' => 'Example',
            'content' => 'Hello',
        ]);
    }

    public function testSitemapCertifiesItemHints(): void
    {
        $document = MSitemapDocument::fromArray([
            'version' => 2,
            'profile' => Protocol::M_SITEMAP_PROFILE,
            'items' => [[
                'cUrl' => 'https://example.com/post/',
                'mUrl' => 'https://example.com/post/llm/',
                'etag' => 'sha256-' . str_repeat('a', 64),
                'lastModified' => '2026-07-23T09:00:00Z',
            ]],
        ]);

        self::assertCount(1, $document->jsonSerialize()['items']);
    }

    public function testSitemapRejectsQuotedHint(): void
    {
        $this->expectException(SchemaException::class);
        MSitemapDocument::fromArray([
            'version' => 2,
            'profile' => Protocol::M_SITEMAP_PROFILE,
            'items' => [[
                'cUrl' => 'https://example.com/post/',
                'mUrl' => 'https://example.com/post/llm/',
                'etag' => '"sha256-' . str_repeat('a', 64) . '"',
            ]],
        ]);
    }

    public function testSitemapRejectsIndexMember(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('must not contain');
        MSitemapDocument::fromArray([
            'version' => 2,
            'profile' => Protocol::M_SITEMAP_PROFILE,
            'items' => [],
            'sitemaps' => [],
        ]);
    }

    public function testSitemapRejectsImpossibleRfc3339Date(): void
    {
        $this->expectException(SchemaException::class);
        MSitemapDocument::fromArray([
            'version' => 2,
            'profile' => Protocol::M_SITEMAP_PROFILE,
            'items' => [[
                'cUrl' => 'https://example.com/post/',
                'mUrl' => 'https://example.com/post/llm/',
                'lastModified' => '2026-02-30T09:00:00Z',
            ]],
        ]);
    }
}
