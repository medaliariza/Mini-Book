<?php
require_once __DIR__ . '/init.php';

if (current_user()) {
    header('Location: feed.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter both username and password.';
    } else {
        $db = mini_book_db();
        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            flash_set('success', 'Welcome back!');
            header('Location: feed.php');
            exit;
        }
        $error = 'Invalid username or password.';
    }
}

$title = 'Log in — Mini-Book';
require __DIR__ . '/header.php';
?>

<div class="page-wrapper">
  <div class="card" style="max-width: 480px; margin: 0 auto;">
    <h1 style="margin-top: 0;">Log in</h1>
    <p style="color: var(--text-muted);">Sign in to continue to Mini-Book.</p>

    <?php if ($error): ?>
      <div class="toast toast-error" role="alert"><?= escape_html($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="input-group">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required maxlength="32" autofocus />
      </div>

      <div class="input-group">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" />
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">Continue</button>
    </form>

    <p style="margin-top: 16px; color: var(--text-muted);">New to Mini-Book? <a href="register.php">Create an account</a>.</p>
  </div>
</div>

<?php require __DIR__ . '/footer.php';
