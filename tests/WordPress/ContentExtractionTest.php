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

    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags(string $value, bool $remove_breaks = false): string
        {
            $value = strip_tags($value);
            return $remove_breaks
                ? preg_replace('/[\r\n\t ]+/', ' ', $value)
                : $value;
        }
    }

    require_once dirname(__DIR__, 2) . '/includes/Hashing.php';
}

namespace TCT\Tests\WordPress {

    use PHPUnit\Framework\TestCase;
    use TCT\Draft03\ResourceLimitException;

    final class ContentExtractionTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['tct_test_filters'] = [];
        }

        public function testNestedBlocksPreservePlacementWithoutDuplication(): void
        {
            $block = [
                'blockName' => 'core/group',
                'innerContent' => ['<p>Before</p>', null, '<p>After</p>'],
                'innerBlocks' => [[
                    'blockName' => 'core/columns',
                    'innerContent' => [null, null],
                    'innerBlocks' => [
                        [
                            'blockName' => 'core/paragraph',
                            'innerContent' => ['<p>Left</p>'],
                            'innerBlocks' => [],
                        ],
                        [
                            'blockName' => 'core/paragraph',
                            'innerContent' => ['<p>Right</p>'],
                            'innerBlocks' => [],
                        ],
                    ],
                ]],
            ];

            self::assertSame(
                ['Before', 'Left', 'Right', 'After'],
                \tct_extract_block_text_parts($block)
            );
        }

        public function testUnreferencedChildrenAreStillTraversedOnce(): void
        {
            $block = [
                'blockName' => 'test/container',
                'innerContent' => ['<p>Parent</p>'],
                'innerBlocks' => [[
                    'blockName' => 'core/paragraph',
                    'innerContent' => ['<p>Child</p>'],
                    'innerBlocks' => [],
                ]],
            ];

            self::assertSame(['Parent', 'Child'], \tct_extract_block_text_parts($block));
        }

        public function testClassicMarkupKeepsLogicalBoundariesAndFidelity(): void
        {
            self::assertSame(
                "CaseSensitive  value\nSecond & final\nThird",
                \tct_plain_text_fragment(
                    '<p>CaseSensitive  value</p><p>Second &amp; final<br>Third</p>'
                )
            );
        }

        public function testUnresolvedDynamicBlockFailsToEmptyText(): void
        {
            self::assertSame([], \tct_extract_block_text_parts([
                'blockName' => 'test/dynamic',
                'innerContent' => [],
                'innerBlocks' => [],
            ]));
        }

        public function testBlockDepthBoundaryFailsClosed(): void
        {
            $accepted = [
                'blockName' => 'core/paragraph',
                'innerContent' => ['<p>Leaf</p>'],
                'innerBlocks' => [],
            ];
            for ($depth = 0; $depth < 64; ++$depth) {
                $accepted = [
                    'blockName' => 'core/group',
                    'innerContent' => [null],
                    'innerBlocks' => [$accepted],
                ];
            }
            self::assertSame(['Leaf'], \tct_extract_block_text_parts($accepted));

            $rejected = [
                'blockName' => 'core/group',
                'innerContent' => [null],
                'innerBlocks' => [$accepted],
            ];
            $this->expectException(ResourceLimitException::class);
            $this->expectExceptionMessage('nesting');
            \tct_extract_block_text_parts($rejected);
        }
    }
}
