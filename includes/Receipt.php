<?php
if (!defined('ABSPATH')) { exit; }

function tct_receipts_enabled() {
    return (bool) get_option('tct_receipts_enabled', 0);
}

function tct_emit_usage_receipt($etag, $status, $bytes_served) {
    $contract = $_SERVER['HTTP_X_AI_CONTRACT'] ?? '';
    $ts = gmdate('c');
    $payload = sprintf('contract=%s; status=%d; bytes=%d; etag="%s"; ts=%s',
        $contract, (int)$status, (int)$bytes_served, $etag, $ts
    );
    $key = get_option('tct_receipt_hmac_key', '');
    $sig = '';
    if ($key) {
        $raw = hash_hmac('sha256', $payload, $key, true);
        $sig = base64_encode($raw);
    }
    $hdr = $payload . ($sig ? ('; sig=' . $sig) : '');
    header('AI-Usage-Receipt: ' . $hdr, false);
}

