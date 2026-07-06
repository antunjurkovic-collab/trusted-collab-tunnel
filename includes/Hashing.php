<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Build the authoritative content string from the CMS (theme-independent).
 * Default: Title + blank line + body text (no HTML), UTF-8 plain text.
 * Filter 'tct_build_content_string' allows adding media text in deterministic order.
 *
 * PHASE 1.5: Uses parse_blocks() DIRECTLY - skips apply_filters('the_content')
 * Benefits:
 * - More stable: theme/template changes won't affect semantic content
 * - Much faster: no shortcodes, embeds, or heavy filters
 * - Better for AI: semantic content, not pixel output
 */
function tct_build_content_string($post) {
    $title = get_the_title($post);

    // Use parse_blocks DIRECTLY - skip apply_filters('the_content')
    $blocks = parse_blocks($post->post_content ?? '');
    $body_parts = [];

    foreach ($blocks as $block) {
        // Skip empty/whitespace blocks
        if (empty($block['blockName'])) {
            continue;
        }

        // Extract text from innerHTML
        if (!empty($block['innerHTML'])) {
            $text = wp_strip_all_tags($block['innerHTML'], true);
            $text = trim($text);
            if ($text !== '') {
                $body_parts[] = $text;
            }
        }

        // Handle nested blocks
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner) {
                if (!empty($inner['innerHTML'])) {
                    $text = wp_strip_all_tags($inner['innerHTML'], true);
                    $text = trim($text);
                    if ($text !== '') {
                        $body_parts[] = $text;
                    }
                }
            }
        }
    }

    $body_text = implode("\n\n", $body_parts);

    // Combine title + body
    $content = '';
    if ($title !== '') {
        $content .= $title . "\n\n";
    }
    $content .= $body_text;

    /**
     * Filter: tct_build_content_string
     * Modify or extend the constructed content string (e.g., include media captions/alt text).
     */
    return apply_filters('tct_build_content_string', $content, $post, $title, $body_text);
}

/**
 * Minimal normalization over a plain-text content string.
 * Steps: decode entities, NFKC, casefold, remove Cc, collapse ASCII whitespace, trim.
 */
function tct_normalize_text($text) {
    $text = (string) $text;
    // Step 1: Decode entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Step 2: NFKC normalization (if intl Normalizer available)
    if (class_exists('Normalizer')) {
        $text = Normalizer::normalize($text, Normalizer::NFKC);
    }
    // Step 3: Unicode case folding
    if (function_exists('mb_convert_case')) {
        $text = mb_convert_case($text, MB_CASE_FOLD, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    // Step 4: Remove control characters (Unicode category Cc)
    $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F-\x9F]/u', '', $text);
    // Step 5: Collapse ASCII whitespace (space, tab, LF, FF, CR)
    $text = preg_replace('/[ \t\n\r\f]+/u', ' ', $text);
    // Step 6: Trim
    $text = trim($text);

    /**
     * Filter: tct_normalize_text
     * Allow sites to customize normalization pipeline.
     */
    return apply_filters('tct_normalize_text', $text);
}

/**
 * Compute sha256-<hex> fingerprint from normalized text.
 * @deprecated Use tct_compute_hash_from_json() for draft-01 compliance
 */
function tct_compute_fingerprint($normalized_text) {
    $hex = hash('sha256', (string)$normalized_text);
    return 'sha256-' . $hex;
}

/**
 * Canonical JSON serialization with sorted keys at ALL levels.
 *
 * This is a simplified RFC8785-style canonicalization that ensures:
 * - Keys sorted lexicographically at every nesting level
 * - No whitespace
 * - Consistent encoding
 *
 * This prevents hash drift when field insertion order changes or
 * when running on different PHP versions.
 *
 * @param mixed $data The data to canonicalize
 * @return mixed Canonicalized data (arrays have sorted keys)
 */
function tct_canonicalize_json($data) {
    if (is_array($data)) {
        // Check if associative array (object)
        $is_assoc = array_keys($data) !== range(0, count($data) - 1);

        if ($is_assoc) {
            // Associative: sort keys lexicographically
            ksort($data);
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = tct_canonicalize_json($value);
            }
            return $result;
        } else {
            // Indexed array: preserve order but recurse into values
            $result = [];
            foreach ($data as $value) {
                $result[] = tct_canonicalize_json($value);
            }
            return $result;
        }
    }

    // For non-arrays (strings, numbers, booleans, null), return as-is
    return $data;
}

