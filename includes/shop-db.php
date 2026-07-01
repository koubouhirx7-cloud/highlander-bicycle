<?php
require_once __DIR__ . '/env.php';

function highlander_shop_db_config() {
    $dsn = highlander_env('SHOP_DB_DSN');
    if ($dsn) {
        return [
            'dsn' => $dsn,
            'user' => highlander_env('SHOP_DB_USER'),
            'password' => highlander_env('SHOP_DB_PASSWORD') ?: '',
        ];
    }

    $host = highlander_env('SHOP_DB_HOST');
    $name = highlander_env('SHOP_DB_NAME');
    $user = highlander_env('SHOP_DB_USER');
    if (!$host || !$name || !$user) return null;

    $charset = highlander_env('SHOP_DB_CHARSET') ?: 'utf8mb4';
    $port = highlander_env('SHOP_DB_PORT');
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
    if ($port) $dsn .= ';port=' . $port;

    return [
        'dsn' => $dsn,
        'user' => $user,
        'password' => highlander_env('SHOP_DB_PASSWORD') ?: '',
    ];
}

function highlander_shop_pdo() {
    static $pdo = null;
    static $attempted = false;
    if ($attempted) return $pdo;
    $attempted = true;

    if (!class_exists('PDO')) {
        error_log('PDO is not available. Shop DB storage is disabled.');
        return null;
    }

    $config = highlander_shop_db_config();
    if (!$config) return null;

    try {
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Exception $e) {
        error_log('Shop DB connection failed: ' . $e->getMessage());
        $pdo = null;
    }

    return $pdo;
}

function highlander_db_save_order($order) {
    $pdo = highlander_shop_pdo();
    if (!$pdo || !is_array($order) || empty($order['order_id'])) return false;

    try {
        $customerId = highlander_db_upsert_customer($pdo, $order);
        highlander_db_upsert_order($pdo, $order, $customerId);
        highlander_db_replace_order_items($pdo, $order);
        return true;
    } catch (Exception $e) {
        error_log('Shop DB order save failed: ' . $e->getMessage());
        return false;
    }
}

