<?php

declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
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
    if (!function_exists('sanitize_title')) {
        function sanitize_title(string $value): string
        {
            return strtolower(trim($value));
        }
    }
    if (!function_exists('trailingslashit')) {
        function trailingslashit(string $value): string
        {
            return rtrim($value, '/') . '/';
        }
    }
    if (!function_exists('add_query_arg')) {
        function add_query_arg(string $key, string $value, string $url): string
        {
            return $url
                . (str_contains($url, '?') ? '&' : '?')
                . rawurlencode($key)
                . '='
                . rawurlencode($value);
        }
    }
    if (!function_exists('parse_blocks')) {
        function parse_blocks(string $source): array
        {
            unset($source);
            return $GLOBALS['tct_test_parsed_blocks'] ?? [];
        }
    }
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags(string $value, bool $remove_breaks = false): string
        {
            $value = strip_tags($value);
            return $remove_breaks
                ? (string) preg_replace('/[\r\n\t ]+/', ' ', $value)
                : $value;
        }
    }
    if (!function_exists('wp_unslash')) {
        function wp_unslash(string $value): string
        {
            return stripslashes($value);
        }
    }
    if (!function_exists('get_the_title')) {
        function get_the_title(object $post): string
        {
            return (string) $post->post_title;
        }
    }
    if (!function_exists('get_the_excerpt')) {
        function get_the_excerpt(object $post): string
        {
            return (string) $post->post_excerpt;
        }
    }
    if (!function_exists('get_post_modified_time')) {
        function get_post_modified_time(
            string $format,
            bool $gmt,
            object|int $post
        ): string {
            unset($format, $gmt);
            if (is_int($post)) {
                $post = $GLOBALS['tct_test_posts'][$post];
            }
            return (string) $post->modified_rfc3339;
        }
    }
    if (!function_exists('get_post_time')) {
        function get_post_time(string $format, bool $gmt, object $post): string
        {
            unset($format, $gmt);
            return (string) $post->published_rfc3339;
        }
    }
    if (!function_exists('get_the_author_meta')) {
        function get_the_author_meta(string $field, int $author_id): string
        {
            unset($field);
            return 'Author ' . $author_id;
        }
    }
    if (!function_exists('get_author_posts_url')) {
        function get_author_posts_url(int $author_id): string
        {
            return 'https://example.com/author/' . $author_id . '/';
        }
    }
    if (!function_exists('get_post_thumbnail_id')) {
        function get_post_thumbnail_id(object $post): int
        {
            unset($post);
            return 0;
        }
    }
    if (!function_exists('get_the_category')) {
        function get_the_category(int $post_id): array
        {
            unset($post_id);
            return [];
        }
    }
    if (!function_exists('get_the_tags')) {
        function get_the_tags(int $post_id): array|false
        {
            unset($post_id);
            return false;
        }
    }

    require_once dirname(__DIR__, 2) . '/includes/Hashing.php';
    require_once dirname(__DIR__, 2) . '/includes/Endpoint.php';
}

namespace TCT\Tests\WordPress {

    use PHPUnit\Framework\TestCase;
    use TCT\Draft03\Protocol;
    use TCT\Draft03\SchemaException;

