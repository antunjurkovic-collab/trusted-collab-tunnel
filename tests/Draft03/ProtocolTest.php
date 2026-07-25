<?php

declare(strict_types=1);

namespace TCT\Tests\Draft03;

use PHPUnit\Framework\TestCase;
use TCT\Draft03\Protocol;

final class ProtocolTest extends TestCase
{
    public function testRevisionSpecificProfileUrisAreExact(): void
    {
        self::assertSame(
            'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-url-profile',
            Protocol::M_URL_PROFILE
        );
        self::assertSame(
            'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-profile',
            Protocol::M_SITEMAP_PROFILE
        );
        self::assertSame(
            'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-index-profile',
            Protocol::M_SITEMAP_INDEX_PROFILE
        );
    }
}
