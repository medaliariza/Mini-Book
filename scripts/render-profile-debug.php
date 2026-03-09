<?php
// Debug renderer for profile.php showing the produced HTML (useful for checking the image src)
require_once __DIR__ . '/../init.php';

// Simulate server environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/mini-book/profile.php';
$_SERVER['PHP_SELF'] = '/mini-book/profile.php';

// Simulate a logged-in user by setting session user_id (adjust to existing user ID)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION['user_id'] = 2; // adjust to your user id

// Request for a specific profile
$_GET = ['u' => 'jenjen-user'];

ob_start();
require __DIR__ . '/../profile.php';
$html = ob_get_clean();

echo $html;
