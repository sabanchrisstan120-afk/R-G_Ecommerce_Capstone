<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="/rg-trading-php/assets/css/style.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-container">
    <a href="/rg-trading-php/landing.php" class="nav-brand">
      <span class="brand-rg">R&amp;G</span> Trading ❄️
    </a>
    <div class="nav-links">
      <a href="/rg-trading-php/index.php">Products</a>
      <?php if (is_logged_in()): ?>
        <a href="/rg-trading-php/pages/orders.php">Orders</a>
        <a href="/rg-trading-php/pages/profile.php">Profile</a>
        <?php if (is_admin()): ?>
          <a href="/rg-trading-php/pages/admin/dashboard.php" class="nav-admin">⚙️ Admin</a>
        <?php endif; ?>
        <div class="nav-user">
          <span>👤 <?= h(current_user()['first_name'] ?? '') ?></span>
          <form method="POST" action="/rg-trading-php/logout.php" style="display:inline;">
            <button type="submit" class="btn-logout">Logout</button>
          </form>
        </div>
      <?php else: ?>
        <a href="/rg-trading-php/login.php" class="btn-login">Login</a>
        <a href="/rg-trading-php/register.php" class="btn-register">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php
$flash = get_flash();
if ($flash): ?>
<div class="flash flash-<?= h($flash['type']) ?>">
  <?= h($flash['message']) ?>
  <button onclick="this.parentElement.remove()">✕</button>
</div>
<?php endif; ?>

<main class="main-content" style="margin:0;max-width:100%;padding:0;">
