<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Serve the Draft-03 M-Sitemap identity representation.
 */
function tct_output_sitemap() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!\TCT\Draft03\ConditionalRequest::isSafeReadMethod($method)) {
        status_header(405);
        header('Allow: GET, HEAD', true);
        exit;
    }

    if (!\TCT\Draft03\AcceptEncoding::identityIsAllowed($_SERVER['HTTP_ACCEPT_ENCODING'] ?? null)) {
        status_header(406);
        header('Vary: Accept-Encoding', true);
        exit;
    }

    if (tct_auth_required() && !tct_auth_ok()) {
        status_header(401);
        header('WWW-Authenticate: Bearer realm="tct"');
        header('Cache-Control: private, no-store', true);
        exit;
    }

    $identity = tct_get_cached_sitemap_identity();
    if ($identity === null) {
        try {
            $identity = tct_build_sitemap_identity();
            tct_set_cached_sitemap_identity($identity);
        } catch (\Throwable $exception) {
            error_log('TCT Draft-03 M-Sitemap build failed: ' . $exception->getMessage());
            status_header(503);
            header('Cache-Control: no-store', true);
            header('Retry-After: 60', true);
            exit;
        }
    }

    $if_none_match = tct_if_none_match_request_value();
    if (
        $if_none_match !== ''
        && \TCT\Draft03\ConditionalRequest::ifNoneMatchMatches($if_none_match, $identity->etag)
    ) {
        status_header(304);
        tct_send_sitemap_identity_headers($identity, false);
        exit;
    }

    status_header(200);
    tct_send_sitemap_identity_headers($identity, true);
    if ($method === 'HEAD') {
        exit;
    }

    echo $identity->body;
    exit;
}

/**
 * Build a catalog from exact current/cached M-URL identity representations.
 */
function tct_build_sitemap_identity() {
    $maximum_items = (int) apply_filters('tct_sitemap_max_items', 10000);
    $maximum_items = max(1, min(100000, $maximum_items));

    $public_types = get_post_types(['public' => true], 'names');
    if (!is_array($public_types)) {
        $public_types = [];
    }
    $excluded_types = apply_filters('tct_sitemap_excluded_post_types', ['attachment', 'product']);
    if (!is_array($excluded_types)) {
        $excluded_types = ['attachment', 'product'];
    }
    $post_types = array_values(array_diff($public_types, $excluded_types));

    $excluded_ids = [];
    foreach (['page_for_posts', 'page_on_front'] as $option) {
        $id = (int) get_option($option, 0);
        if ($id > 0) {
            $excluded_ids[] = $id;
        }
    }
    if (function_exists('wc_get_page_id')) {
        $shop_id = (int) wc_get_page_id('shop');
        if ($shop_id > 0) {
            $excluded_ids[] = $shop_id;
        }
    }

    $query = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'post__not_in' => array_values(array_unique($excluded_ids)),
        'posts_per_page' => $maximum_items + 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
    ];
    $query = apply_filters('tct_sitemap_query_args', $query);
    if (!is_array($query)) {
        throw new \RuntimeException('Sitemap query filter must return an array.');
    }

    // Filters cannot turn the bounded reference implementation into an
    // unbounded or authorization-broadening query.
    $query['post_type'] = $post_types;
    $query['post_status'] = 'publish';
    $query['post__not_in'] = array_values(array_unique($excluded_ids));
    $query['posts_per_page'] = $maximum_items + 1;
    $query['orderby'] = 'ID';
    $query['order'] = 'ASC';
    $query['fields'] = 'ids';
    $query['no_found_rows'] = true;
    $ids = array_values(array_unique(array_map('intval', (array) get_posts($query))));
    sort($ids, SORT_NUMERIC);

    $homepage_post = tct_protocol_homepage_post();
    $homepage_count = $homepage_post !== null ? 1 : 0;
    if (count($ids) + $homepage_count > $maximum_items) {
        throw new \RuntimeException('M-Sitemap exceeds the configured item limit.');
    }

    $items = [];
    if ($homepage_post !== null) {
        $items[] = tct_sitemap_item_for_post($homepage_post);
    }

    foreach ($ids as $post_id) {
        $post = get_post($post_id);
        if (!$post || !tct_post_is_exposable($post)) {
            continue;
        }
        $items[] = tct_sitemap_item_for_post($post);
    }

    $c_urls = array_column($items, 'cUrl');
    $m_urls = array_column($items, 'mUrl');
    if (count($c_urls) !== count(array_unique($c_urls, SORT_STRING))) {
        throw new \RuntimeException('M-Sitemap contains duplicate C-URLs.');
    }
    if (count($m_urls) !== count(array_unique($m_urls, SORT_STRING))) {
        throw new \RuntimeException('M-Sitemap contains duplicate M-URLs.');
    }

    $certified_core = [
        'version' => \TCT\Draft03\Protocol::M_SITEMAP_VERSION,
        'profile' => \TCT\Draft03\Protocol::M_SITEMAP_PROFILE,
        'items' => $items,
    ];
    $value = apply_filters('tct_sitemap_document', $certified_core);
    if (!is_array($value)) {
        throw new \TCT\Draft03\SchemaException('Sitemap document filter must return an object.');
    }
    foreach (['version', 'profile', 'items'] as $member) {
        if (!array_key_exists($member, $value) || $value[$member] !== $certified_core[$member]) {
            throw new \TCT\Draft03\SchemaException(
                'Sitemap document filter must not modify version, profile, or items.'
            );
        }
    }

    $document = \TCT\Draft03\MSitemapDocument::fromArray($value);
    $identity = \TCT\Draft03\IdentityRepresentation::fromValue($document);
    $maximum_bytes = (int) apply_filters('tct_sitemap_max_bytes', 16777216);
    $maximum_bytes = max(1024, min(\TCT\Draft03\Protocol::MAX_IDENTITY_BYTES, $maximum_bytes));
    if (strlen($identity->body) > $maximum_bytes) {
        throw new \RuntimeException('M-Sitemap exceeds the configured byte limit.');
    }

    return $identity;
}

