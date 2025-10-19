<?php
if (!defined('ABSPATH')) { exit; }

function tct_changes_get() {
    $changes = get_option('tct_changes_list');
    if (!is_array($changes)) $changes = [];
    return $changes;
}

function tct_changes_save($changes) {
    // Cap to last 100 items
    if (count($changes) > 100) {
        $changes = array_slice($changes, 0, 100);
    }
    update_option('tct_changes_list', $changes);
}

function tct_record_change($post, $c_url, $m_url, $etag, $modified_iso) {
    $changes = tct_changes_get();
    array_unshift($changes, [
        'cUrl' => $c_url,
        'mUrl' => $m_url,
        'etag' => $etag,
        'modified' => $modified_iso,
        'ts' => gmdate('c'),
        'post_id' => $post ? (int)$post->ID : null,
        'post_type' => $post ? $post->post_type : null,
    ]);
    tct_changes_save($changes);
}

function tct_output_changes_json() {
    $changes = tct_changes_get();
    $out = [
        'version' => 1,
        'generated_at' => gmdate('c'),
        'items' => $changes,
    ];
    header('Content-Type: application/json; charset=UTF-8', true);
    echo wp_json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

