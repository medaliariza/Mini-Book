<?php
// quick debug helper: print users + profile_pic
$db = new PDO('sqlite:' . __DIR__ . '/../data/mini-book.db');
foreach ($db->query('SELECT username, profile_pic FROM users') as $row) {
    echo $row['username'] . ': ' . ($row['profile_pic'] ?: '(none)') . "\n";
}
