<?php
require_once dirname(__DIR__) . '/includes/shop-db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

highlander_admin_start_session();

$action = isset($_GET['action']) ? (string)$_GET['action'] : 'session';

try {
    if ($action === 'session') {
        highlander_admin_json(['ok' => true, 'authenticated' => highlander_admin_is_authenticated()]);
    }

    if ($action === 'login') {
        highlander_admin_require_method('POST');
        highlander_admin_login();
    }

    if ($action === 'logout') {
        highlander_admin_require_method('POST');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        highlander_admin_json(['ok' => true]);
    }

    highlander_admin_require_auth();

    $pdo = highlander_shop_pdo();
    if (!$pdo) {
        highlander_admin_json([
            'ok' => false,
            'error' => 'db_not_configured',
            'message' => '顧客管理用のデータベースがまだ設定されていません。SHOP_DB_* を .env に設定してください。'
        ], 503);
    }

    highlander_admin_ensure_customer_columns($pdo);

    if ($action === 'summary') {
        highlander_admin_json(['ok' => true, 'summary' => highlander_admin_summary($pdo)]);
    }

    if ($action === 'customers') {
        highlander_admin_json(['ok' => true, 'customers' => highlander_admin_customers($pdo)]);
    }

    if ($action === 'orders') {
        highlander_admin_json(['ok' => true, 'orders' => highlander_admin_orders($pdo)]);
    }

    if ($action === 'customer') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) highlander_admin_json(['ok' => false, 'message' => '顧客IDが不正です。'], 422);
        $customer = highlander_admin_customer($pdo, $id);
        if (!$customer) highlander_admin_json(['ok' => false, 'message' => '顧客情報が見つかりません。'], 404);
        highlander_admin_json([
            'ok' => true,
            'customer' => $customer,
            'orders' => highlander_admin_orders($pdo, ['customer_id' => $id, 'limit' => 30])
        ]);
    }

    if ($action === 'update_customer') {
        highlander_admin_require_method('POST');
        highlander_admin_update_customer($pdo);
    }

    highlander_admin_json(['ok' => false, 'message' => '未対応の操作です。'], 404);
} catch (Exception $e) {
    error_log('Admin shop API error: ' . $e->getMessage());
    highlander_admin_json(['ok' => false, 'message' => '管理画面の処理中にエラーが発生しました。'], 500);
}

function highlander_admin_start_session() {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('HL_ADMIN_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function highlander_admin_is_authenticated() {
    return !empty($_SESSION['highlander_admin_authenticated']);
}

function highlander_admin_require_auth() {
    if (!highlander_admin_is_authenticated()) {
        highlander_admin_json(['ok' => false, 'message' => '管理者ログインが必要です。'], 401);
    }
}

function highlander_admin_require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        highlander_admin_json(['ok' => false, 'message' => '許可されていないアクセスです。'], 405);
    }
}

