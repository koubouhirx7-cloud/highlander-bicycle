<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once dirname(__DIR__) . '/includes/microcms.php';
require_once dirname(__DIR__) . '/includes/order-storage.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    highlander_checkout_json(405, ["error" => "Method Not Allowed"]);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    highlander_checkout_json(400, ["error" => "Invalid JSON"]);
}

$cart = isset($data['cart']) && is_array($data['cart']) ? $data['cart'] : [];
$customer = isset($data['customer']) && is_array($data['customer']) ? $data['customer'] : [];

if (count($cart) === 0) {
    highlander_checkout_json(400, ["error" => "Cart is empty"]);
}

$customer = highlander_normalize_customer($customer);
if (!$customer['name'] || !$customer['email'] || !$customer['phone'] || !$customer['address']) {
    highlander_checkout_json(400, ["error" => "Customer information is incomplete"]);
}

$items = highlander_build_verified_items($cart);
$totalAmount = array_reduce($items, function ($sum, $item) {
    return $sum + ($item['price'] * $item['quantity']);
}, 0);

if ($totalAmount <= 0) {
    highlander_checkout_json(400, ["error" => "Invalid order amount"]);
}

$orderId = isset($data['order_id']) ? highlander_sanitize_order_id($data['order_id']) : '';
if ($orderId === '') {
    $orderId = highlander_generate_order_id();
}

$order = [
    "order_id" => $orderId,
    "customer" => $customer,
    "items" => $items,
    "amount_total" => $totalAmount,
    "currency" => "jpy",
    "payment_method" => "stripe",
    "payment_status" => "pending",
    "status" => "checkout_prepared",
    "source" => "highlander_shop",
];
highlander_save_order($order);

$stripeSecretKey = highlander_env('STRIPE_SECRET_KEY');
if (!$stripeSecretKey || $stripeSecretKey === 'undefined' || $stripeSecretKey === '${STRIPE_SECRET_KEY}') {
    error_log("STRIPE_SECRET_KEY is not set. Redirecting to mock payment screen.");
    highlander_update_order($orderId, [
        "status" => "mock_checkout_prepared",
        "payment_status" => "pending_mock",
    ]);
    highlander_checkout_json(200, [
        "url" => "/mock-stripe-checkout.html?amount=" . $totalAmount . "&order_id=" . rawurlencode($orderId),
        "order_id" => $orderId,
        "mock" => true,
    ]);
}

$origin = highlander_get_origin();
$stripeData = [
    'mode' => 'payment',
    'success_url' => $origin . '/order-success.html?session_id={CHECKOUT_SESSION_ID}&order_id=' . rawurlencode($orderId),
    'cancel_url' => $origin . '/checkout.html?checkout=cancelled&order_id=' . rawurlencode($orderId),
    'client_reference_id' => $orderId,
    'customer_email' => $customer['email'],
    'billing_address_collection' => 'required',
    'phone_number_collection' => [
        'enabled' => 'true',
    ],
    'shipping_address_collection' => [
        'allowed_countries' => ['JP'],
    ],
    'metadata' => [
        'order_id' => $orderId,
        'customer_name' => highlander_metadata_value($customer['name']),
        'customer_phone' => highlander_metadata_value($customer['phone']),
        'source' => 'highlander_shop',
    ],
    'payment_intent_data' => [
        'metadata' => [
            'order_id' => $orderId,
            'source' => 'highlander_shop',
        ],
    ],
    'line_items' => [],
];

foreach ($items as $index => $item) {
    $stripeData['line_items'][$index] = [
        'price_data' => [
            'currency' => 'jpy',
            'product_data' => [
                'name' => $item['name'],
                'metadata' => [
                    'product_id' => $item['id'],
                ],
            ],
            'unit_amount' => $item['price'],
        ],
        'quantity' => $item['quantity'],
    ];
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($stripeData));
curl_setopt($ch, CURLOPT_USERPWD, $stripeSecretKey . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Stripe-Version: 2026-02-25.clover',
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (PHP_VERSION_ID < 80500) {
    curl_close($ch);
}

if ($response === false) {
    error_log('Stripe Checkout session request failed: ' . $error);
    highlander_update_order($orderId, ["status" => "stripe_request_failed"]);
    highlander_checkout_json(502, ["error" => "Stripe request failed"]);
}

$session = json_decode($response, true);
if ($httpCode < 200 || $httpCode >= 300 || !is_array($session) || empty($session['url'])) {
    error_log('Stripe Checkout session failed: HTTP ' . $httpCode . ' ' . $response);
    highlander_update_order($orderId, [
        "status" => "stripe_session_failed",
        "stripe_error" => is_array($session) && isset($session['error']['message']) ? $session['error']['message'] : 'unknown',
    ]);
    highlander_checkout_json(500, ["error" => "Stripe checkout session could not be created"]);
}

highlander_update_order($orderId, [
    "status" => "stripe_checkout_created",
    "stripe_session_id" => isset($session['id']) ? $session['id'] : null,
]);

highlander_checkout_json(200, [
    "url" => $session['url'],
    "order_id" => $orderId,
]);

function highlander_normalize_customer($customer) {
    return [
        "name" => trim((string)($customer['name'] ?? '')),
        "email" => trim((string)($customer['email'] ?? '')),
        "phone" => trim((string)($customer['phone'] ?? '')),
        "address" => trim((string)($customer['address'] ?? '')),
        "uid" => trim((string)($customer['uid'] ?? '')),
    ];
}

function highlander_build_verified_items($cart) {
    $items = [];
    foreach ($cart as $cartItem) {
        $id = isset($cartItem['id']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string)$cartItem['id']) : '';
        $quantity = isset($cartItem['quantity']) ? intval($cartItem['quantity']) : 0;
        if ($id === '' || $quantity <= 0) continue;
        if ($quantity > 20) $quantity = 20;

        $result = highlander_microcms_fetch('products', $id, []);
        $status = isset($result['status']) ? intval($result['status']) : 500;
        $product = isset($result['body']) && is_array($result['body']) ? $result['body'] : null;
        if ($status < 200 || $status >= 300 || !$product) {
            highlander_checkout_json(400, ["error" => "Product not found", "product_id" => $id]);
        }

        $name = trim((string)($product['title'] ?? '商品'));
        $price = highlander_parse_price($product['price'] ?? null);
        if ($price <= 0) {
            highlander_checkout_json(400, ["error" => "Product price is invalid", "product_id" => $id]);
        }

        $items[] = [
            "id" => $id,
            "name" => $name,
            "price" => $price,
            "quantity" => $quantity,
            "subtotal" => $price * $quantity,
        ];
    }

    if (count($items) === 0) {
        highlander_checkout_json(400, ["error" => "Cart items are invalid"]);
    }
    return $items;
}

function highlander_parse_price($value) {
    if (is_int($value) || is_float($value)) return intval($value);
    $digits = preg_replace('/[^\d]/', '', (string)$value);
    return $digits === '' ? 0 : intval($digits);
}

function highlander_metadata_value($value) {
    $value = (string)$value;
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 480);
    }
    return substr($value, 0, 480);
}

function highlander_get_origin() {
    $proto = 'http';
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        $proto = 'https';
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $proto = 'https';
    }
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    if ($host === 'www.high-lander2.com' || $host === 'xs001364.xsrv.jp') {
        $host = 'high-lander2.com';
    }
    return $proto . '://' . $host;
}

function highlander_checkout_json($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
