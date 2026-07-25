<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Versioned cache epoch. Bumping it invalidates every identity representation
 * without enumerating or mutating historical transient rows.
 */
function tct_cache_epoch() {
    $epoch = (int) get_option('tct_v03_cache_epoch', 1);
    return max(1, $epoch);
}

function tct_bump_cache_epoch() {
    $next = tct_cache_epoch() + 1;
    update_option('tct_v03_cache_epoch', $next, false);
    return $next;
}

function tct_identity_cache_key($resource_id) {
    return \TCT\Draft03\Protocol::CACHE_NAMESPACE
        . '_identity_'
        . tct_cache_epoch()
        . '_'
        . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $resource_id);
}

function tct_sitemap_cache_key() {
    return \TCT\Draft03\Protocol::CACHE_NAMESPACE . '_sitemap_' . tct_cache_epoch();
}

/**
 * @return \TCT\Draft03\IdentityRepresentation|null
 */
function tct_get_cached_identity($resource_id) {
    $cached = get_transient(tct_identity_cache_key($resource_id));
    if (!is_array($cached) || !isset($cached['body'], $cached['etag'])) {
        return null;
    }

    if (!is_string($cached['body']) || !is_string($cached['etag'])) {
        return null;
    }

    $identity = \TCT\Draft03\IdentityRepresentation::fromBody($cached['body']);
    if (!hash_equals($identity->etag, $cached['etag'])) {
        delete_transient(tct_identity_cache_key($resource_id));
        return null;
    }

    return $identity;
}

function tct_set_cached_identity($resource_id, $identity) {
    if (!($identity instanceof \TCT\Draft03\IdentityRepresentation)) {
        throw new InvalidArgumentException('Expected a certified identity representation.');
    }

    $ttl = (int) apply_filters('tct_identity_cache_ttl', HOUR_IN_SECONDS, $resource_id);
    set_transient(
        tct_identity_cache_key($resource_id),
        [
            'body' => $identity->body,
            'etag' => $identity->etag,
        ],
        max(1, $ttl)
    );
}

function tct_delete_cached_identity($resource_id) {
    delete_transient(tct_identity_cache_key($resource_id));
}

/**
 * @return \TCT\Draft03\IdentityRepresentation|null
 */
function tct_get_cached_sitemap_identity() {
    $cached = get_transient(tct_sitemap_cache_key());
    if (!is_array($cached) || !isset($cached['body'], $cached['etag'])) {
        return null;
    }

    if (!is_string($cached['body']) || !is_string($cached['etag'])) {
        return null;
    }

    $identity = \TCT\Draft03\IdentityRepresentation::fromBody($cached['body']);
    if (!hash_equals($identity->etag, $cached['etag'])) {
        delete_transient(tct_sitemap_cache_key());
        return null;
    }

    return $identity;
}

function tct_set_cached_sitemap_identity($identity) {
    if (!($identity instanceof \TCT\Draft03\IdentityRepresentation)) {
        throw new InvalidArgumentException('Expected a certified sitemap identity representation.');
    }

    $ttl = (int) apply_filters('tct_sitemap_cache_ttl', HOUR_IN_SECONDS);
    set_transient(
        tct_sitemap_cache_key(),
        [
            'body' => $identity->body,
            'etag' => $identity->etag,
        ],
        max(1, $ttl)
    );
}

function tct_delete_cached_sitemap_identity() {
    delete_transient(tct_sitemap_cache_key());
}
