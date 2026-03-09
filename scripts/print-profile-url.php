<?php
require_once __DIR__ . '/../init.php';
$u = $argv[1] ?? null;
if (!$u) {
    echo "Usage: php print-profile-url.php <username>\n";
    exit(1);
}
$profile = get_user_by_username($u);
if (!$profile) {
    echo "User not found\n";
    exit(1);
}
echo "profile_pic: " . ($profile['profile_pic'] ?? '(none)') . "\n";
echo "url: " . get_profile_pic_url($profile['profile_pic'] ?? null) . "\n";
