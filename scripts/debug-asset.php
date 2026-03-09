<?php
require_once __DIR__ . '/../init.php';

// Simulate running under web server in subfolder
$_SERVER['SCRIPT_NAME'] = '/mini-book/profile.php';

echo 'asset for upload: ' . asset('data/uploads/test.png') . "\n";
