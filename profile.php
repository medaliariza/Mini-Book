<?php
require_once __DIR__ . '/init.php';
require_login();

$user = current_user();

$username = trim($_GET['u'] ?? '');
if ($username === '') {
    header('Location: feed.php');
    exit;
}

$profile = get_user_by_username($username);
if (!$profile) {
    $title = 'Profile not found — Mini-Book';
    require __DIR__ . '/header.php';
    ?>
    <div class="page-wrapper">
      <div class="card" style="text-align:center;">
        <h1>Profile not found</h1>
        <p style="color: var(--text-muted);">We couldn't find a profile for <strong><?= escape_html($username) ?></strong>.</p>
        <p><a class="btn" href="feed.php">Return to feed</a></p>
      </div>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

$posts = get_posts_for_user($profile['id'], 50);
$isMe = $profile['id'] === $user['id'];
$isFollowing = $isMe ? false : is_following($user['id'], $profile['id']);

if ($isMe && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile_pic') {
        $newPic = handle_profile_pic_upload($_FILES['profile_pic'] ?? []);
        if ($newPic) {
            if (!empty($profile['profile_pic'])) {
                delete_profile_pic($profile['profile_pic']);
            }
            $db = mini_book_db();
            $stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
            $stmt->execute([$newPic, $user['id']]);
            flash_set('success', 'Profile picture updated.');
        } else {
            flash_set('error', 'Unable to upload that image. Please try a JPG, PNG, WEBP, or GIF.');
        }
        safe_redirect('profile.php?u=' . urlencode($profile['username']));
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            flash_set('error', 'All password fields are required.');
            safe_redirect('profile.php?u=' . urlencode($profile['username']));
        }

        $db = mini_book_db();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $data = $stmt->fetch();

        if (!$data || !password_verify($current, $data['password_hash'])) {
            flash_set('error', 'Current password is incorrect.');
            safe_redirect('profile.php?u=' . urlencode($profile['username']));
        }

        if ($new !== $confirm) {
            flash_set('error', 'New passwords do not match.');
            safe_redirect('profile.php?u=' . urlencode($profile['username']));
        }

        if (strlen($new) < 6) {
            flash_set('error', 'New password must be at least 6 characters.');
            safe_redirect('profile.php?u=' . urlencode($profile['username']));
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);
        flash_set('success', 'Password updated successfully.');
        safe_redirect('profile.php?u=' . urlencode($profile['username']));
    }

    if ($action === 'update_bio') {
        $bio = sanitize_text($_POST['bio'] ?? '');
        $db = mini_book_db();
        $stmt = $db->prepare('UPDATE users SET bio = ? WHERE id = ?');
        $stmt->execute([$bio, $user['id']]);
        flash_set('success', 'Bio updated.');
        safe_redirect('profile.php?u=' . urlencode($profile['username']));
    }

    if ($action === 'delete_account') {
        $password = $_POST['password'] ?? '';
        if ($password === '') {
            flash_set('error', 'Password is required to delete your account.');
            safe_redirect('profile.php?u=' . urlencode($profile['username']));
        }

        $db = mini_book_db();
        $stmt = $db->prepare('SELECT password_hash, profile_pic FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $data = $stmt->fetch();

        if (!$data || !password_verify($password, $data['password_hash'])) {
            flash_set('error', 'Password is incorrect.');
            safe_redirect('profile.php?u=' . urlencode($profile['username']));
        }

        if (!empty($data['profile_pic'])) {
            delete_profile_pic($data['profile_pic']);
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);

        $flash = 'Your account has been deleted.';
        $_SESSION = [];
        session_regenerate_id(true);
        flash_set('success', $flash);

        header('Location: login.php');
        exit;
    }
}

$title = escape_html($profile['full_name']) . ' — Mini-Book';
require __DIR__ . '/header.php';
?>