    final class PayloadIdentityTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['tct_test_options'] = [
                'tct_endpoint_slug' => 'llm',
                'tct_include_headings' => 1,
                'tct_force_full_content' => 1,
            ];
            $GLOBALS['tct_test_filters'] = [];
            $GLOBALS['tct_test_parsed_blocks'] = [
                [
                    'blockName' => 'core/paragraph',
                    'innerContent' => ['<p>First &amp; exact</p>'],
                    'innerBlocks' => [],
                ],
                [
                    'blockName' => 'core/group',
                    'innerContent' => [null],
                    'innerBlocks' => [[
                        'blockName' => 'core/paragraph',
                        'innerContent' => ['<p>Nested Case</p>'],
                        'innerBlocks' => [],
                    ]],
                ],
            ];
        }

        public function testFinalPayloadAndIdentityAreStableAndCurrentDraft03(): void
        {
            $post = $this->post();
            [$payload, $first] = \tct_build_murl_identity(
                $post,
                'https://example.com/post/',
                'https://example.com/post/llm/'
            );
            [, $second] = \tct_build_murl_identity(
                $post,
                'https://example.com/post/',
                'https://example.com/post/llm/'
            );

            self::assertSame(Protocol::M_URL_PROFILE, $payload['profile']);
            self::assertSame('https://example.com/post/', $payload['canonical_url']);
            self::assertSame("Example Title\n\nFirst & exact\n\nNested Case", $payload['content']);
            self::assertArrayNotHasKey('hash', $payload);
            self::assertSame($first->body, $second->body);
            self::assertSame($first->etag, $second->etag);
            self::assertSame(
                '"sha256-' . hash('sha256', $first->body) . '"',
                $first->etag
            );

            // Heading extensions follow source document order, not level order.
            self::assertSame([3, 2], array_column($payload['headings'], 'level'));
        }

        public function testFilteredCanonicalMismatchFailsClosed(): void
        {
            $GLOBALS['tct_test_filters']['tct_build_payload'] =
                static function(mixed $default): array {
                    unset($default);
                    return [
                        'profile' => Protocol::M_URL_PROFILE,
                        'canonical_url' => 'https://other.example/post/',
                        'title' => 'Wrong mapping',
                        'content' => 'Wrong mapping',
                    ];
                };

            $this->expectException(SchemaException::class);
            $this->expectExceptionMessage('canonical_url');
            \tct_build_murl_identity(
                $this->post(),
                'https://example.com/post/',
                'https://example.com/post/llm/'
            );
        }

        public function testWordPressMagicQuotesAreRemovedFromIfNoneMatch(): void
        {
            $_SERVER['HTTP_IF_NONE_MATCH'] = '\\"sha256-current\\"';

            try {
                self::assertSame(
                    '"sha256-current"',
                    \tct_if_none_match_request_value()
                );
            } finally {
                unset($_SERVER['HTTP_IF_NONE_MATCH']);
            }
        }

        public function testMurlMappingSupportsPrettyAndPlainPermalinks(): void
        {
            self::assertSame(
                'https://example.com/article/llm/',
                \tct_m_url_for_c_url('https://example.com/article/')
            );
            self::assertSame(
                'https://example.com/?p=7&tct_m_url=1',
                \tct_m_url_for_c_url('https://example.com/?p=7')
            );
        }

        public function testRequestPathIsRelativizedAtTheConfiguredHomeBoundary(): void
        {
            self::assertSame(
                '/2026/07/28/post/llm/',
                \tct_request_path_relative_to_home(
                    '/2026/07/28/post/llm/',
                    'https://example.com/'
                )
            );
            self::assertSame(
                '/2026/07/28/post/llm/',
                \tct_request_path_relative_to_home(
                    '/subsite/2026/07/28/post/llm/',
                    'https://example.com/subsite/'
                )
            );
            self::assertSame(
                '/post/llm/',
                \tct_request_path_relative_to_home(
                    '/scope:deterministic-fixture/post/llm/',
                    'https://playground.wordpress.net/scope:deterministic-fixture/'
                )
            );
            self::assertSame(
                '/',
                \tct_request_path_relative_to_home(
                    '/subsite',
                    'https://example.com/subsite/'
                )
            );
        }

        public function testRequestPathRelativizationFailsClosedOutsideExactBoundary(): void
        {
            self::assertNull(
                \tct_request_path_relative_to_home(
                    '/subsite-other/post/llm/',
                    'https://example.com/subsite/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    '/post/llm/',
                    'https://example.com/subsite/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    "/subsite/post/\x00/llm/",
                    'https://example.com/subsite/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    '',
                    'https://example.com/subsite/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    'subsite/post/llm/',
                    'https://example.com/subsite/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    '//subsite/post/llm/',
                    'https://example.com/subsite/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    '/subsite/post/llm/',
                    'not-an-absolute-home-url'
                )
            );
        }

        public function testRequestPathRelativizationHasAnExactByteBoundary(): void
        {
            $atLimit = '/' . str_repeat('a', TCT_MAX_REQUEST_PATH_BYTES - 1);
            $overLimit = $atLimit . 'a';

            self::assertSame(
                $atLimit,
                \tct_request_path_relative_to_home(
                    $atLimit,
                    'https://example.com/'
                )
            );
            self::assertNull(
                \tct_request_path_relative_to_home(
                    $overLimit,
                    'https://example.com/'
                )
            );
        }

        private function post(): object
        {
            return (object) [
                'ID' => 7,
                'post_title' => 'Example Title',
                'post_content' => '<h3>First heading</h3><p>Body</p><h2>Second heading</h2>',
                'post_excerpt' => 'A stable excerpt.',
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_password' => '',
                'post_author' => 5,
                'post_name' => 'post',
                'modified_rfc3339' => '2026-07-23T09:00:00+00:00',
                'published_rfc3339' => '2026-07-20T09:00:00+00:00',
            ];
        }
    }
}
