<?php

declare(strict_types=1);

namespace TCT\Draft03;

final class Protocol
{
    public const M_URL_PROFILE = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-url-profile';

    public const M_SITEMAP_PROFILE = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-profile';

    public const M_SITEMAP_INDEX_PROFILE = 'https://www.ietf.org/archive/id/draft-jurkovikj-collab-tunnel-03.html#tct-m-sitemap-index-profile';

    public const M_SITEMAP_VERSION = 2;

    public const M_SITEMAP_INDEX_VERSION = 1;

    public const CONTENT_TYPE = 'application/json';

    public const CACHE_NAMESPACE = 'tct_v03_alpha2';

    private function __construct()
    {
    }
}
