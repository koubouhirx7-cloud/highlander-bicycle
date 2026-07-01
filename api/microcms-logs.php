<?php
require_once dirname(__DIR__) . '/includes/microcms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    highlander_send_json([
        'status' => 405,
        'body' => ['error' => 'Method Not Allowed'],
    ]);
    exit;
}

$contentId = isset($_GET['id']) ? $_GET['id'] : null;
highlander_send_json(highlander_microcms_fetch('logs', $contentId, $_GET));
?>
