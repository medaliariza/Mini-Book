<?php
require_once __DIR__ . '/init.php';

if (current_user()) {
    header('Location: feed.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $fullName === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_\.\-]{3,32}$/', $username)) {
        $error = 'Username should be 3-32 characters and can include letters, numbers, dots, underscores, and dashes.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = mini_book_db();
        $stmt = $db->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn()) {
            $error = 'That username is already taken. Try another one.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password_hash, full_name, created_at) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $hash, $fullName, date('c')]);

            $_SESSION['user_id'] = (int)$db->lastInsertId();
            flash_set('success', 'Welcome! Your Mini-Book profile was created.');
            header('Location: feed.php');
            exit;
        }
    }
}

$title = 'Sign up — Mini-Book';
require __DIR__ . '/header.php';
?>

<div class="page-wrapper">
  <div class="card" style="max-width: 520px; margin: 0 auto;">
    <h1 style="margin-top: 0;">Sign up</h1>
    <p style="color: var(--text-muted);">Create your free Mini-Book profile.</p>

    <?php if ($error): ?>
      <div class="toast toast-error" role="alert"><?= escape_html($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="input-group">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" required maxlength="60" autofocus />
      </div>

      <div class="input-group">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required maxlength="32" />
      </div>

      <div class="input-group">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" />
      </div>

      <div class="input-group">
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password" />
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">Create account</button>
    </form>

    <p style="margin-top: 16px; color: var(--text-muted);">Already have an account? <a href="login.php">Log in</a>.</p>
  </div>
</div>

<?php require __DIR__ . '/footer.php';
