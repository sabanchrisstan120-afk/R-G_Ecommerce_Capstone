<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-container">
    <a href="<?= BASE_URL ?>/landing.php" class="nav-brand">
      <span class="brand-rg">R&amp;G</span> Trading ❄️
    </a>
    
    <div class="nav-links">
      <a href="<?= BASE_URL ?>/index.php">Products</a>

      <?php if (!is_rider()): ?>
        <a href="<?= BASE_URL ?>/pages/cart.php" class="cart-icon">
          🛒
          <span id="cart-count">
            <?= count($_SESSION['cart'] ?? []) ?>
          </span>
        </a>
      <?php endif; ?>
  
  <?php if (is_logged_in()): ?>
        <?php if (is_rider()): ?>
          <a href="<?= BASE_URL ?>/pages/rider/orders.php">Rider Orders</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/pages/orders.php">Orders</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/profile.php">Profile</a>
        <?php if (is_admin()): ?>
          <a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="nav-admin">⚙️ Admin</a>
        <?php endif; ?>
        <div class="nav-user">
          <span>👤 <?= h(current_user()['first_name'] ?? '') ?></span>
          <form method="POST" action="<?= BASE_URL ?>/logout.php" class="inline-form">
            <button type="submit" class="btn-logout">Logout</button>
          </form>
        </div>

        
      <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php" class="btn-login">Login</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn-register">Register</a>
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

<main class="main-content main-full">