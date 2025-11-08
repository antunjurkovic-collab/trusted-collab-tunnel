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
 * Compute SHA-256 hash from canonical JSON (Method A per draft-01).
 *
 * Per draft-jurkovikj-collab-tunnel-01 Section 6.2:
 * 1. Build payload object WITHOUT hash field
 * 2. Canonicalize to UTF-8 bytes (deterministic JSON)
 * 3. Compute SHA-256
 * 4. Return as "sha256-<64hex>"
 *
 * @param array $payload Associative array (WITHOUT 'hash' field)
 * @return string Hash in format "sha256-<hex>"
 */
function tct_compute_hash_from_json($payload) {
    // Remove hash field if accidentally included
    $clean = $payload;
    unset($clean['hash']);

    // Canonical JSON: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ensures deterministic output
    // PHP json_encode preserves key insertion order (deterministic for our payloads)
    // For full RFC 8785 compliance, would need a dedicated library, but this is sufficient
    // for TCT's deterministic requirements (stable keys, no prettifying, UTF-8)
    $canonical = wp_json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($canonical === false) {
        // Fallback: try without Unicode unescaping
        $canonical = wp_json_encode($clean, JSON_UNESCAPED_SLASHES);
    }

    $hex = hash('sha256', $canonical);
    return 'sha256-' . $hex;
}
