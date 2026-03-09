<?php
require_once __DIR__ . '/../init.php';

// simulate logged in user
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION['user_id'] = 2;

$user = get_user_by_username('jenjen-user');
$posts = get_posts_for_user($user['id'], 1);
$first = $posts[0];
print_r(array_keys($first));
print_r($first);
