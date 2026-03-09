<?php
if (!defined('MINIBOOK_SITE_NAME')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$user = current_user();
$title = $title ?? MINIBOOK_SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#ff5ea5" />
  <title><?= escape_html($title) ?></title>
  <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>" />
  <script defer src="<?= asset('assets/js/app.js') ?>"></script>
  <?php if ($user): ?>
    <script>window.MINIBOOK_USER_ID = <?= json_encode($user['id']) ?>;</script>
  <?php endif; ?>
</head>
<body class="site-body">
  <header class="main-header">
    <div class="header-left">
      <a class="brand" href="feed.php" aria-label="Mini-Book Home">
        <span class="brand-logo" aria-hidden="true">📘</span>
        <span class="brand-text">Mini-Book</span>
      </a>

      <?php if ($user): ?>
        <nav class="primary-nav" aria-label="Primary">
          <a href="feed.php" class="nav-link <?= menu_active('feed.php') ?>" title="Home">
            <span aria-hidden="true" class="icon">🏠</span>
            <span class="nav-label">Home</span>
          </a>
          <a href="profile.php?u=<?= urlencode($user['username']) ?>" class="nav-link <?= menu_active('profile.php') ?>" title="Profile">
            <span aria-hidden="true" class="icon">👤</span>
            <span class="nav-label">Profile</span>
          </a>
          <a href="feed.php" class="nav-link" title="Explore">
            <span aria-hidden="true" class="icon">🔍</span>
            <span class="nav-label">Explore</span>
          </a>
        </nav>
      <?php endif; ?>
    </div>

    <div class="header-right">
      <?php if ($user): ?>
        <?php
          $notificationCount = get_unread_notification_count($user['id']);
          $notifications = get_notifications($user['id'], 10);
        ?>

        <div class="search-wrapper">
          <form method="get" action="feed.php" class="search-form" role="search">
            <input name="q" type="search" placeholder="Search mini-book" aria-label="Search" class="search-input" />
            <button type="submit" class="search-btn" title="Search">🔎</button>
          </form>
        </div>

        <div class="notifications">
          <button id="notificationsToggle" type="button" class="icon-button" aria-haspopup="true" aria-expanded="false" aria-label="Notifications" onclick="window.toggleNotifications?.()">
            <span class="icon">🔔</span>
            <?php if ($notificationCount > 0): ?>
              <span class="notification-badge" aria-hidden="true"><?= $notificationCount ?></span>
            <?php endif; ?>
          </button>
          <div id="notificationsDropdown" class="notifications-dropdown" role="menu" aria-label="Notifications">
            <?php if (count($notifications) === 0): ?>
              <div class="notifications-empty">You're all caught up!</div>
            <?php else: ?>
              <?php foreach ($notifications as $n): ?>
                <?php
                  $actorName = escape_html($n['actor_name'] ?: $n['actor_username']);
                  $actorUrl = 'profile.php?u=' . urlencode($n['actor_username']);
                  $when = human_time($n['created_at']);
                  $text = '';
                  switch ($n['type']) {
                    case 'follow':
                      $text = "$actorName started following you";
                      break;
                    case 'unfollow':
                      $text = "$actorName unfollowed you";
                      break;
                    case 'like_post':
                      $text = "$actorName liked your post";
                      break;
                    case 'comment':
                      $text = "$actorName commented on your post";
                      break;
                    case 'like_comment':
                      $text = "$actorName liked your comment";
                      break;
                    default:
                      $text = "$actorName sent a notification";
                      break;
                  }
                ?>
                <a class="notification-item" href="<?= $actorUrl ?>">
                  <span class="notification-text"><?= escape_html($text) ?></span>
                  <span class="notification-time"><?= escape_html($when) ?></span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      
      <?php endif; ?>

      <div class="toggle-wrapper" title="Toggle dark mode">
        <button id="themeToggle" class="theme-toggle" aria-label="Toggle dark mode">
          <span class="icon">🌙</span>
          <span class="toggle-label">Dark</span>
        </button>
      </div>

      <?php if ($user): ?>
        <div class="account-menu">
          <span class="account-name">Hi, <?= escape_html($user['full_name'] ?? $user['username']) ?></span>
          <a class="btn secondary" href="logout.php">Log out</a>
        </div>
      <?php else: ?>
        <div class="auth-links">
          <a class="btn" href="login.php">Log in</a>
          <a class="btn secondary" href="register.php">Sign up</a>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main class="page-wrapper">
    <?php if ($flash = flash_get('success')): ?>
      <div class="toast toast-success" role="status"><?= escape_html($flash) ?></div>
    <?php endif; ?>
    <?php if ($flash = flash_get('error')): ?>
      <div class="toast toast-error" role="alert"><?= escape_html($flash) ?></div>
    <?php endif; ?>
