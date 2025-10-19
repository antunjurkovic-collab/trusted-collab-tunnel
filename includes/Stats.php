<?php
if (!defined('ABSPATH')) { exit; }

function tct_stats_get_store() {
    $store = get_option('tct_stats_store');
    if (!is_array($store)) {
        $store = [
            'since' => gmdate('c'),
            'requests' => 0,
            'ok_200' => 0,
            'not_modified_304' => 0,
            'bytes_200' => 0,
            'bytes_saved_304' => 0,
            'murls' => [], // mUrl => ['hits'=>0,'bytes200'=>0,'bytesSaved'=>0]
            'last_body_len' => [], // mUrl => last 200 body length
        ];
    }
    return $store;
}

function tct_stats_save_store($store) {
    update_option('tct_stats_store', $store);
}

function tct_stats_record($m_url, $status, $body_len = 0) {
    if (!is_string($m_url) || $m_url === '') return;
    $store = tct_stats_get_store();
    $store['requests'] += 1;
    if ($status == 200) {
        $store['ok_200'] += 1;
        $store['bytes_200'] += max(0, (int)$body_len);
        $store['last_body_len'][$m_url] = max(0, (int)$body_len);
        if (!isset($store['murls'][$m_url])) $store['murls'][$m_url] = ['hits'=>0,'bytes200'=>0,'bytesSaved'=>0];
        $store['murls'][$m_url]['hits'] += 1;
        $store['murls'][$m_url]['bytes200'] += max(0, (int)$body_len);
    } elseif ($status == 304) {
        $store['not_modified_304'] += 1;
        $saved = 0;
        if (isset($store['last_body_len'][$m_url])) {
            $saved = max(0, (int)$store['last_body_len'][$m_url]);
        }
        $store['bytes_saved_304'] += $saved;
        if (!isset($store['murls'][$m_url])) $store['murls'][$m_url] = ['hits'=>0,'bytes200'=>0,'bytesSaved'=>0];
        $store['murls'][$m_url]['hits'] += 1;
        $store['murls'][$m_url]['bytesSaved'] += $saved;
    } else {
        if (!isset($store['murls'][$m_url])) $store['murls'][$m_url] = ['hits'=>0,'bytes200'=>0,'bytesSaved'=>0];
        $store['murls'][$m_url]['hits'] += 1;
    }

    // Cap map sizes to avoid option bloat
    if (count($store['murls']) > 500) {
        // keep most recent 500 by hits heuristic
        arsort($store['murls']);
        $store['murls'] = array_slice($store['murls'], 0, 500, true);
    }
    if (count($store['last_body_len']) > 1000) {
        $store['last_body_len'] = array_slice($store['last_body_len'], -1000, null, true);
    }

    tct_stats_save_store($store);
}

function tct_output_stats_json() {
    $store = tct_stats_get_store();
    $items = [];
    // Build top endpoints (by hits)
    $m = $store['murls'];
    uasort($m, function($a,$b){ return ($b['hits'] ?? 0) <=> ($a['hits'] ?? 0); });
    $top = array_slice($m, 0, 10, true);
    foreach ($top as $url => $row) {
        $items[] = [
            'mUrl' => $url,
            'hits' => (int)($row['hits'] ?? 0),
            'bytes200' => (int)($row['bytes200'] ?? 0),
            'bytesSaved' => (int)($row['bytesSaved'] ?? 0),
        ];
    }

    $out = [
        'version' => 1,
        'since' => $store['since'],
        'generated_at' => gmdate('c'),
        'totals' => [
            'requests' => (int)$store['requests'],
            'ok_200' => (int)$store['ok_200'],
            'not_modified_304' => (int)$store['not_modified_304'],
            'bytes_200' => (int)$store['bytes_200'],
            'bytes_saved_304' => (int)$store['bytes_saved_304'],
            'unique_m_urls' => count($store['murls']),
        ],
        'top_endpoints' => $items,
    ];
    header('Content-Type: application/json; charset=UTF-8', true);
    echo wp_json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

