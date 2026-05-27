<?php
require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    header('Location: /rg-trading-php/index.php');
    exit;
}

$error  = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $phone      = trim($_POST['phone']      ?? '');

    // Basic client-side validation
    if (empty($first_name)) $errors['first_name'] = 'First name is required.';
    if (empty($last_name))  $errors['last_name']  = 'Last name is required.';
    if (empty($email))      $errors['email']      = 'Email is required.';
    if (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        $payload = ['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'password' => $password];
        if (!empty($phone)) $payload['phone'] = $phone;

        $result = api_request('POST', '/auth/register', $payload);

        if ($result['status'] === 201 && isset($result['body']['data']['access_token'])) {
            $_SESSION['access_token']  = $result['body']['data']['access_token'];
            $_SESSION['refresh_token'] = $result['body']['data']['refresh_token'];
            $_SESSION['user']          = $result['body']['data']['user'];

            set_flash('success', 'Account created! Welcome to R&G Trading.');
            header('Location: /rg-trading-php/index.php');
            exit;
        } else {
            // Show API validation errors
            if (isset($result['body']['errors'])) {
                foreach ($result['body']['errors'] as $e) {
                    $errors[$e['field']] = $e['message'];
                }
            } else {
                $error = $result['body']['message'] ?? 'Registration failed. Please try again.';
            }
        }
    }
}

$page_title = 'Register — ' . APP_NAME;
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
    <h2>Create an account</h2>
    <p>Join R&amp;G Trading to browse and order aircon units</p>

    <?php if ($error): ?>
      <div class="flash flash-error" style="border-radius:8px; margin-bottom:16px;"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" placeholder="Juan" value="<?= h($_POST['first_name'] ?? '') ?>" required>
          <?php if (isset($errors['first_name'])): ?><p class="form-error"><?= h($errors['first_name']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" placeholder="dela Cruz" value="<?= h($_POST['last_name'] ?? '') ?>" required>
          <?php if (isset($errors['last_name'])): ?><p class="form-error"><?= h($errors['last_name']) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="juan@example.com" value="<?= h($_POST['email'] ?? '') ?>" required>
        <?php if (isset($errors['email'])): ?><p class="form-error"><?= h($errors['email']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Password <span style="color:#a0aec0;font-weight:400">(min 8 characters)</span></label>
        <input type="password" name="password" placeholder="••••••••" required>
        <?php if (isset($errors['password'])): ?><p class="form-error"><?= h($errors['password']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Phone <span style="color:#a0aec0;font-weight:400">(optional)</span></label>
        <input type="text" name="phone" placeholder="09XXXXXXXXX" value="<?= h($_POST['phone'] ?? '') ?>">
      </div>
      <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="/rg-trading-php/login.php">Sign in</a>
    </div>
  </div>
</div>
<script src="/rg-trading-php/assets/js/main.js"></script>
</body>
</html>
