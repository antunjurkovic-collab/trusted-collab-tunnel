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

    public const MAX_JSON_DEPTH = 64;

    public const MAX_JSON_NODES = 100000;

    public const MAX_JSON_KEY_BYTES = 16384;

    public const MAX_JSON_STRING_BYTES = 4194304;

    public const MAX_IDENTITY_BYTES = 16777216;

    public const MAX_SOURCE_BYTES = 8388608;

    public const MAX_EXTRACTED_ITEMS = 10000;

    private function __construct()
    {
    }
}
