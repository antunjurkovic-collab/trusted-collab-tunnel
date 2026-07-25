<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use PHPUnit\Framework\TestCase;
use TCT\Draft03\IdentityRepresentation;
use TCT\Draft03\Protocol;

final class IdentityRepresentationTest extends TestCase
{
    public function testDraft03AppendixCTestVector(): void
    {
        $representation = IdentityRepresentation::fromValue([
            'profile' => Protocol::M_URL_PROFILE,
            'canonical_url' => 'https://example.com/post/',
            'title' => 'Example',
            'content' => 'Hello',
        ]);

        self::assertSame(
            '{"canonical_url":"https://example.com/post/","content":"Hello","profile":"https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-url-profile","title":"Example"}',
            $representation->body
        );
        self::assertSame(
            '"sha256-d5a54a6e2a2ee0f0b84592d4d6c9f5361aeea41e4cb23550f4804e3ea29eaa2c"',
            $representation->etag
        );
        self::assertSame(
            'sha256-d5a54a6e2a2ee0f0b84592d4d6c9f5361aeea41e4cb23550f4804e3ea29eaa2c',
            $representation->catalogEtag()
        );
        self::assertSame(
            'sha-256=:1aVKbiou4PC4RZLU1sn1NhrupB5MsjVQ9IBOPqKeqiw=:',
            $representation->contentDigest
        );
    }
}