/**
 * Return the one homepage representation used by both catalog and endpoint.
 */
function tct_protocol_homepage_post() {
    $front_id = (int) get_option('page_on_front', 0);
    $post = $front_id > 0 ? get_post($front_id) : tct_create_homepage_pseudo_post();
    return $post && tct_post_is_exposable($post) ? $post : null;
}

/**
 * Build a catalog item only after obtaining its exact identity M-URL ETag.
 */
function tct_sitemap_item_for_post($post) {
    $c_url = tct_c_url_for_post($post);
    if (!is_string($c_url) || $c_url === '') {
        throw new \RuntimeException('Exposable post has no C-URL.');
    }
    $m_url = tct_m_url_for_c_url($c_url);
    $resource_id = isset($post->ID) ? (int) $post->ID : 0;
    $identity = tct_get_cached_identity($resource_id);
    if ($identity === null) {
        [, $identity] = tct_build_murl_identity($post, $c_url, $m_url);
        tct_set_cached_identity($resource_id, $identity);
    }

    $modified_timestamp = isset($post->post_modified_gmt)
        ? strtotime((string) $post->post_modified_gmt . ' UTC')
        : false;
    $modified = $modified_timestamp !== false ? gmdate('c', $modified_timestamp) : null;
    if ($resource_id > 0) {
        $modified = get_post_modified_time('c', true, $post);
    }

    $item = [
        'cUrl' => $c_url,
        'mUrl' => $m_url,
        'etag' => $identity->catalogEtag(),
    ];
    if (is_string($modified) && $modified !== '') {
        $item['lastModified'] = $modified;
    }

    return $item;
}

function tct_send_sitemap_identity_headers($identity, $include_content_headers) {
    if (!($identity instanceof \TCT\Draft03\IdentityRepresentation)) {
        throw new \InvalidArgumentException('Expected a certified sitemap identity representation.');
    }

    if (function_exists('ini_set')) {
        @ini_set('zlib.output_compression', '0');
    }

    $cache_control = tct_auth_required()
        ? 'private, max-age=0, must-revalidate, no-transform'
        : 'public, max-age=300, must-revalidate, stale-if-error=86400, no-transform';
    header('ETag: ' . $identity->etag, true);
    header('Cache-Control: ' . $cache_control, true);
    header('Vary: Accept-Encoding', true);
    header(
        'Link: <' . \TCT\Draft03\Protocol::M_SITEMAP_PROFILE . '>; rel="profile"',
        false
    );

    if ($include_content_headers) {
        header('Content-Type: ' . \TCT\Draft03\Protocol::CONTENT_TYPE, true);
        header('Content-Digest: ' . $identity->contentDigest, true);
        header('Content-Length: ' . strlen($identity->body), true);
    }
}