function highlander_admin_input() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function highlander_admin_login() {
    $input = highlander_admin_input();
    $password = (string)($input['password'] ?? '');
    $hash = highlander_env('ADMIN_PASSWORD_HASH');
    $plain = highlander_env('ADMIN_PASSWORD');

    if (!$hash && !$plain) {
        highlander_admin_json([
            'ok' => false,
            'message' => '管理者パスワードが未設定です。ADMIN_PASSWORD_HASH を .env に設定してください。'
        ], 503);
    }

    $valid = false;
    if ($hash && password_verify($password, $hash)) {
        $valid = true;
    } elseif ($plain && hash_equals((string)$plain, $password)) {
        $valid = true;
    }

    if (!$valid) {
        highlander_admin_json(['ok' => false, 'message' => 'パスワードが違います。'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['highlander_admin_authenticated'] = true;
    $_SESSION['highlander_admin_logged_in_at'] = time();

    highlander_admin_json(['ok' => true, 'authenticated' => true]);
}

function highlander_admin_ensure_customer_columns($pdo) {
    static $done = false;
    if ($done) return;
    $done = true;

    $statements = [
        'ALTER TABLE shop_customers ADD COLUMN admin_note TEXT NULL',
        "ALTER TABLE shop_customers ADD COLUMN crm_link_status VARCHAR(40) NOT NULL DEFAULT 'unlinked'",
        'ALTER TABLE shop_customers ADD COLUMN hublink_customer_id VARCHAR(128) NULL',
        'ALTER TABLE shop_customers ADD COLUMN crm_linked_at DATETIME NULL',
        'ALTER TABLE shop_customers ADD INDEX idx_shop_customers_crm_link_status (crm_link_status)',
    ];

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Exception $e) {
            // Duplicate column/index errors are expected when the schema is already current.
        }
    }
}

function highlander_admin_summary($pdo) {
    $customerCount = intval($pdo->query('SELECT COUNT(*) FROM shop_customers')->fetchColumn());
    $orderCount = intval($pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
    $paidOrderCount = intval($pdo->query(
        "SELECT COUNT(*) FROM shop_orders
         WHERE payment_status IN ('paid', 'succeeded') OR stripe_payment_status = 'paid' OR status IN ('paid', 'completed')"
    )->fetchColumn());
    $totalAmount = intval($pdo->query(
        "SELECT COALESCE(SUM(amount_total), 0) FROM shop_orders
         WHERE payment_status IN ('paid', 'succeeded') OR stripe_payment_status = 'paid' OR status IN ('paid', 'completed')"
    )->fetchColumn());

    return [
        'customer_count' => $customerCount,
        'order_count' => $orderCount,
        'paid_order_count' => $paidOrderCount,
        'total_amount' => $totalAmount,
    ];
}

function highlander_admin_customers($pdo) {
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = highlander_admin_limit($_GET['limit'] ?? 50, 100);
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = 'WHERE c.name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q OR c.shipping_address LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }

    $stmt = $pdo->prepare(
        "SELECT
            c.id, c.google_uid, c.email, c.name, c.phone, c.shipping_address,
            c.admin_note, c.crm_link_status, c.hublink_customer_id, c.crm_linked_at,
            c.last_order_at, c.created_at, c.updated_at,
            COUNT(o.id) AS order_count,
            COALESCE(SUM(o.amount_total), 0) AS total_amount
         FROM shop_customers c
         LEFT JOIN shop_orders o ON o.customer_id = c.id
         $where
         GROUP BY c.id
         ORDER BY COALESCE(c.last_order_at, c.updated_at) DESC
         LIMIT $limit"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function highlander_admin_customer($pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT
            c.id, c.google_uid, c.email, c.name, c.phone, c.shipping_address,
            c.admin_note, c.crm_link_status, c.hublink_customer_id, c.crm_linked_at,
            c.last_order_at, c.created_at, c.updated_at,
            COUNT(o.id) AS order_count,
            COALESCE(SUM(o.amount_total), 0) AS total_amount
         FROM shop_customers c
         LEFT JOIN shop_orders o ON o.customer_id = c.id
         WHERE c.id = :id
         GROUP BY c.id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function highlander_admin_orders($pdo, $options = []) {
    $limit = highlander_admin_limit($options['limit'] ?? ($_GET['limit'] ?? 50), 100);
    $customerId = isset($options['customer_id']) ? intval($options['customer_id']) : intval($_GET['customer_id'] ?? 0);
    $q = trim((string)($_GET['q'] ?? ''));
    $where = [];
    $params = [];

    if ($customerId > 0) {
        $where[] = 'customer_id = :customer_id';
        $params[':customer_id'] = $customerId;
    }
    if ($q !== '') {
        $where[] = '(order_id LIKE :q OR customer_email LIKE :q OR customer_name LIKE :q OR customer_phone LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
        "SELECT id, order_id, customer_id, google_uid, customer_email, customer_name, customer_phone,
            shipping_address, amount_total, currency, payment_method, payment_status, status,
            stripe_payment_status, paid_at, created_at, updated_at
         FROM shop_orders
         $whereSql
         ORDER BY created_at DESC
         LIMIT $limit"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function highlander_admin_update_customer($pdo) {
    $input = highlander_admin_input();
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) highlander_admin_json(['ok' => false, 'message' => '顧客IDが不正です。'], 422);

    $status = (string)($input['crm_link_status'] ?? 'unlinked');
    $allowedStatuses = ['unlinked', 'reviewing', 'linked', 'hold'];
    if (!in_array($status, $allowedStatuses, true)) $status = 'unlinked';

    $stmt = $pdo->prepare(
        'UPDATE shop_customers
         SET name = :name,
             phone = :phone,
             shipping_address = :shipping_address,
             admin_note = :admin_note,
             crm_link_status = :crm_link_status,
             hublink_customer_id = NULLIF(:hublink_customer_id, \'\'),
             crm_linked_at = CASE
                WHEN :status_for_date = \'linked\' THEN COALESCE(crm_linked_at, NOW())
                ELSE crm_linked_at
             END,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':name' => trim((string)($input['name'] ?? '')),
        ':phone' => trim((string)($input['phone'] ?? '')),
        ':shipping_address' => trim((string)($input['shipping_address'] ?? '')),
        ':admin_note' => trim((string)($input['admin_note'] ?? '')),
        ':crm_link_status' => $status,
        ':hublink_customer_id' => trim((string)($input['hublink_customer_id'] ?? '')),
        ':status_for_date' => $status,
        ':id' => $id,
    ]);

    highlander_admin_json(['ok' => true, 'customer' => highlander_admin_customer($pdo, $id)]);
}

function highlander_admin_limit($value, $max) {
    $limit = intval($value);
    if ($limit <= 0) $limit = 50;
    return min($limit, $max);
}

function highlander_admin_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
