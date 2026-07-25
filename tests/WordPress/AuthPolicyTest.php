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
            if (is_callable($callback)) {
                return $callback($value, ...$arguments);
            }
            return $value;
        }
    }

    if (!function_exists('get_option')) {
        function get_option(string $name, mixed $default = false): mixed
        {
            return $GLOBALS['tct_test_options'][$name] ?? $default;
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
            return (int) ($GLOBALS['tct_test_shop_id'] ?? 0);
        }
    }

    require_once dirname(__DIR__, 2) . '/includes/Auth.php';
}

namespace TCT\Tests\WordPress {

    use PHPUnit\Framework\TestCase;

    final class AuthPolicyTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['tct_test_options'] = [
                'tct_auth_mode' => 'off',
                'tct_api_key_hashes' => [],
                'page_for_posts' => 0,
            ];
            $GLOBALS['tct_test_publicly_viewable'] = true;
            $GLOBALS['tct_test_shop_id'] = 0;
            $GLOBALS['tct_test_filters'] = [];
            $_SERVER = [];
            putenv('TCT_API_KEYS');
        }

        public function testStoredDigestAcceptsBearerWithoutPersistingPlaintext(): void
        {
            $key = 'a-long-internal-test-key';
            $GLOBALS['tct_test_options']['tct_auth_mode'] = 'api_key';
            $GLOBALS['tct_test_options']['tct_api_key_hashes'] = [hash('sha256', $key)];
            $GLOBALS['tct_test_options']['tct_api_keys'] = ['legacy-plaintext-key'];
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $key;

            self::assertTrue(\tct_auth_ok());
            self::assertNotContains($key, \tct_api_key_hashes());
        }

        public function testLegacyPlaintextOptionIsNeverConsulted(): void
        {
            $GLOBALS['tct_test_options']['tct_auth_mode'] = 'api_key';
            $GLOBALS['tct_test_options']['tct_api_keys'] = ['legacy-plaintext-key'];
            $_SERVER['HTTP_X_API_KEY'] = 'legacy-plaintext-key';

            self::assertFalse(\tct_auth_ok());
        }

        public function testRuntimeKeySourceAndWrongKeyBehavior(): void
        {
            $GLOBALS['tct_test_options']['tct_auth_mode'] = 'api_key';
            putenv('TCT_API_KEYS=first-runtime-key,second-runtime-key');
            $_SERVER['HTTP_X_API_KEY'] = 'second-runtime-key';
            self::assertTrue(\tct_auth_ok());

            $_SERVER['HTTP_X_API_KEY'] = 'wrong-runtime-key';
            self::assertFalse(\tct_auth_ok());
        }

        public function testUnknownAuthenticationModeFailsClosed(): void
        {
            $GLOBALS['tct_test_options']['tct_auth_mode'] = 'unknown';
            self::assertFalse(\tct_auth_ok());
        }

        public function testExposurePolicyAcceptsOnlyPublicUnprotectedResources(): void
        {
            $post = (object) [
                'ID' => 42,
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_password' => '',
            ];
            self::assertTrue(\tct_post_is_exposable($post));

            $post->post_password = 'secret';
            self::assertFalse(\tct_post_is_exposable($post));
            $post->post_password = '';

            $post->post_status = 'draft';
            self::assertFalse(\tct_post_is_exposable($post));
            $post->post_status = 'publish';

            $post->post_type = 'product';
            self::assertFalse(\tct_post_is_exposable($post));
        }

        public function testArchiveShopAndNonpublicResourcesAreExcluded(): void
        {
            $post = (object) [
                'ID' => 42,
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_password' => '',
            ];

            $GLOBALS['tct_test_options']['page_for_posts'] = 42;
            self::assertFalse(\tct_post_is_exposable($post));
            $GLOBALS['tct_test_options']['page_for_posts'] = 0;

            $GLOBALS['tct_test_shop_id'] = 42;
            self::assertFalse(\tct_post_is_exposable($post));
            $GLOBALS['tct_test_shop_id'] = 0;

            $GLOBALS['tct_test_publicly_viewable'] = false;
            self::assertFalse(\tct_post_is_exposable($post));
        }

        public function testSyntheticHomepageIsExplicitlyExposable(): void
        {
            self::assertTrue(\tct_post_is_exposable((object) [
                'ID' => 0,
                'post_type' => 'homepage',
                'post_status' => 'publish',
            ]));
        }
    }
}
