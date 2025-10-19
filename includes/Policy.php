<?php
if (!defined('ABSPATH')) { exit; }

function tct_get_terms_url() {
    $v = get_option('tct_terms_url', '');
    return apply_filters('tct_terms_url', $v);
}

function tct_get_pricing_url() {
    $v = get_option('tct_pricing_url', '');
    return apply_filters('tct_pricing_url', $v);
}

function tct_emit_policy_links() {
    $terms = tct_get_terms_url();
    $pricing = tct_get_pricing_url();
    if ($terms) {
        header('Link: <' . esc_url_raw($terms) . '>; rel="terms"', false);
    }
    if ($pricing) {
        header('Link: <' . esc_url_raw($pricing) . '>; rel="pricing"', false);
    }
}

