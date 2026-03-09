<?php
// init.php - shared bootstrap for Mini-Book
session_start();

// Enable strict typing and error reporting for development
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('MINIBOOK_DATA_DIR', __DIR__ . '/data');
define('MINIBOOK_UPLOAD_DIR', MINIBOOK_DATA_DIR . '/uploads');
define('MINIBOOK_DB_PATH', MINIBOOK_DATA_DIR . '/mini-book.db');

define('MINIBOOK_SITE_NAME', 'Mini-Book');

// Ensure data directory exists
if (!is_dir(MINIBOOK_DATA_DIR)) {
    mkdir(MINIBOOK_DATA_DIR, 0755, true);
}

// Ensure upload directory exists (for profile photos)
if (!is_dir(MINIBOOK_UPLOAD_DIR)) {
    mkdir(MINIBOOK_UPLOAD_DIR, 0755, true);
}

// Initialize database connection (SQLite)
function mini_book_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'sqlite:' . MINIBOOK_DB_PATH;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Ensure we have required tables
    $pdo->exec('PRAGMA foreign_keys = ON');
    create_tables($pdo);

    // Ensure schema is current (columns can be added later for existing DBs)
    $stmt = $pdo->query("PRAGMA table_info('users')");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('profile_pic', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile_pic TEXT DEFAULT NULL');
    }

    $stmt = $pdo->query("PRAGMA table_info('posts')");
    $postColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('media', $postColumns, true)) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN media TEXT DEFAULT NULL');
    }

    // Ensure notifications table exists for older DBs (only needed if schema missing)
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
    if ($stmt->fetch() === false) {
        $pdo->exec(<<<'SQL'
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    actor_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    post_id INTEGER NULL,
    comment_id INTEGER NULL,
    seen INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE
);
SQL
        );
    }

    // Ensure notifications table has required columns (for older DB versions)
    $stmt = $pdo->query("PRAGMA table_info('notifications')");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('seen', $columns, true)) {
        $pdo->exec('ALTER TABLE notifications ADD COLUMN seen INTEGER NOT NULL DEFAULT 0');
    }

    // Ensure messages table exists for older DBs
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='messages'");
    if ($stmt->fetch() === false) {
        $pdo->exec(<<<'SQL'
CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    seen INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
        );
    }

    // Ensure comment_likes table exists for older DBs
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='comment_likes'");
    if ($stmt->fetch() === false) {
        $pdo->exec(<<<'SQL'
CREATE TABLE comment_likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    comment_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(user_id, comment_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE
);
SQL
        );
    }

    return $pdo;
}

function create_tables(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    bio TEXT DEFAULT '',
    profile_pic TEXT DEFAULT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    media TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(user_id, post_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comment_likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    comment_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(user_id, comment_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS follows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    follower_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(user_id, follower_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(follower_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    actor_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    post_id INTEGER NULL,
    comment_id INTEGER NULL,
    seen INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    seen INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL
    );
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT id, username, full_name, bio, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function human_time(string $datetime): string
{
    $then = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . 'm ago';
    }
    if ($diff < 86400) {
        $h = (int)floor($diff / 3600);
        return $h . 'h ago';
    }
    return $then->format('M j, Y');
}

function asset(string $path): string
{
    // Determine base URL for the app (works in subfolders).
    // Use SCRIPT_NAME (not PHP_SELF) to avoid rewritten paths.
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

    // Normalize base to always start with a leading slash (so paths are never relative).
    if ($base !== '' && $base[0] !== '/') {
        $base = '/' . $base;
    }

    // If we do not have a base, make path root-relative to avoid relative paths.
    if ($base === '' || $base === '/') {
        return '/' . ltrim($path, '/');
    }

    return $base . '/' . ltrim($path, '/');
}

function menu_active(string $page): string
{
    return (basename($_SERVER['PHP_SELF']) === $page) ? 'active' : '';
}

function count_followers(int $userId): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM follows WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function count_following(int $userId): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function is_following(int $viewerId, int $profileId): bool
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND user_id = ?');
    $stmt->execute([$viewerId, $profileId]);
    return (bool)$stmt->fetchColumn();
}

function is_post_liked(int $userId, int $postId): bool
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?');
    $stmt->execute([$userId, $postId]);
    return (bool)$stmt->fetchColumn();
}

function parse_request_int(string $field): ?int
{
    if (!isset($_REQUEST[$field])) {
        return null;
    }
    return filter_var($_REQUEST[$field], FILTER_VALIDATE_INT) ?: null;
}

function sanitize_text(string $value): string
{
    // Keep line breaks but trim excessive whitespace
    $value = trim($value);
    $value = preg_replace('/[\r\n]{3,}/', "\n\n", $value);
    return $value;
}

function get_user_by_username(string $username): ?array
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT id, username, full_name, bio, profile_pic, created_at FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch() ?: null;
}

