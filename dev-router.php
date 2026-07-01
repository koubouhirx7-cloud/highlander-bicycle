<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$routes = [
    '/api/create-checkout-session' => __DIR__ . '/api/create-checkout-session.php',
    '/api/confirm-checkout-session' => __DIR__ . '/api/confirm-checkout-session.php',
    '/api/stripe-webhook' => __DIR__ . '/api/stripe-webhook.php',
    '/api/order-to-hublink' => __DIR__ . '/api/order-to-hublink.php',
    '/api/microcms-posts' => __DIR__ . '/api/microcms-posts.php',
    '/api/microcms-products' => __DIR__ . '/api/microcms-products.php',
    '/api/microcms-logs' => __DIR__ . '/api/microcms-logs.php',
];

if (isset($routes[$path])) {
    require $routes[$path];
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/') {
    require __DIR__ . '/index.html';
    return true;
}

return false;
?>
