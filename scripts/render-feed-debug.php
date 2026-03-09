<?php
// Debug renderer for feed.php showing the produced HTML (useful for checking the image src)
require_once __DIR__ . '/../init.php';

// Simulate server environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/mini-book/feed.php';
$_SERVER['PHP_SELF'] = '/mini-book/feed.php';

// Simulate a logged-in user by setting session user_id (adjust to existing user ID)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION['user_id'] = 2; // adjust to your user id

ob_start();
require __DIR__ . '/../feed.php';
$html = ob_get_clean();

echo $html;
