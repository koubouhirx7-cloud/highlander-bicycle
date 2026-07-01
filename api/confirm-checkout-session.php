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
    highlander_confirm_json(405, ["error" => "Method Not Allowed"]);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    highlander_confirm_json(400, ["error" => "Invalid JSON"]);
}

$sessionId = isset($data['session_id']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string)$data['session_id']) : '';
$orderId = isset($data['order_id']) ? highlander_sanitize_order_id($data['order_id']) : '';
$isMock = !empty($data['mock']);

if ($isMock) {
    if ($orderId === '') {
        highlander_confirm_json(400, ["error" => "Order ID is required"]);
    }
    $order = highlander_load_order($orderId);
    if (!$order) {
        highlander_confirm_json(404, ["error" => "Order not found"]);
    }

    $order = highlander_update_order($orderId, [
        "status" => "mock_paid",
        "payment_status" => "paid_mock",
        "paid_at" => date('c'),
    ]);
    $hublink = highlander_notify_hublink($order);
    $order = highlander_update_order($orderId, ["hublink_result" => $hublink]);

    highlander_confirm_json(200, [
        "ok" => true,
        "mock" => true,
        "payment_status" => "paid_mock",
        "order" => highlander_public_order($order),
        "hublink" => $hublink,
    ]);
}

if ($sessionId === '') {
    highlander_confirm_json(400, ["error" => "Session ID is required"]);
}

$stripeSecretKey = highlander_env('STRIPE_SECRET_KEY');
if (!$stripeSecretKey || $stripeSecretKey === 'undefined' || $stripeSecretKey === '${STRIPE_SECRET_KEY}') {
    highlander_confirm_json(500, ["error" => "Stripe configuration is missing"]);
}

$session = highlander_retrieve_stripe_session($sessionId, $stripeSecretKey);
if (!$session['ok']) {
    highlander_confirm_json($session['status'], ["error" => "Stripe session could not be confirmed"]);
}

$stripeSession = $session['body'];
$sessionOrderId = isset($stripeSession['client_reference_id']) ? highlander_sanitize_order_id($stripeSession['client_reference_id']) : '';
if ($orderId === '') $orderId = $sessionOrderId;
if ($orderId === '' || ($sessionOrderId !== '' && $sessionOrderId !== $orderId)) {
    highlander_confirm_json(400, ["error" => "Order ID mismatch"]);
}

$order = highlander_load_order($orderId);
if (!$order) {
    highlander_confirm_json(404, ["error" => "Order not found"]);
}

$paymentStatus = isset($stripeSession['payment_status']) ? $stripeSession['payment_status'] : 'unknown';
$stripeAmount = isset($stripeSession['amount_total']) ? intval($stripeSession['amount_total']) : null;
$expectedAmount = isset($order['amount_total']) ? intval($order['amount_total']) : null;
if ($stripeAmount !== null && $expectedAmount !== null && $stripeAmount !== $expectedAmount) {
    $order = highlander_update_order($orderId, [
        "status" => "amount_mismatch",
        "payment_status" => $paymentStatus,
        "stripe_session_id" => $sessionId,
        "stripe_amount_total" => $stripeAmount,
    ]);
    highlander_confirm_json(409, ["error" => "Amount mismatch", "order" => highlander_public_order($order)]);
}

$changes = [
    "stripe_session_id" => $sessionId,
    "stripe_payment_status" => $paymentStatus,
    "stripe_amount_total" => $stripeAmount,
    "status" => $paymentStatus === 'paid' ? "paid" : "stripe_" . $paymentStatus,
    "payment_status" => $paymentStatus,
];
if ($paymentStatus === 'paid') {
    $changes['paid_at'] = date('c');
}
$order = highlander_update_order($orderId, $changes);
$hublink = null;
if ($paymentStatus === 'paid') {
    $hublink = highlander_notify_hublink($order);
    $order = highlander_update_order($orderId, ["hublink_result" => $hublink]);
}

highlander_confirm_json(200, [
    "ok" => $paymentStatus === 'paid',
    "payment_status" => $paymentStatus,
    "order" => highlander_public_order($order),
    "hublink" => $hublink,
]);

function highlander_retrieve_stripe_session($sessionId, $stripeSecretKey) {
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_USERPWD, $stripeSecretKey . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Stripe-Version: 2026-02-25.clover']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    $decoded = json_decode($response, true);
    if ($response === false || $httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        error_log('Stripe session retrieve failed: HTTP ' . $httpCode . ' ' . $response);
        return ["ok" => false, "status" => 502, "body" => null];
    }
    return ["ok" => true, "status" => $httpCode, "body" => $decoded];
}

function highlander_notify_hublink($order) {
    $token = highlander_env('HUBLINK_SHOP_TOKEN');
    $tenantId = highlander_env('HUBLINK_TENANT_ID') ?: '1';
    $endpoint = highlander_env('HUBLINK_INGEST_URL') ?: 'https://hub-link.jp/api/shop_order.php';

    if (!$token) {
        return ["ok" => false, "skipped" => true, "reason" => "HUBLINK_SHOP_TOKEN missing"];
    }

    $payload = [
        "tenant_id" => $tenantId,
        "order_id" => $order['order_id'] ?? null,
        "customer" => $order['customer'] ?? null,
        "items" => $order['items'] ?? [],
        "amount_total" => $order['amount_total'] ?? null,
        "payment_method" => $order['payment_method'] ?? 'stripe',
        "payment_status" => $order['payment_status'] ?? null,
        "stripe_session_id" => $order['stripe_session_id'] ?? null,
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    $decoded = json_decode($response, true);
    return [
        "ok" => $httpCode >= 200 && $httpCode < 300,
        "status" => $httpCode,
        "body" => is_array($decoded) ? $decoded : $response,
    ];
}

function highlander_public_order($order) {
    if (!is_array($order)) return null;
    return [
        "order_id" => $order['order_id'] ?? null,
        "items" => $order['items'] ?? [],
        "amount_total" => $order['amount_total'] ?? null,
        "currency" => $order['currency'] ?? 'jpy',
        "payment_method" => $order['payment_method'] ?? null,
        "payment_status" => $order['payment_status'] ?? null,
        "status" => $order['status'] ?? null,
    ];
}

function highlander_confirm_json($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
