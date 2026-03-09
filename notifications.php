<?php
require_once __DIR__ . '/init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$action = $_REQUEST['action'] ?? '';

if ($action === 'mark_read') {
    mark_notifications_as_read($user['id']);
    respondJson(['status' => 'ok']);
}

respondJson(['status' => 'error', 'message' => 'Unknown action']);
