<?php
require_once __DIR__ . '/../init.php';
$user = get_user_by_username('jenjen-user');
if (!$user) {
    echo "user not found\n";
    exit;
}
$posts = get_posts_for_user($user['id'], 1);
if (empty($posts)) {
    echo "no posts\n";
    exit;
}
print_r($posts[0]);