function get_user_by_id(int $userId): ?array
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT id, username, full_name, bio, profile_pic, created_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function get_post_owner(int $postId): ?array
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT u.id, u.username, u.full_name FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?');
    $stmt->execute([$postId]);
    return $stmt->fetch() ?: null;
}

function get_post_like_count(int $postId): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
    $stmt->execute([$postId]);
    return (int)$stmt->fetchColumn();
}

function get_post_comment_count(int $postId): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
    $stmt->execute([$postId]);
    return (int)$stmt->fetchColumn();
}

function get_recent_posts(int $limit = 20): array
{
    $db = mini_book_db();
    $stmt = $db->prepare(
        'SELECT p.*, u.username, u.full_name, u.profile_pic FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_posts_for_user(int $userId, int $limit = 50): array
{
    $db = mini_book_db();
    $stmt = $db->prepare(
        'SELECT p.*, u.username, u.full_name, u.profile_pic FROM posts p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_comments_for_post(int $postId, int $limit = 20): array
{
    $db = mini_book_db();
    $stmt = $db->prepare(
        'SELECT c.*, u.username, u.full_name, u.profile_pic FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC LIMIT ?'
    );
    $stmt->bindValue(1, $postId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function require_post_request(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('HTTP/1.1 405 Method Not Allowed');
        exit('Method not allowed');
    }
}

function safe_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function insert_post(int $userId, string $content, ?string $media = null): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('INSERT INTO posts (user_id, content, media, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $content, $media, date('c')]);
    return (int)$db->lastInsertId();
}

function insert_comment(int $userId, int $postId, string $content): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('INSERT INTO comments (user_id, post_id, content, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $postId, $content, date('c')]);
    return (int)$db->lastInsertId();
}

function toggle_like(int $userId, int $postId): bool
{
    $db = mini_book_db();
    if (is_post_liked($userId, $postId)) {
        $stmt = $db->prepare('DELETE FROM likes WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$userId, $postId]);
        return false;
    }

    $stmt = $db->prepare('INSERT OR IGNORE INTO likes (user_id, post_id, created_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $postId, date('c')]);
    return true;
}

function toggle_follow(int $viewerId, int $profileId): bool
{
    $db = mini_book_db();
    if (is_following($viewerId, $profileId)) {
        $stmt = $db->prepare('DELETE FROM follows WHERE follower_id = ? AND user_id = ?');
        $stmt->execute([$viewerId, $profileId]);
        return false;
    }

    $stmt = $db->prepare('INSERT OR IGNORE INTO follows (user_id, follower_id, created_at) VALUES (?, ?, ?)');
    $stmt->execute([$profileId, $viewerId, date('c')]);
    return true;
}

function html_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_profile_pic_url(?string $filename): string
{
    if (!$filename) {
        return asset('assets/img/avatar-placeholder.svg');
    }

    // prevent directory traversal
    $safe = basename($filename);
    return asset('data/uploads/' . $safe);
}

function delete_profile_pic(string $filename): void
{
    $path = MINIBOOK_UPLOAD_DIR . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function handle_profile_pic_upload(array $file): ?string
{
    return handle_media_upload($file);
}

function handle_media_upload(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return null;
    }

    $ext = $allowed[$mime];
    $safeName = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = MINIBOOK_UPLOAD_DIR . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }

    return $safeName;
}

function insert_notification(int $userId, int $actorId, string $type, ?int $postId = null, ?int $commentId = null): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('INSERT INTO notifications (user_id, actor_id, type, post_id, comment_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $actorId, $type, $postId, $commentId, date('c')]);
    return (int)$db->lastInsertId();
}

function get_unread_notification_count(int $userId): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND seen = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function get_notifications(int $userId, int $limit = 20): array
{
    $db = mini_book_db();
    $stmt = $db->prepare(
        'SELECT n.*, u.username AS actor_username, u.full_name AS actor_name, u.profile_pic AS actor_pic
         FROM notifications n
         JOIN users u ON n.actor_id = u.id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notifications_as_read(int $userId): void
{
    $db = mini_book_db();
    $stmt = $db->prepare('UPDATE notifications SET seen = 1 WHERE user_id = ? AND seen = 0');
    $stmt->execute([$userId]);
}

function insert_message(int $senderId, int $receiverId, string $content): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$senderId, $receiverId, $content, date('c')]);
    return (int)$db->lastInsertId();
}

function get_conversations(int $userId): array
{
    $db = mini_book_db();
    $stmt = $db->prepare(
        'SELECT
             u.id AS user_id,
             u.username,
             u.full_name,
             u.profile_pic,
             MAX(m.created_at) AS last_message_at,
             SUM(CASE WHEN m.receiver_id = ? AND m.seen = 0 THEN 1 ELSE 0 END) AS unread_count
         FROM messages m
         JOIN users u ON (u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
         WHERE m.sender_id = ? OR m.receiver_id = ?
         GROUP BY u.id, u.username, u.full_name, u.profile_pic
         ORDER BY last_message_at DESC'
    );
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

function get_messages_between(int $userId, int $otherUserId, int $limit = 50): array
{
    $db = mini_book_db();
    $stmt = $db->prepare(
        'SELECT m.*, u.username AS sender_username, u.full_name AS sender_name, u.profile_pic AS sender_pic
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $otherUserId, PDO::PARAM_INT);
    $stmt->bindValue(3, $otherUserId, PDO::PARAM_INT);
    $stmt->bindValue(4, $userId, PDO::PARAM_INT);
    $stmt->bindValue(5, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return array_reverse($stmt->fetchAll());
}

function mark_messages_as_read(int $userId, int $otherUserId): void
{
    $db = mini_book_db();
    $stmt = $db->prepare('UPDATE messages SET seen = 1 WHERE receiver_id = ? AND sender_id = ? AND seen = 0');
    $stmt->execute([$userId, $otherUserId]);
}

function delete_message(int $messageId, int $userId): void
{
    $db = mini_book_db();
    $stmt = $db->prepare('DELETE FROM messages WHERE id = ? AND sender_id = ?');
    $stmt->execute([$messageId, $userId]);
}

function is_comment_liked(int $userId, int $commentId): bool
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT 1 FROM comment_likes WHERE user_id = ? AND comment_id = ?');
    $stmt->execute([$userId, $commentId]);
    return (bool)$stmt->fetchColumn();
}

function toggle_comment_like(int $userId, int $commentId): bool
{
    $db = mini_book_db();
    if (is_comment_liked($userId, $commentId)) {
        $stmt = $db->prepare('DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?');
        $stmt->execute([$userId, $commentId]);
        return false;
    }

    $stmt = $db->prepare('INSERT OR IGNORE INTO comment_likes (user_id, comment_id, created_at) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $commentId, date('c')]);
    return true;
}

function get_comment_like_count(int $commentId): int
{
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?');
    $stmt->execute([$commentId]);
    return (int)$stmt->fetchColumn();
}

