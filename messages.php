<?php
require_once __DIR__ . '/init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$action = $_REQUEST['action'] ?? '';

if ($action === 'conversations') {
    $conversations = get_conversations($user['id']);
    respondJson(['status' => 'ok', 'conversations' => $conversations]);
}

if ($action === 'messages') {
    $with = parse_request_int('with');
    if (!$with) {
        respondJson(['status' => 'error', 'message' => 'Missing user id']);
    }

    $messages = get_messages_between($user['id'], $with, 100);
    mark_messages_as_read($user['id'], $with);
    respondJson(['status' => 'ok', 'messages' => $messages]);
}

if ($action === 'send') {
    $to = parse_request_int('to');
    $content = trim($_POST['content'] ?? '');
    if (!$to || $content === '') {
        respondJson(['status' => 'error', 'message' => 'Missing recipient or message']);
    }

    $msgId = insert_message($user['id'], $to, $content);
    insert_notification($to, $user['id'], 'message');

    $message = [
        'id' => $msgId,
        'sender_id' => $user['id'],
        'receiver_id' => $to,
        'content' => $content,
        'created_at' => date('c'),
        'sender_username' => $user['username'],
        'sender_name' => $user['full_name'],
        'sender_pic' => $user['profile_pic'],
    ];

    respondJson(['status' => 'ok', 'message' => $message]);
}

if ($action === 'delete') {
    $msgId = parse_request_int('id');
    if (!$msgId) {
        respondJson(['status' => 'error', 'message' => 'Missing message id']);
    }
    delete_message($msgId, $user['id']);
    respondJson(['status' => 'ok']);
}

respondJson(['status' => 'error', 'message' => 'Unknown action']);
