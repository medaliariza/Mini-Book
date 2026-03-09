<?php
require_once __DIR__ . '/../init.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/mini-book/profile.php';
$_SERVER['PHP_SELF'] = '/mini-book/profile.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_SESSION['user_id'] = 2;

$_GET = ['u' => 'jenjen-user'];

ob_start();
require __DIR__ . '/../profile.php';
$html = ob_get_clean();

$lines = explode("\n", $html);
foreach ($lines as $line) {
    if (strpos($line, 'post-media') !== false) {
        echo $line . "\n";
    }
}
