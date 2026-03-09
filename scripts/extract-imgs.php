<?php
require_once __DIR__ . '/../init.php';

// Simulate environment for profile page rendering
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/mini-book/profile.php';
$_SERVER['PHP_SELF'] = '/mini-book/profile.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION['user_id'] = 2; // adjust to your user id

$_GET = ['u' => 'jenjen-user'];

ob_start();
require __DIR__ . '/../profile.php';
$html = ob_get_clean();

preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);
foreach ($matches[1] as $src) {
    echo $src . "\n";
}
