<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$token = getEnvValue('HUBLINK_SHOP_TOKEN');
$tenantId = getEnvValue('HUBLINK_TENANT_ID') ?: '1';
$endpoint = getEnvValue('HUBLINK_INGEST_URL') ?: 'https://hub-link.jp/api/shop_order.php';

if (!$token) {
    error_log('HUBLINK_SHOP_TOKEN is not set. Skipping CRM ingest.');
    echo json_encode(["ok" => false, "skipped" => true]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

$payload = [
    "tenant_id" => $tenantId,
    "order_id" => isset($data['order_id']) ? $data['order_id'] : null,
    "customer" => isset($data['customer']) ? $data['customer'] : null,
    "items" => isset($data['items']) ? $data['items'] : [],
    "payment_method" => isset($data['payment_method']) ? $data['payment_method'] : null,
    "payment_status" => isset($data['payment_status']) ? $data['payment_status'] : null,
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Shop-Token: ' . $token,
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (PHP_VERSION_ID < 80500) {
    curl_close($ch);
}

if ($response === false) {
    error_log('HubLink ingest failed: ' . $error);
    echo json_encode(["ok" => false, "error" => "ingest_failed"]);
    exit;
}

$decoded = json_decode($response, true);
echo json_encode([
    "ok" => $httpCode >= 200 && $httpCode < 300,
    "hublink" => is_array($decoded) ? $decoded : ["raw" => $response],
]);

function getEnvValue($key) {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    $value = getenv($key);
    if ($value !== false && $value !== '') return $value;

    $paths = [
        dirname(__DIR__) . '/.env',
        dirname(__DIR__, 2) . '/.env',
    ];

    foreach ($paths as $envPath) {
        if (!file_exists($envPath)) continue;
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;

            list($name, $value) = explode('=', $line, 2);
            if (trim($name) !== $key) continue;

            $value = trim($value);
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }
            return $value;
        }
    }

    return null;
}
?>
