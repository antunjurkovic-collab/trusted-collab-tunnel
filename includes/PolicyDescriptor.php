<?php
if (!defined('ABSPATH')) { exit; }

/**
 * AI Policy Descriptor - Machine-readable policy for AI crawlers
 *
 * Provides structured JSON describing publisher preferences for AI use cases.
 * Aligned with IETF AIPREF working group priorities.
 *
 * @since 1.0.0
 */

/**
 * Get the complete policy descriptor array
 *
 * @return array Policy descriptor data
 */
function tct_get_policy_descriptor() {
    $policy = [
        'profile' => 'tct-policy-1',
        'version' => 1,
        'effective' => get_option('tct_policy_effective', gmdate('c')),
        'updated' => get_option('tct_policy_updated', gmdate('c')),

        'policy_urls' => [
            'terms_of_service' => tct_get_terms_url(),
            'payment_info' => tct_get_pricing_url(),
            'contact' => get_option('tct_contact_url', ''),
        ],

        'purposes' => [
            'allow_ai_input' => (bool) get_option('tct_allow_ai_input', true),
            'allow_ai_train' => (bool) get_option('tct_allow_ai_train', false),
            'allow_search_indexing' => (bool) get_option('tct_allow_search', true),
        ],

        'requirements' => [
            'attribution_required' => (bool) get_option('tct_require_attribution', true),
            'link_back_required' => (bool) get_option('tct_require_linkback', false),
            'notice_required' => (bool) get_option('tct_require_notice', true),
        ],

        'rate_hints' => [
            'max_requests_per_second' => (int) get_option('tct_rate_hint_rps', 0) ?: null,
            'max_requests_per_day' => (int) get_option('tct_rate_hint_daily', 10000),
            'note' => 'Advisory limits, honor system',
        ],

        'extensions' => new stdClass(), // Empty object for future AIPREF vocabulary
    ];

    /**
     * Filter the policy descriptor before output
     *
     * Use this to customize policy fields or add extensions
     *
     * Example:
     * add_filter('tct_policy_descriptor', function($policy) {
     *     $policy['purposes']['allow_ai_train'] = true;
     *     $policy['extensions']['custom_field'] = 'value';
     *     return $policy;
     * });
     *
     * @param array $policy Policy descriptor array
     */
    return apply_filters('tct_policy_descriptor', $policy);
}

/**
 * Output the policy descriptor as JSON
 *
 * Endpoint: /llm-policy.json
 * Content-Type: application/json
 * Cache: 1 hour (policy changes infrequently)
 */
function tct_output_policy_json() {
    header('Content-Type: application/json; charset=UTF-8', true);
    header('Cache-Control: max-age=3600, public', true); // Cache for 1 hour
    header('Vary: Accept-Encoding', true);

    // Update the "updated" timestamp on first access if not set
    if (!get_option('tct_policy_effective')) {
        update_option('tct_policy_effective', gmdate('c'));
    }

    $policy = tct_get_policy_descriptor();

    // Pretty print for human readability
    echo wp_json_encode($policy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Update the policy "updated" timestamp
 *
 * Call this when policy settings change
 */
function tct_update_policy_timestamp() {
    update_option('tct_policy_updated', gmdate('c'));
}

/**
 * Initialize policy defaults on plugin activation
 */
function tct_init_policy_defaults() {
    // Only set if not already configured
    if (!get_option('tct_policy_effective')) {
        $now = gmdate('c');
        update_option('tct_policy_effective', $now);
        update_option('tct_policy_updated', $now);
    }

    // Set default purposes (allow AI input, prohibit training by default)
    if (get_option('tct_allow_ai_input') === false) {
        update_option('tct_allow_ai_input', 1);
    }
    if (get_option('tct_allow_ai_train') === false) {
        update_option('tct_allow_ai_train', 0);
    }
    if (get_option('tct_allow_search') === false) {
        update_option('tct_allow_search', 1);
    }

    // Set default requirements
    if (get_option('tct_require_attribution') === false) {
        update_option('tct_require_attribution', 1);
    }
    if (get_option('tct_require_linkback') === false) {
        update_option('tct_require_linkback', 0);
    }
    if (get_option('tct_require_notice') === false) {
        update_option('tct_require_notice', 1);
    }

    // Set default rate hints
    if (get_option('tct_rate_hint_daily') === false) {
        update_option('tct_rate_hint_daily', 10000);
    }
}
