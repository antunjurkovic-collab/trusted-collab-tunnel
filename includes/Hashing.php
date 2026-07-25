<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Return the stable C-URL represented by a WordPress post.
 */
function tct_c_url_for_post($post) {
    $post_id = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
    if ($post_id === 0 || $post_id === (int) get_option('page_on_front', 0)) {
        return trailingslashit(home_url('/'));
    }

    $permalink = get_permalink($post_id);
    return is_string($permalink) && $permalink !== '' ? $permalink : null;
}

/**
 * Map a C-URL to the configured M-URL without depending on request spelling.
 */
function tct_m_url_for_c_url($c_url) {
    $endpoint = sanitize_title((string) get_option('tct_endpoint_slug', 'llm'));
    if ($endpoint === '') {
        $endpoint = 'llm';
    }

    if (parse_url((string) $c_url, PHP_URL_QUERY) !== null) {
        return add_query_arg('tct_m_url', '1', (string) $c_url);
    }

    return trailingslashit((string) $c_url) . $endpoint . '/';
}

/**
 * Convert one parsed WordPress block subtree to deterministic plain-text parts.
 *
 * innerContent preserves the placement of child blocks. Null entries are
 * replaced by recursively extracted children, preventing shallow traversal
 * loss and parent/child duplication.
 *
 * @param array<string, mixed> $block Parsed block.
 * @return string[]
 */
