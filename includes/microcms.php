<?php
require_once __DIR__ . '/env.php';

function highlander_microcms_fetch($endpoint, $contentId = null, $query = []) {
    $allowedEndpoints = ['blogs', 'products', 'logs'];
    if (!in_array($endpoint, $allowedEndpoints, true)) {
        return [
            'status' => 400,
            'body' => ['error' => 'Unsupported endpoint'],
        ];
    }

    $serviceDomain = highlander_env('MICROCMS_SERVICE_DOMAIN');
    $apiKey = highlander_env('MICROCMS_API_KEY');

    if (!$serviceDomain || !$apiKey) {
        return [
            'status' => 500,
            'body' => ['error' => 'microCMS configuration is missing'],
        ];
    }

    $url = 'https://' . rawurlencode($serviceDomain) . '.microcms.io/api/v1/' . rawurlencode($endpoint);
    if ($contentId) {
        $url .= '/' . rawurlencode($contentId);
    }

    $safeQuery = [];
    foreach (['limit', 'offset', 'orders', 'filters', 'fields', 'q', 'draftKey'] as $key) {
        if (isset($query[$key]) && $query[$key] !== '') {
            $safeQuery[$key] = $query[$key];
        }
    }
    if (!empty($safeQuery)) {
        $url .= '?' . http_build_query($safeQuery);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MICROCMS-API-KEY: ' . $apiKey]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    if ($response === false) {
        error_log('microCMS fetch failed: ' . $error);
        return [
            'status' => 502,
            'body' => ['error' => 'microCMS request failed'],
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'status' => 502,
            'body' => ['error' => 'Invalid microCMS response'],
        ];
    }

    return [
        'status' => $httpCode ?: 200,
        'body' => $decoded,
    ];
}

function highlander_send_json($result) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(isset($result['status']) ? $result['status'] : 200);
    echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
}
?>
