<?php
require_once __DIR__ . '/includes/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: /rg-trading-php/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $result = api_request('POST', '/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        if ($result['status'] === 200 && isset($result['body']['data']['access_token'])) {
            $_SESSION['access_token']  = $result['body']['data']['access_token'];
            $_SESSION['refresh_token'] = $result['body']['data']['refresh_token'];
            $_SESSION['user']          = $result['body']['data']['user'];

            set_flash('success', 'Welcome back, ' . $_SESSION['user']['first_name'] . '!');

            // Redirect admins to dashboard
            if (is_admin()) {
                header('Location: /rg-trading-php/pages/admin/dashboard.php');
            } else {
                header('Location: /rg-trading-php/index.php');
            }
            exit;
        } else {
            $error = $result['body']['message'] ?? 'Invalid email or password.';
        }
    }
}

$page_title = 'Login — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title) ?></title>
  <link rel="stylesheet" href="/rg-trading-php/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <h2>Welcome back</h2>
    <p>Sign in to your R&amp;G Trading account</p>

    <?php if ($error): ?>
      <div class="flash flash-error" style="border-radius:8px; margin-bottom:16px;">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@example.com"
               value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="/rg-trading-php/register.php">Register here</a>
    </div>
  </div>
</div>
<script src="/rg-trading-php/assets/js/main.js"></script>
</body>
</html>