function highlander_db_load_order($orderId) {
    $pdo = highlander_shop_pdo();
    if (!$pdo || !$orderId) return null;

    try {
        $stmt = $pdo->prepare('SELECT raw_order FROM shop_orders WHERE order_id = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['raw_order'])) return null;

        $decoded = json_decode($row['raw_order'], true);
        return is_array($decoded) ? $decoded : null;
    } catch (Exception $e) {
        error_log('Shop DB order load failed: ' . $e->getMessage());
        return null;
    }
}

function highlander_db_upsert_customer($pdo, $order) {
    $customer = isset($order['customer']) && is_array($order['customer']) ? $order['customer'] : [];
    $googleUid = trim((string)($customer['uid'] ?? ''));
    $email = trim((string)($customer['email'] ?? ''));
    $name = trim((string)($customer['name'] ?? ''));
    $phone = trim((string)($customer['phone'] ?? ''));
    $address = trim((string)($customer['address'] ?? ''));
    if ($googleUid === '' && $email === '') return null;

    $customerId = highlander_db_find_customer_id($pdo, $googleUid, $email);
    if ($customerId) {
        $stmt = $pdo->prepare(
            'UPDATE shop_customers
             SET google_uid = COALESCE(NULLIF(:google_uid, \'\'), google_uid),
                 email = COALESCE(NULLIF(:email, \'\'), email),
                 name = COALESCE(NULLIF(:name, \'\'), name),
                 phone = COALESCE(NULLIF(:phone, \'\'), phone),
                 shipping_address = COALESCE(NULLIF(:shipping_address, \'\'), shipping_address),
                 last_order_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':google_uid' => $googleUid,
            ':email' => $email,
            ':name' => $name,
            ':phone' => $phone,
            ':shipping_address' => $address,
            ':id' => $customerId,
        ]);
        return intval($customerId);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO shop_customers
         (google_uid, email, name, phone, shipping_address, last_order_at, created_at, updated_at)
         VALUES
         (NULLIF(:google_uid, \'\'), :email, :name, :phone, :shipping_address, NOW(), NOW(), NOW())'
    );
    $stmt->execute([
        ':google_uid' => $googleUid,
        ':email' => $email,
        ':name' => $name,
        ':phone' => $phone,
        ':shipping_address' => $address,
    ]);
    return intval($pdo->lastInsertId());
}

function highlander_db_find_customer_id($pdo, $googleUid, $email) {
    if ($googleUid !== '') {
        $stmt = $pdo->prepare('SELECT id FROM shop_customers WHERE google_uid = :google_uid LIMIT 1');
        $stmt->execute([':google_uid' => $googleUid]);
        $row = $stmt->fetch();
        if ($row) return intval($row['id']);
    }

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM shop_customers WHERE email = :email ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        if ($row) return intval($row['id']);
    }

    return null;
}

function highlander_db_upsert_order($pdo, $order, $customerId) {
    $customer = isset($order['customer']) && is_array($order['customer']) ? $order['customer'] : [];
    $rawOrder = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hublinkResult = isset($order['hublink_result'])
        ? json_encode($order['hublink_result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $stmt = $pdo->prepare(
        'INSERT INTO shop_orders
         (order_id, customer_id, google_uid, customer_email, customer_name, customer_phone, shipping_address,
          amount_total, currency, payment_method, payment_status, status,
          stripe_session_id, stripe_payment_status, stripe_amount_total,
          hublink_result, raw_order, paid_at, created_at, updated_at)
         VALUES
         (:order_id, :customer_id, NULLIF(:google_uid, \'\'), :customer_email, :customer_name, :customer_phone, :shipping_address,
          :amount_total, :currency, :payment_method, :payment_status, :status,
          :stripe_session_id, :stripe_payment_status, :stripe_amount_total,
          :hublink_result, :raw_order, :paid_at, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
          customer_id = VALUES(customer_id),
          google_uid = VALUES(google_uid),
          customer_email = VALUES(customer_email),
          customer_name = VALUES(customer_name),
          customer_phone = VALUES(customer_phone),
          shipping_address = VALUES(shipping_address),
          amount_total = VALUES(amount_total),
          currency = VALUES(currency),
          payment_method = VALUES(payment_method),
          payment_status = VALUES(payment_status),
          status = VALUES(status),
          stripe_session_id = VALUES(stripe_session_id),
          stripe_payment_status = VALUES(stripe_payment_status),
          stripe_amount_total = VALUES(stripe_amount_total),
          hublink_result = VALUES(hublink_result),
          raw_order = VALUES(raw_order),
          paid_at = VALUES(paid_at),
          updated_at = NOW()'
    );

    $stmt->execute([
        ':order_id' => $order['order_id'],
        ':customer_id' => $customerId,
        ':google_uid' => trim((string)($customer['uid'] ?? '')),
        ':customer_email' => trim((string)($customer['email'] ?? '')),
        ':customer_name' => trim((string)($customer['name'] ?? '')),
        ':customer_phone' => trim((string)($customer['phone'] ?? '')),
        ':shipping_address' => trim((string)($customer['address'] ?? '')),
        ':amount_total' => intval($order['amount_total'] ?? 0),
        ':currency' => $order['currency'] ?? 'jpy',
        ':payment_method' => $order['payment_method'] ?? '',
        ':payment_status' => $order['payment_status'] ?? '',
        ':status' => $order['status'] ?? '',
        ':stripe_session_id' => $order['stripe_session_id'] ?? null,
        ':stripe_payment_status' => $order['stripe_payment_status'] ?? null,
        ':stripe_amount_total' => isset($order['stripe_amount_total']) ? intval($order['stripe_amount_total']) : null,
        ':hublink_result' => $hublinkResult,
        ':raw_order' => $rawOrder,
        ':paid_at' => highlander_mysql_datetime($order['paid_at'] ?? null),
    ]);
}

function highlander_db_replace_order_items($pdo, $order) {
    $orderId = $order['order_id'];
    $pdo->prepare('DELETE FROM shop_order_items WHERE order_id = :order_id')->execute([':order_id' => $orderId]);

    $items = isset($order['items']) && is_array($order['items']) ? $order['items'] : [];
    if (!$items) return;

    $stmt = $pdo->prepare(
        'INSERT INTO shop_order_items
         (order_id, product_id, product_name, unit_price, quantity, subtotal, raw_item, created_at)
         VALUES
         (:order_id, :product_id, :product_name, :unit_price, :quantity, :subtotal, :raw_item, NOW())'
    );

    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $unitPrice = intval($item['price'] ?? $item['unit_price'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);
        $subtotal = intval($item['subtotal'] ?? ($unitPrice * $quantity));
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_id' => trim((string)($item['id'] ?? $item['product_id'] ?? '')),
            ':product_name' => trim((string)($item['name'] ?? $item['product_name'] ?? '')),
            ':unit_price' => $unitPrice,
            ':quantity' => $quantity,
            ':subtotal' => $subtotal,
            ':raw_item' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

function highlander_mysql_datetime($value) {
    if (!$value) return null;
    $timestamp = strtotime((string)$value);
    if (!$timestamp) return null;
    return date('Y-m-d H:i:s', $timestamp);
}
?>
