<?php
require_once __DIR__ . '/../init.php';

// Simulate a web request running under /mini-book/profile.php
$_SERVER['SCRIPT_NAME'] = '/mini-book/profile.php';

echo get_profile_pic_url('93b6fff5eadc351a915782a5.png') . "\n";
