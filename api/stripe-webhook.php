<?php
header("Content-Type: application/json; charset=utf-8");

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/order-storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
$webhookSecret = highlander_env('STRIPE_WEBHOOK_SECRET');

if (!$webhookSecret) {
    http_response_code(500);
    echo json_encode(["error" => "Stripe webhook secret is missing"]);
    exit;
}

if (!highlander_verify_stripe_signature($payload, $signature, $webhookSecret)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Stripe signature"]);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['type'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Stripe event"]);
    exit;
}

if ($event['type'] === 'checkout.session.completed' || $event['type'] === 'checkout.session.async_payment_succeeded') {
    $session = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : [];
    $orderId = isset($session['client_reference_id']) ? highlander_sanitize_order_id($session['client_reference_id']) : '';
    if ($orderId === '' && isset($session['metadata']['order_id'])) {
        $orderId = highlander_sanitize_order_id($session['metadata']['order_id']);
    }

    if ($orderId !== '') {
        $order = highlander_load_order($orderId);
        if ($order) {
            $paymentStatus = isset($session['payment_status']) ? $session['payment_status'] : 'paid';
            $order = highlander_update_order($orderId, [
                "status" => $paymentStatus === 'paid' ? "paid" : "stripe_" . $paymentStatus,
                "payment_status" => $paymentStatus,
                "stripe_session_id" => isset($session['id']) ? $session['id'] : null,
                "stripe_amount_total" => isset($session['amount_total']) ? intval($session['amount_total']) : null,
                "paid_at" => $paymentStatus === 'paid' ? date('c') : ($order['paid_at'] ?? null),
                "stripe_webhook_event_id" => isset($event['id']) ? $event['id'] : null,
            ]);

            if ($paymentStatus === 'paid') {
                $hublink = highlander_webhook_notify_hublink($order);
                highlander_update_order($orderId, ["hublink_result" => $hublink]);
            }
        }
    }
}

echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);

function highlander_verify_stripe_signature($payload, $header, $secret) {
    if (!$payload || !$header || !$secret) return false;
    $parts = [];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]][] = $kv[1];
        }
    }
    if (empty($parts['t'][0]) || empty($parts['v1'])) return false;
    $timestamp = intval($parts['t'][0]);
    if (abs(time() - $timestamp) > 300) return false;

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    foreach ($parts['v1'] as $signature) {
        if (hash_equals($expected, $signature)) return true;
    }
    return false;
}

function highlander_webhook_notify_hublink($order) {
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
?>
