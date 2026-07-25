<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }
    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$arguments): mixed
        {
            $callback = $GLOBALS['tct_test_filters'][$hook] ?? null;
            return is_callable($callback) ? $callback($value, ...$arguments) : $value;
        }
    }
    if (!function_exists('get_option')) {
        function get_option(string $name, mixed $default = false): mixed
        {
            return $GLOBALS['tct_test_options'][$name] ?? $default;
        }
    }
    if (!function_exists('update_option')) {
        function update_option(string $name, mixed $value, mixed $autoload = null): bool
        {
            unset($autoload);
            $GLOBALS['tct_test_options'][$name] = $value;
            return true;
        }
    }
    if (!function_exists('get_transient')) {
        function get_transient(string $name): mixed
        {
            return $GLOBALS['tct_test_transients'][$name] ?? false;
        }
    }
    if (!function_exists('set_transient')) {
        function set_transient(string $name, mixed $value, int $expiration): bool
        {
            unset($expiration);
            $GLOBALS['tct_test_transients'][$name] = $value;
            return true;
        }
    }
    if (!function_exists('delete_transient')) {
        function delete_transient(string $name): bool
        {
            unset($GLOBALS['tct_test_transients'][$name]);
            return true;
        }
    }
    if (!function_exists('get_post_types')) {
        function get_post_types(array $arguments, string $output): array
        {
            unset($arguments, $output);
            return ['post', 'page'];
        }
    }
    if (!function_exists('get_posts')) {
        function get_posts(array $arguments): array
        {
            $GLOBALS['tct_test_last_query'] = $arguments;
            return $GLOBALS['tct_test_post_ids'] ?? [];
        }
    }
    if (!function_exists('get_post')) {
        function get_post(int $post_id): ?object
        {
            return $GLOBALS['tct_test_posts'][$post_id] ?? null;
        }
    }
    if (!function_exists('get_permalink')) {
        function get_permalink(int $post_id): string|false
        {
            return $GLOBALS['tct_test_permalinks'][$post_id] ?? false;
        }
    }
    if (!function_exists('get_post_modified_time')) {
        function get_post_modified_time(
            string $format,
            bool $gmt,
            object|int $post
        ): string {
            unset($format, $gmt);
            $post = is_int($post) ? get_post($post) : $post;
            return $post->modified_rfc3339;
        }
    }
    if (!function_exists('home_url')) {
        function home_url(string $path = ''): string
        {
            return 'https://example.com' . ($path === '' ? '' : '/' . ltrim($path, '/'));
        }
    }
    if (!function_exists('trailingslashit')) {
        function trailingslashit(string $value): string
        {
            return rtrim($value, '/') . '/';
        }
    }
    if (!function_exists('sanitize_title')) {
        function sanitize_title(string $value): string
        {
            return trim(strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $value)), '-');
        }
    }
    if (!function_exists('is_post_publicly_viewable')) {
        function is_post_publicly_viewable(object $post): bool
        {
            unset($post);
            return (bool) ($GLOBALS['tct_test_publicly_viewable'] ?? true);
        }
    }
    if (!function_exists('wc_get_page_id')) {
        function wc_get_page_id(string $page): int
        {
            unset($page);
            return 0;
        }
    }

    require_once dirname(__DIR__, 2) . '/includes/Hashing.php';
    require_once dirname(__DIR__, 2) . '/includes/Auth.php';
    require_once dirname(__DIR__, 2) . '/includes/Cache.php';
    require_once dirname(__DIR__, 2) . '/includes/Sitemap.php';
}

namespace TCT\Tests\WordPress {

    use PHPUnit\Framework\TestCase;
    use TCT\Draft03\IdentityRepresentation;
    use TCT\Draft03\MUrlDocument;
    use TCT\Draft03\Protocol;

    final class SitemapIdentityTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['tct_test_options'] = [
                'page_on_front' => 1,
                'page_for_posts' => 0,
                'tct_endpoint_slug' => 'llm',
                'tct_v03_cache_epoch' => 1,
            ];
            $GLOBALS['tct_test_filters'] = [
                'tct_sitemap_max_items' => static fn(int $default): int => 2,
            ];
            $GLOBALS['tct_test_transients'] = [];
            $GLOBALS['tct_test_post_ids'] = [2];
            $GLOBALS['tct_test_posts'] = [
                1 => $this->post(1, 'page'),
                2 => $this->post(2, 'post'),
            ];
            $GLOBALS['tct_test_permalinks'] = [
                1 => 'https://example.com/',
                2 => 'https://example.com/post/',
            ];
            $GLOBALS['tct_test_publicly_viewable'] = true;

            \tct_set_cached_identity(1, $this->identity('https://example.com/', 'Home'));
            \tct_set_cached_identity(2, $this->identity('https://example.com/post/', 'Post'));
        }

        public function testCatalogHintsComeFromExactCachedIdentityRepresentations(): void
        {
            $homeIdentity = \tct_get_cached_identity(1);
            $postIdentity = \tct_get_cached_identity(2);
            $sitemap = \tct_build_sitemap_identity();
            $value = json_decode($sitemap->body, true, flags: JSON_THROW_ON_ERROR);

            self::assertSame(Protocol::M_SITEMAP_VERSION, $value['version']);
            self::assertSame(Protocol::M_SITEMAP_PROFILE, $value['profile']);
            self::assertSame(
                [
                    [
                        'cUrl' => 'https://example.com/',
                        'etag' => $homeIdentity->catalogEtag(),
                        'lastModified' => '2026-07-23T09:00:00+00:00',
                        'mUrl' => 'https://example.com/llm/',
                    ],
                    [
                        'cUrl' => 'https://example.com/post/',
                        'etag' => $postIdentity->catalogEtag(),
                        'lastModified' => '2026-07-23T09:00:00+00:00',
                        'mUrl' => 'https://example.com/post/llm/',
                    ],
                ],
                $value['items']
            );
            self::assertSame(
                '"sha256-' . hash('sha256', $sitemap->body) . '"',
                $sitemap->etag
            );
            self::assertSame(3, $GLOBALS['tct_test_last_query']['posts_per_page']);
        }

        public function testCorruptBodyValidatorPairIsDiscarded(): void
        {
            $key = \tct_identity_cache_key(2);
            $GLOBALS['tct_test_transients'][$key]['body'] .= ' ';

            self::assertNull(\tct_get_cached_identity(2));
            self::assertArrayNotHasKey($key, $GLOBALS['tct_test_transients']);
        }

        public function testCatalogItemLimitFailsClosed(): void
        {
            $GLOBALS['tct_test_filters']['tct_sitemap_max_items'] =
                static fn(int $default): int => 1;

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('item limit');
            \tct_build_sitemap_identity();
        }

        private function post(int $id, string $type): object
        {
            return (object) [
                'ID' => $id,
                'post_type' => $type,
                'post_status' => 'publish',
                'post_password' => '',
                'post_modified_gmt' => '2026-07-23 09:00:00',
                'modified_rfc3339' => '2026-07-23T09:00:00+00:00',
            ];
        }

        private function identity(string $canonicalUrl, string $title): IdentityRepresentation
        {
            return IdentityRepresentation::fromValue(MUrlDocument::fromArray([
                'profile' => Protocol::M_URL_PROFILE,
                'canonical_url' => $canonicalUrl,
                'title' => $title,
                'content' => $title . ' content',
            ]));
        }
    }
}
