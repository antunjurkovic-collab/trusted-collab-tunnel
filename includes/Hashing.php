<?php
if (!defined('ABSPATH')) { exit; }

function tct_normalize_text($html) {
    // Basic, template-invariant-ish normalization; callers can override via filter 'tct_normalize_text'
    $text = wp_strip_all_tags((string)$html, true);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strtolower($text);
    // Collapse whitespace
    $text = preg_replace('/\s+/u', ' ', $text);
    // Normalize common punctuation by removing or spacing
    $text = preg_replace('/[\p{P}\p{S}]+/u', ' ', $text); // remove punctuation/symbols
    $text = trim($text);
    /**
     * Filter: tct_normalize_text
     * Allow sites to customize normalization pipeline.
     */
    return apply_filters('tct_normalize_text', $text, $html);
}

function tct_compute_fingerprint($normalized_text) {
    $hex = hash('sha256', (string)$normalized_text);
    return 'sha256-' . $hex;
}

