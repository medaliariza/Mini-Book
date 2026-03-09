<?php
require_once __DIR__ . '/init.php';

session_destroy();

header('Location: login.php');
exit;
