<?php
require_once __DIR__ . '/init.php';
require_login();

$user = current_user();

function respondJson(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function wantsJson(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json') || str_contains($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest');
}

$action = $_POST['action'] ?? 'unknown';

if ($action === 'create_post') {
    $content = sanitize_text($_POST['content'] ?? '');
    $media = handle_media_upload($_FILES['media'] ?? []);

    if ($content === '' && !$media) {
        flash_set('error', 'Post must include text or an image.');
        header('Location: feed.php');
        exit;
    }

    insert_post($user['id'], $content, $media);
    flash_set('success', 'Post published!');
    header('Location: feed.php');
    exit;
}

if ($action === 'toggle_like') {
    $postId = parse_request_int('post_id');
    if (!$postId) {
        if (wantsJson()) {
            respondJson(['status' => 'error', 'message' => 'Invalid post']);
        }
        flash_set('error', 'Invalid post ID.');
        header('Location: feed.php');
        exit;
    }

    $liked = toggle_like($user['id'], $postId);
    $likeCount = get_post_like_count($postId);

    // Notify post owner
    $owner = get_post_owner($postId);
    if ($owner && $owner['id'] !== $user['id'] && $liked) {
        insert_notification($owner['id'], $user['id'], 'like_post', $postId);
    }

    if (wantsJson()) {
        respondJson([
            'status' => 'ok',
            'liked' => $liked,
            'likeCount' => $likeCount,
            'update' => [
                "#post-like-count-{$postId}" => $likeCount,
            ],
        ]);
    }

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'feed.php'));
    exit;
}

if ($action === 'create_comment') {
    $postId = parse_request_int('post_id');
    $content = sanitize_text($_POST['content'] ?? '');

    if (!$postId || $content === '') {
        flash_set('error', 'Missing comment or post.');
        header('Location: feed.php');
        exit;
    }

    insert_comment($user['id'], $postId, $content);

    // Notify post owner
    $owner = get_post_owner($postId);
    if ($owner && $owner['id'] !== $user['id']) {
        insert_notification($owner['id'], $user['id'], 'comment', $postId);
    }

    flash_set('success', 'Comment added.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'feed.php'));
    exit;
}

if ($action === 'delete_post') {
    $postId = parse_request_int('post_id');
    if (!$postId) {
        flash_set('error', 'Invalid post.');
        header('Location: feed.php');
        exit;
    }

    $db = mini_book_db();
    $stmt = $db->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
    $stmt->execute([$postId, $user['id']]);

    flash_set('success', 'Post deleted.');
    header('Location: feed.php');
    exit;
}

if ($action === 'toggle_comment_like') {
    $commentId = parse_request_int('comment_id');
    if (!$commentId) {
        flash_set('error', 'Invalid comment.');
        header('Location: feed.php');
        exit;
    }

    $liked = toggle_comment_like($user['id'], $commentId);

    // Notify comment author
    $db = mini_book_db();
    $stmt = $db->prepare('SELECT user_id, post_id FROM comments WHERE id = ?');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if ($comment && $comment['user_id'] !== $user['id'] && $liked) {
        insert_notification($comment['user_id'], $user['id'], 'like_comment', $comment['post_id'], $commentId);
    }

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'feed.php'));
    exit;
}

if ($action === 'toggle_follow') {
    $target = parse_request_int('user_id');
    if (!$target) {
        flash_set('error', 'Invalid user.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'feed.php'));
        exit;
    }

    $followed = toggle_follow($user['id'], $target);

    if ($followed) {
        insert_notification($target, $user['id'], 'follow');
    } else {
        insert_notification($target, $user['id'], 'unfollow');
    }

    flash_set('success', $followed ? 'You are now following this person.' : 'You unfollowed this person.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'feed.php'));
    exit;
}

// Default fallback
flash_set('error', 'Unknown action.');
header('Location: feed.php');
exit;