function tct_extract_block_text_parts($block, $depth = 0, &$nodes = null) {
    if (!is_array($block)) {
        return [];
    }

    if ($nodes === null) {
        $nodes = 0;
    }
    if ($depth > \TCT\Draft03\Protocol::MAX_JSON_DEPTH) {
        throw new \TCT\Draft03\ResourceLimitException(
            'WordPress block nesting exceeds the internal Draft-03 limit.'
        );
    }
    ++$nodes;
    if ($nodes > \TCT\Draft03\Protocol::MAX_JSON_NODES) {
        throw new \TCT\Draft03\ResourceLimitException(
            'WordPress block count exceeds the internal Draft-03 limit.'
        );
    }

    $parts = [];
    $children = isset($block['innerBlocks']) && is_array($block['innerBlocks'])
        ? array_values($block['innerBlocks'])
        : [];
    $child_index = 0;

    if (isset($block['innerContent']) && is_array($block['innerContent'])) {
        foreach ($block['innerContent'] as $fragment) {
            if ($fragment === null) {
                if (isset($children[$child_index])) {
                    foreach (
                        tct_extract_block_text_parts(
                            $children[$child_index],
                            $depth + 1,
                            $nodes
                        ) as $child_part
                    ) {
                        $parts[] = $child_part;
                    }
                }
                ++$child_index;
                continue;
            }

            if (is_string($fragment)) {
                $text = tct_plain_text_fragment($fragment);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }
    } elseif (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
        $text = tct_plain_text_fragment($block['innerHTML']);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    while (isset($children[$child_index])) {
        foreach (
            tct_extract_block_text_parts(
                $children[$child_index],
                $depth + 1,
                $nodes
            ) as $child_part
        ) {
            $parts[] = $child_part;
        }
        ++$child_index;
    }

    if ($parts === [] && !empty($block['blockName'])) {
        $resolved = apply_filters('tct_resolve_dynamic_block_text', '', $block);
        if (is_string($resolved) && trim($resolved) !== '') {
            $parts[] = trim($resolved);
        }
    }

    return $parts;
}

/**
 * Strip saved markup without case folding, Unicode normalization, or internal
 * whitespace collapse.
 */
function tct_plain_text_fragment($fragment) {
    $fragment = preg_replace('/<br\s*\/?>/i', "\n", (string) $fragment);
    $fragment = preg_replace(
        '/<\/(?:address|article|aside|blockquote|div|figcaption|figure|footer|h[1-6]|header|li|main|nav|p|pre|section|td|th|tr)>/i',
        "\n",
        (string) $fragment
    );
    $text = wp_strip_all_tags((string) $fragment, false);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($text);
}

/**
 * Build the documented publisher-selected plain-text representation.
 *
 * The transformation reads saved source directly, strips markup, decodes HTML
 * entities, preserves case and meaningful internal whitespace, traverses
 * Gutenberg blocks recursively, and includes Classic/freeform HTML.
 */
function tct_build_content_string($post) {
    $post_id = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
    $title = $post_id > 0
        ? (string) get_the_title($post)
        : (string) ($post->post_title ?? '');
    $source = isset($post->post_content) ? (string) $post->post_content : '';
    if (strlen($source) > \TCT\Draft03\Protocol::MAX_SOURCE_BYTES) {
        throw new \TCT\Draft03\ResourceLimitException(
            'Saved WordPress source exceeds the internal Draft-03 byte limit.'
        );
    }
    $blocks = parse_blocks($source);
    $body_parts = [];
    $nodes = 0;

    foreach ((array) $blocks as $block) {
        foreach (tct_extract_block_text_parts($block, 0, $nodes) as $block_part) {
            $body_parts[] = $block_part;
        }
    }

    $body_text = implode("\n\n", $body_parts);
    $content = $title;
    if ($title !== '' && $body_text !== '') {
        $content .= "\n\n";
    }
    $content .= $body_text;

    return apply_filters('tct_build_content_string', $content, $post, $title, $body_text);
}

/**
 * Encode a complete JSON value as RFC 8785 JCS.
 *
 * Failures are typed and fail closed; no substitute JSON value is returned.
 */
function tct_canonical_json_encode($payload) {
    return (new \TCT\Draft03\JcsEncoder())->encode($payload);
}

/**
 * Compute the Draft-03 catalog form of the JCS identity ETag.
 */
function tct_compute_hash_from_json($payload) {
    return \TCT\Draft03\IdentityRepresentation::fromValue($payload)->catalogEtag();
}

/**
 * Certify a final M-URL payload and derive its exact identity representation.
 *
 * @param array<string, mixed> $payload
 */
function tct_certify_murl_identity($payload) {
    $document = \TCT\Draft03\MUrlDocument::fromArray($payload);
    return \TCT\Draft03\IdentityRepresentation::fromValue($document);
}

/**
 * @return array{0: array<string, mixed>, 1: \TCT\Draft03\IdentityRepresentation}
 */
function tct_build_murl_identity($post, $c_url, $m_url) {
    $payload = tct_build_tct_payload($post, $c_url, $m_url);
    return [$payload, tct_certify_murl_identity($payload)];
}

/**
 * Build the final M-URL payload.
 *
 * The tct_build_payload filter remains available for deterministic extensions.
 * Its final result must retain all required Draft-03 members and must satisfy
 * the JCS/I-JSON domain.
 *
 * @return array<string, mixed>
 */
function tct_build_tct_payload($post, $c_url, $m_url) {
    $full = tct_build_full_payload($post, $c_url, $m_url, null);
    $filtered = apply_filters('tct_build_payload', null, $post, $c_url, $m_url);

    if (is_array($filtered) && isset($filtered['payload']) && is_array($filtered['payload'])) {
        $payload = $filtered['payload'];
    } elseif (is_array($filtered)) {
        $payload = $filtered;
        foreach ($full as $member => $value) {
            if (!array_key_exists($member, $payload)) {
                $payload[$member] = $value;
            }
        }
    } else {
        $payload = $full;
    }

    if (($payload['canonical_url'] ?? null) !== $c_url) {
        throw new \TCT\Draft03\SchemaException(
            'M-URL canonical_url must equal the emitted canonical Link target.'
        );
    }

    return $payload;
}

/**
 * Backward-compatible internal helper returning the certified catalog ETag.
 *
 * @return array{0: array<string, mixed>, 1: string}
 */
function tct_build_tct_payload_and_hash($post, $c_url, $m_url) {
    [$payload, $identity] = tct_build_murl_identity($post, $c_url, $m_url);
    return [$payload, $identity->catalogEtag()];
}