/**
 * Encode payload using the deterministic JSON bytes used for TCT validators.
 *
 * This is a pragmatic PHP implementation of the draft-03 requirement that the
 * same canonical JSON bytes are used for both the M-URL response body and the
 * strong ETag computation. It sorts object keys at every level and emits JSON
 * without insignificant whitespace.
 *
 * @param array $payload Associative array (WITHOUT 'hash' field)
 * @return string Canonical JSON bytes, or an empty JSON object on failure
 */
function tct_canonical_json_encode($payload) {
    $clean = is_array($payload) ? $payload : [];
    unset($clean['hash']);

    $canonical_data = tct_canonicalize_json($clean);
    $canonical = wp_json_encode($canonical_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($canonical === false) {
        $canonical = wp_json_encode($canonical_data, JSON_UNESCAPED_SLASHES);
    }

    return $canonical === false ? '{}' : $canonical;
}

/**
 * Compute SHA-256 hash from canonical JSON bytes.
 *
 * Per draft-jurkovikj-collab-tunnel-03:
 * 1. Build payload object WITHOUT hash field
 * 2. Canonicalize to UTF-8 bytes
 * 3. Compute SHA-256
 * 4. Return as "sha256-<64hex>"
 *
 * @param array $payload Associative array (WITHOUT 'hash' field)
 * @return string Hash in format "sha256-<hex>"
 */
function tct_compute_hash_from_json($payload) {
    return 'sha256-' . hash('sha256', tct_canonical_json_encode($payload));
}

/**
 * Build final TCT payload and hash for a given post + URLs.
 *
 * This is the SINGLE SOURCE OF TRUTH used by:
 * - M-URL endpoint responses (tct_output_llm_endpoint)
 * - M-Sitemap etag values (tct_output_sitemap)
 *
 * Ensures:
 * - Identical canonicalization and hashing in both places
 * - Sitemap etag == M-URL ETag (sans quotes)
 * - 100% triple parity: sitemap etag == HTTP ETag == payload hash
 *
 * Per expert review: "Both sitemap and M-URL must derive from the
 * same canonicalization/hashing pipeline."
 *
 * @param WP_Post|object|null $post WordPress post object
 * @param string $c_url Canonical URL (with trailing slash)
 * @param string $m_url M-URL (with trailing slash)
 * @return array [payload (array), hash (string "sha256-...")]
 */
function tct_build_tct_payload_and_hash($post, $c_url, $m_url) {
    // 1. Base payload without hash
    $full = tct_build_full_payload($post, $c_url, $m_url, null);

    // 2. Initial hash from canonical JSON of base payload
    $hash = tct_compute_hash_from_json($full);
    // NOTE: Per draft-03, DO NOT add 'hash' to payload (ETag header only)

    // 3. Allow an external provider to override or extend
    $payload = null;
    $filtered = apply_filters('tct_build_payload', null, $post, $c_url, $m_url);

    if (is_array($filtered) && isset($filtered['payload'])) {
        // Fully provided payload. Draft-03 requires the ETag to be derived from
        // the final representation bytes, so ignore caller-provided hashes.
        $payload = $filtered['payload'];
        $hash = tct_compute_hash_from_json($payload);
    } elseif (is_array($filtered)) {
        // Partial override: merge with our full payload
        $payload = $filtered;

        // Fill in missing core fields from our base payload
        if (!isset($payload['content']) || $payload['content'] === '' || $payload['content'] === null) {
            $payload['content'] = $full['content'];
        }
        if (!isset($payload['excerpt']) || $payload['excerpt'] === '' || $payload['excerpt'] === null) {
            $payload['excerpt'] = $full['excerpt'];
        }
        if (!isset($payload['word_count']) || !is_int($payload['word_count'])) {
            $payload['word_count'] = $full['word_count'];
        }

        $payload['llm_url'] = $payload['llm_url'] ?? $m_url;
        $payload['canonical_url'] = $payload['canonical_url'] ?? $c_url;

        // Recompute hash from final merged payload
        $hash = tct_compute_hash_from_json($payload);
        // NOTE: Per draft-03, DO NOT add 'hash' to payload (ETag header only)
    } else {
        // No external override â†’ use our full payload
        $payload = $full;
        // $hash already set
    }

    return [$payload, $hash];
}
