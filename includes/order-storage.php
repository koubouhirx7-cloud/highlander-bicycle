<?php
function highlander_order_storage_dir() {
    $dir = dirname(__DIR__) . '/storage/orders';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function highlander_sanitize_order_id($orderId) {
    $orderId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$orderId);
    return substr($orderId, 0, 80);
}

function highlander_generate_order_id() {
    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -8);
    }
    return 'hl_' . date('YmdHis') . '_' . $suffix;
}

function highlander_order_path($orderId) {
    $safeId = highlander_sanitize_order_id($orderId);
    if ($safeId === '') return null;
    return highlander_order_storage_dir() . '/' . $safeId . '.json';
}

function highlander_save_order($order) {
    if (!isset($order['order_id'])) return false;
    $path = highlander_order_path($order['order_id']);
    if (!$path) return false;
    $order['updated_at'] = date('c');
    if (!isset($order['created_at'])) {
        $order['created_at'] = date('c');
    }
    return file_put_contents($path, json_encode($order, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function highlander_load_order($orderId) {
    $path = highlander_order_path($orderId);
    if (!$path || !is_file($path)) return null;
    $decoded = json_decode(file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function highlander_update_order($orderId, $changes) {
    $order = highlander_load_order($orderId);
    if (!$order) return null;
    foreach ($changes as $key => $value) {
        $order[$key] = $value;
    }
    highlander_save_order($order);
    return $order;
}
?>
