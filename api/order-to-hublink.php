<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/order-storage.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

$order = highlander_build_shop_order_from_payload($data);
$saved = highlander_save_order($order);

$token = highlander_env('HUBLINK_SHOP_TOKEN');
$tenantId = highlander_env('HUBLINK_TENANT_ID') ?: '1';
$endpoint = highlander_env('HUBLINK_INGEST_URL') ?: 'https://hub-link.jp/api/shop_order.php';

if (!$token) {
    error_log('HUBLINK_SHOP_TOKEN is not set. Saved order and skipped CRM ingest.');
    echo json_encode([
        "ok" => true,
        "saved" => $saved,
        "order_id" => $order['order_id'],
        "hublink" => ["ok" => false, "skipped" => true],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    "tenant_id" => $tenantId,
    "order_id" => $order['order_id'],
    "customer" => $order['customer'],
    "items" => $order['items'],
    "amount_total" => $order['amount_total'],
    "payment_method" => $order['payment_method'],
    "payment_status" => $order['payment_status'],
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
    $hublinkResult = ["ok" => false, "error" => "ingest_failed"];
    highlander_update_order($order['order_id'], ["hublink_result" => $hublinkResult]);
    echo json_encode([
        "ok" => true,
        "saved" => $saved,
        "order_id" => $order['order_id'],
        "hublink" => $hublinkResult,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($response, true);
$hublinkResult = [
    "ok" => $httpCode >= 200 && $httpCode < 300,
    "status" => $httpCode,
    "body" => is_array($decoded) ? $decoded : $response,
];
highlander_update_order($order['order_id'], ["hublink_result" => $hublinkResult]);

echo json_encode([
    "ok" => true,
    "saved" => $saved,
    "order_id" => $order['order_id'],
    "hublink" => $hublinkResult,
], JSON_UNESCAPED_UNICODE);

function highlander_build_shop_order_from_payload($data) {
    $orderId = isset($data['order_id']) ? highlander_sanitize_order_id($data['order_id']) : '';
    if ($orderId === '') $orderId = highlander_generate_order_id();

    $items = highlander_normalize_payload_items(isset($data['items']) ? $data['items'] : []);
    $amountTotal = array_reduce($items, function ($sum, $item) {
        return $sum + intval($item['subtotal']);
    }, 0);

    $paymentMethod = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($data['payment_method'] ?? 'manual'));
    $paymentStatus = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($data['payment_status'] ?? 'pending'));

    return [
        "order_id" => $orderId,
        "customer" => highlander_normalize_payload_customer(isset($data['customer']) ? $data['customer'] : []),
        "items" => $items,
        "amount_total" => $amountTotal,
        "currency" => "jpy",
        "payment_method" => $paymentMethod,
        "payment_status" => $paymentStatus,
        "status" => $paymentStatus,
        "source" => "highlander_shop",
    ];
}

function highlander_normalize_payload_customer($customer) {
    if (!is_array($customer)) $customer = [];
    return [
        "name" => trim((string)($customer['name'] ?? '')),
        "email" => trim((string)($customer['email'] ?? '')),
        "phone" => trim((string)($customer['phone'] ?? '')),
        "address" => trim((string)($customer['address'] ?? '')),
        "uid" => trim((string)($customer['uid'] ?? '')),
        "member_logged_in" => !empty($customer['member_logged_in']),
        "identity_provider" => trim((string)($customer['identity_provider'] ?? '')),
        "delivery_profile_saved" => !empty($customer['delivery_profile_saved']),
    ];
}

function highlander_normalize_payload_items($items) {
    if (!is_array($items)) return [];
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($item['id'] ?? ''));
        $name = trim((string)($item['name'] ?? '商品'));
        $price = intval($item['price'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);
        if ($quantity <= 0) continue;
        if ($quantity > 20) $quantity = 20;
        if ($price < 0) $price = 0;

        $normalized[] = [
            "id" => $id,
            "name" => $name,
            "price" => $price,
            "quantity" => $quantity,
            "subtotal" => $price * $quantity,
        ];
    }
    return $normalized;
}
?>
