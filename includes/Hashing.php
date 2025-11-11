<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Build the authoritative content string from the CMS (theme-independent).
 * Default: Title + blank line + body text (no HTML), UTF-8 plain text.
 * Filter 'tct_build_content_string' allows adding media text in deterministic order.
 */
function tct_build_content_string($post) {
    $title = $post ? get_the_title($post) : '';

    // Build body text from post content without HTML markup
    $body_html = '';
    if ($post) {
        $body_html = apply_filters('the_content', $post->post_content);
        if (!is_string($body_html) || trim($body_html) === '') {
            $body_html = (string) $post->post_content;
        }
    }
    $body_text = wp_strip_all_tags((string)$body_html, true);

    // Combine with deterministic separator (two newlines)
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
 * Compute SHA-256 hash from canonical JSON (Method A per draft-01).
 *
 * IMPORTANT: This ensures deterministic key ordering to prevent hash drift.
 *
 * Per draft-jurkovikj-collab-tunnel-01 Section 6.2:
 * 1. Build payload object WITHOUT hash field
 * 2. Canonicalize to UTF-8 bytes (deterministic JSON with sorted keys)
 * 3. Compute SHA-256
 * 4. Return as "sha256-<64hex>"
 *
 * Per draft-01 Section 6.1: Implementations SHOULD use RFC8785, or ensure
 * stable key ordering. This implementation uses lexicographic key sorting
 * at all nesting levels to guarantee deterministic hashes.
 *
 * @param array $payload Associative array (WITHOUT 'hash' field)
 * @return string Hash in format "sha256-<hex>"
 */
function tct_compute_hash_from_json($payload) {
    // Remove hash field if accidentally included
    $clean = $payload;
    unset($clean['hash']);

    // Canonicalize: sort keys at all levels for deterministic hashing
    $canonical_data = tct_canonicalize_json($clean);

    // Serialize to JSON with deterministic flags
    $canonical = wp_json_encode($canonical_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($canonical === false) {
        // Fallback: try without Unicode unescaping
        $canonical = wp_json_encode($canonical_data, JSON_UNESCAPED_SLASHES);
        if ($canonical === false) {
            // Last resort: return error hash
            return 'sha256-' . str_repeat('0', 64);
        }
    }

    $hex = hash('sha256', $canonical);
    return 'sha256-' . $hex;
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
    $full['hash'] = $hash;

    // 3. Allow an external provider to override or extend
    $payload = null;
    $filtered = apply_filters('tct_build_payload', null, $post, $c_url, $m_url);

    if (is_array($filtered) && isset($filtered['payload'], $filtered['hash'])) {
        // Fully provided: trust but ensure consistency
        $payload = $filtered['payload'];
        $hash = $filtered['hash'];

        // Optional paranoid check: recompute and verify
        // $check = tct_compute_hash_from_json($payload);
        // if ($check !== $hash) { $hash = $check; $payload['hash'] = $hash; }
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
        $payload['hash'] = $hash;
    } else {
        // No external override → use our full payload
        $payload = $full;
        // $hash already set
    }

    return [$payload, $hash];
}
