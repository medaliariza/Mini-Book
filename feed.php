<?php
require_once __DIR__ . '/init.php';
require_login();

$user = current_user();

$q = trim($_GET['q'] ?? '');

$posts = [];
if ($q !== '') {
    $db = mini_book_db();
    $term = '%' . str_replace(' ', '%', $q) . '%';
    $stmt = $db->prepare(
        'SELECT p.*, u.username, u.full_name, u.profile_pic FROM posts p JOIN users u ON p.user_id = u.id WHERE p.content LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? ORDER BY p.created_at DESC LIMIT 50'
    );
    $stmt->execute([$term, $term, $term]);
    $posts = $stmt->fetchAll();
} else {
    $posts = get_recent_posts(40);
}

$title = 'Home — Mini-Book';
require __DIR__ . '/header.php';
?>

<div class="page-wrapper">
  <div class="card">
    <h2>Create a post</h2>
    <form method="post" action="post.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create_post" />
      <div class="input-group">
        <label for="post_content">What's on your mind?</label>
        <textarea id="post_content" name="content" placeholder="Share something with your friends..."></textarea>
      </div>
      <div class="input-group">
        <label for="post_media">Add a photo (optional)</label>
        <input id="post_media" name="media" type="file" accept="image/*" />
      </div>
      <button type="submit" class="btn btn-primary">Post</button>
    </form>
  </div>

  <?php if ($q !== ''): ?>
    <p style="margin-top: 14px; color: var(--text-muted);">Showing search results for <strong><?= escape_html($q) ?></strong>.</p>
  <?php endif; ?>

  <?php if (count($posts) === 0): ?>
    <div class="card" style="text-align: center;">
      <p style="margin: 0;">No posts yet. Share something to start the conversation!</p>
    </div>
  <?php endif; ?>

  <?php foreach ($posts as $post): ?>
    <?php
      $liked = is_post_liked($user['id'], $post['id']);
      $likeCount = get_post_like_count($post['id']);
      $commentCount = get_post_comment_count($post['id']);
      $comments = get_comments_for_post($post['id'], 5);
      $authorProfile = get_user_by_id($post['user_id']);
    ?>
    <article class="post">
      <div class="post-header">
        <a class="post-avatar" href="profile.php?u=<?= urlencode($post['username']) ?>" aria-label="View profile">
          <img src="<?= escape_html(get_profile_pic_url($post['profile_pic'] ?? null)) ?>" alt="<?= escape_html($post['full_name']) ?>" />
        </a>
        <div class="post-meta">
          <a href="profile.php?u=<?= urlencode($post['username']) ?>"><strong><?= escape_html($post['full_name']) ?></strong></a>
          <span>@<?= escape_html($post['username']) ?> · <?= human_time($post['created_at']) ?></span>
        </div>
        <?php if ($post['user_id'] === $user['id']): ?>
          <form method="post" action="post.php" style="margin:0;">
            <input type="hidden" name="action" value="delete_post" />
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>" />
            <button type="submit" class="btn secondary" style="padding: 8px 12px;">Delete</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($post['content'] !== ''): ?>
        <div class="post-content"><?= nl2br(escape_html($post['content'])) ?></div>
      <?php endif; ?>

      <?php if (!empty($post['media'])): ?>
        <div class="post-media">
          <img src="<?= escape_html(get_profile_pic_url($post['media'])) ?>" alt="Attached photo" />
        </div>
      <?php endif; ?>

      <div class="post-actions">
        <form method="post" action="post.php" class="post-action" style="display:inline-flex;" >
          <input type="hidden" name="action" value="toggle_like" />
          <input type="hidden" name="post_id" value="<?= $post['id'] ?>" />
          <button type="submit" class="post-action <?= $liked ? 'active' : '' ?>" style="border:none; background:transparent; padding:0;" aria-label="Like">
            <span class="icon"><?= $liked ? '💖' : '🤍' ?></span>
            <span><?= $likeCount ?> Like<?= $likeCount === 1 ? '' : 's' ?></span>
          </button>
        </form>

        <button class="post-action" type="button" onclick="document.getElementById('comment-form-<?= $post['id'] ?>').scrollIntoView({ behavior: 'smooth' });">
          <span class="icon">💬</span>
          <span><?= $commentCount ?> Comment<?= $commentCount === 1 ? '' : 's' ?></span>
        </button>
      </div>

      <?php if (count($comments) > 0): ?>
        <div class="comment-list">
          <?php foreach ($comments as $comment): ?>
            <?php
              $commentLiked = is_comment_liked($user['id'], $comment['id']);
              $commentLikeCount = get_comment_like_count($comment['id']);
            ?>
            <div class="comment-item">
              <a class="comment-avatar" href="profile.php?u=<?= urlencode($comment['username']) ?>" aria-label="View profile">
                <img src="<?= escape_html(get_profile_pic_url($comment['profile_pic'] ?? null)) ?>" alt="<?= escape_html($comment['full_name']) ?>" />
              </a>
              <div class="comment-body">
                <div class="comment-meta">
                  <strong><?= escape_html($comment['full_name']) ?></strong>
                  <span>@<?= escape_html($comment['username']) ?></span>
                  <span>· <?= human_time($comment['created_at']) ?></span>
                </div>
                <p style="margin: 6px 0 0;"><?= nl2br(escape_html($comment['content'])) ?></p>
                <div style="margin-top: 8px; font-size: 0.85rem;">
                  <form method="post" action="post.php" style="display:inline-flex; gap: 8px; align-items:center;">
                    <input type="hidden" name="action" value="toggle_comment_like" />
                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>" />
                    <button type="submit" class="post-action <?= $commentLiked ? 'active' : '' ?>" style="border:none; background:transparent; padding:0;" aria-label="Like comment">
                      <span class="icon"><?= $commentLiked ? '💖' : '🤍' ?></span>
                      <span><?= $commentLikeCount ?> Like<?= $commentLikeCount === 1 ? '' : 's' ?></span>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form id="comment-form-<?= $post['id'] ?>" method="post" action="post.php">
        <input type="hidden" name="action" value="create_comment" />
        <input type="hidden" name="post_id" value="<?= $post['id'] ?>" />
        <div class="input-group" style="margin-top: 14px;">
          <label for="comment_<?= $post['id'] ?>">Add a comment</label>
          <textarea id="comment_<?= $post['id'] ?>" name="content" required placeholder="Write a reply..."></textarea>
        </div>
        <button type="submit" class="btn" style="padding: 10px 14px;">Comment</button>
      </form>
    </article>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/footer.php';
