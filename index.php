<?php
require_once __DIR__ . '/init.php';

if (current_user()) {
    header('Location: feed.php');
    exit;
}

header('Location: login.php');
exit;
