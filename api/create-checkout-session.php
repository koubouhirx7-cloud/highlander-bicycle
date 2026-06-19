<?php
// Set headers for JSON response and CORS
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure it is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Parse JSON request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$cart = isset($data['cart']) ? $data['cart'] : null;
if (!$cart || !is_array($cart) || count($cart) === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Cart is empty or invalid"]);
    exit;
}

// Calculate total amount for fallback redirection
$totalAmount = 0;
foreach ($cart as $item) {
    $totalAmount += intval($item['price']) * intval($item['quantity']);
}

// Load Stripe Secret Key from environment variables or .env file
$stripeSecretKey = getEnvValue('STRIPE_SECRET_KEY');

if (!$stripeSecretKey || $stripeSecretKey === 'undefined' || $stripeSecretKey === '${STRIPE_SECRET_KEY}') {
    // If Stripe secret key is not set, log warning (internally) and fallback to mockup checkout screen
    error_log("STRIPE_SECRET_KEY is not set. Redirecting to mock payment screen.");
    echo json_encode([
        "url" => "/mock-stripe-checkout.html?amount=" . $totalAmount
    ]);
    exit;
}

// Prepare Stripe Checkout Session parameters
$stripeData = [
    'mode' => 'payment',
    'success_url' => getOrigin() . '/order-success.html?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => getOrigin() . '/checkout.html',
    'payment_method_types' => ['card']
];

$i = 0;
foreach ($cart as $item) {
    $stripeData["line_items"][$i] = [
        'price_data' => [
            'currency' => 'jpy',
            'product_data' => [
                'name' => $item['name']
            ],
            'unit_amount' => intval($item['price'])
        ],
        'quantity' => intval($item['quantity'])
    ];
    $i++;
}

// Format payload for x-www-form-urlencoded
$postFields = http_build_query($stripeData);

// Send POST request to Stripe REST API using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/checkout/sessions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_USERPWD, $stripeSecretKey . ":"); // Auth using API Key as username and empty password
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode([
        "error" => "Stripe API Request Failed",
        "details" => json_decode($response, true)
    ]);
    exit;
}

$session = json_decode($response, true);
echo json_encode(["url" => $session['url']]);

/**
 * Loads configuration value from environmental variables or parses .env file.
 */
function getEnvValue($key) {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];

    // Read from .env in the parent directory
    $envPath = dirname(__DIR__) . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Ignore comments
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === $key) {
                // Strip outer quotes if present
                if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }
                return $value;
            }
        }
    }
    return null;
}

/**
 * Resolves the dynamic host origin (domain with protocol).
 */
function getOrigin() {
    $proto = 'http';
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        $proto = 'https';
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $proto = 'https';
    }
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return $proto . '://' . $host;
}
?>