<div class="page-wrapper">
  <div class="card">
    <div class="profile-header">
      <div class="profile-avatar" aria-hidden="true">
        <img src="<?= escape_html(get_profile_pic_url($profile['profile_pic'] ?? null)) ?>" alt="Profile picture" />
      </div>

      <div class="profile-meta">
        <h1><?= escape_html($profile['full_name']) ?></h1>
        <p style="margin: 6px 0 0; color: var(--text-muted);">@<?= escape_html($profile['username']) ?></p>
        <?php if ($profile['bio']): ?>
          <p style="margin: 10px 0 0; line-height: 1.5;"><?= nl2br(escape_html($profile['bio'])) ?></p>
        <?php else: ?>
          <p style="margin: 10px 0 0; color: var(--text-muted);">No bio yet. Add one from your feed.</p>
        <?php endif; ?>

        <div class="profile-stats">
          <div class="profile-stat">
            <strong><?= count($posts) ?></strong>
            Posts
          </div>
          <div class="profile-stat">
            <strong><?= count_followers($profile['id']) ?></strong>
            Followers
          </div>
          <div class="profile-stat">
            <strong><?= count_following($profile['id']) ?></strong>
            Following
          </div>
        </div>
      </div>

        <?php if ($isMe): ?>
          <div class="profile-settings">
            <button id="profileSettingsToggle" type="button" class="profile-settings-toggle" aria-haspopup="true" aria-expanded="false">
              <span aria-hidden="true">☰</span>
              <span>Settings</span>
            </button>
            <div id="profileSettingsDropdown" class="profile-settings-dropdown" role="menu">
              <button type="button" data-profile-action="photo">Change profile picture</button>
              <button type="button" data-profile-action="password">Change password</button>
              <button type="button" data-profile-action="bio">Edit bio</button>
              <button type="button" data-profile-action="delete" class="danger">Delete account</button>
            </div>
          </div>
        <?php else: ?>
          <div>
            <form method="post" action="post.php">
              <input type="hidden" name="action" value="toggle_follow" />
              <input type="hidden" name="user_id" value="<?= $profile['id'] ?>" />
              <button type="submit" class="btn <?= $isFollowing ? 'secondary' : '' ?>">
                <?= $isFollowing ? 'Following' : 'Follow' ?>
              </button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-settings-panel" data-panel="photo">
      <div class="profile-picture-preview">
        <img src="<?= escape_html(get_profile_pic_url($profile['profile_pic'])) ?>" alt="Current profile picture" />
      </div>
      <form method="post" action="profile.php?u=<?= urlencode($profile['username']) ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile_pic" />
        <div class="input-group">
          <label for="profile_pic">Upload a new profile picture</label>
          <input id="profile_pic" name="profile_pic" type="file" accept="image/*" required />
        </div>
        <button type="submit" class="btn btn-primary">Save photo</button>
      </form>
    </div>

    <div class="profile-settings-panel" data-panel="password">
      <form method="post" action="profile.php?u=<?= urlencode($profile['username']) ?>">
        <input type="hidden" name="action" value="change_password" />
        <div class="input-group">
          <label for="current_password">Current password</label>
          <input id="current_password" name="current_password" type="password" required autocomplete="current-password" />
        </div>
        <div class="input-group">
          <label for="new_password">New password</label>
          <input id="new_password" name="new_password" type="password" required autocomplete="new-password" />
        </div>
        <div class="input-group">
          <label for="confirm_password">Confirm new password</label>
          <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password" />
        </div>
        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </div>

    <div class="profile-settings-panel" data-panel="bio">
      <form method="post" action="profile.php?u=<?= urlencode($profile['username']) ?>">
        <input type="hidden" name="action" value="update_bio" />
        <div class="input-group">
          <label for="bio">Bio</label>
          <textarea id="bio" name="bio" rows="4" maxlength="500"><?= escape_html($profile['bio'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save bio</button>
      </form>
    </div>

    <div class="profile-settings-panel" data-panel="delete">
      <form method="post" action="profile.php?u=<?= urlencode($profile['username']) ?>">
        <input type="hidden" name="action" value="delete_account" />
        <p style="color: var(--text-muted); margin-top: 0;">Deleting your account is permanent and cannot be undone.</p>
        <div class="input-group">
          <label for="delete_password">Confirm password</label>
          <input id="delete_password" name="password" type="password" required autocomplete="current-password" />
        </div>
        <button type="submit" class="btn" style="background: #c33;">Delete account</button>
      </form>
    </div>
  </div>

  <?php if (count($posts) === 0): ?>
    <div class="card" style="text-align: center;">
      <p style="margin: 0;">No posts yet. Come back later!</p>
    </div>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
      <?php
        $liked = is_post_liked($user['id'], $post['id']);
        $likeCount = get_post_like_count($post['id']);
        $commentCount = get_post_comment_count($post['id']);
        $comments = get_comments_for_post($post['id'], 4);
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

        <div class="post-content"><?= nl2br(escape_html($post['content'])) ?></div>

        <?php if (!empty($post['media'])): ?>
          <div class="post-media">
            <img src="<?= escape_html(get_profile_pic_url($post['media'])) ?>" alt="Attached photo" />
          </div>
        <?php endif; ?>

        <div class="post-actions">
          <form method="post" action="post.php" class="post-action" style="display:inline-flex;">
            <input type="hidden" name="action" value="toggle_like" />
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>" />
            <button type="submit" class="post-action <?= $liked ? 'active' : '' ?>" style="border:none; background:transparent; padding:0;">
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
  <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php';
