<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

$period  = intval($_GET['period'] ?? 30);
$summary = api_request('GET', '/admin/dashboard/summary?period=' . $period, [], true);
$data    = $summary['body']['data'] ?? [];

$rev       = $data['revenue']    ?? [];
$orders    = $data['orders']     ?? [];
$customers = $data['customers']  ?? [];
$top       = $data['top_product'] ?? null;

// Revenue trends for chart
$trends_result = api_request('GET', '/admin/dashboard/revenue-trends?granularity=day&months=1', [], true);
$trends        = $trends_result['body']['data']['trends'] ?? [];

// Top products
$top_products_result = api_request('GET', '/admin/dashboard/top-products?limit=5', [], true);
$top_products        = $top_products_result['body']['data']['products'] ?? [];

// Recent orders
$recent_orders_result = api_request('GET', '/admin/orders?limit=5', [], true);
$recent_orders        = $recent_orders_result['body']['data']['orders'] ?? [];

$page_title = 'Admin Dashboard — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-layout">

  <!-- Sidebar -->
  <div class="admin-sidebar">
    <div class="sidebar-title">Admin Panel</div>
    <a href="/rg-trading-php/pages/admin/dashboard.php" class="active">
      <span class="icon">📊</span> Dashboard
    </a>
    <a href="/rg-trading-php/pages/admin/products.php">
      <span class="icon">❄️</span> Products
    </a>
    <a href="/rg-trading-php/pages/admin/orders.php">
      <span class="icon">📦</span> Orders
    </a>
    <a href="/rg-trading-php/pages/admin/users.php">
      <span class="icon">👥</span> Users
    </a>
    <a href="/rg-trading-php/pages/admin/categories.php">
      <span class="icon">🏷️</span> Categories
    </a>
    
    <a href="/rg-trading-php/pages/admin/reports.php">
      <span class="icon">📊</span> Reports
    </a>
    
    
    <a href="/rg-trading-php/index.php" style="margin-top:auto;border-top:1px solid #2d3748;padding-top:12px;">
      <span class="icon">🏪</span> View Store
    </a>
  </div>

  <!-- Main Content -->
  <div class="admin-main">
    <div class="admin-header">
      <h1>Dashboard</h1>
      <p>Welcome back, <?= h(current_user()['first_name'] ?? 'Admin') ?>. Here's what's happening.</p>
    </div>

    <!-- Period Filter -->
    <div style="display:flex;gap:8px;margin-bottom:24px;">
      <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $val => $label): ?>
        <a href="?period=<?= $val ?>"
           style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
                  background:<?= $period === $val ? '#1a365d' : '#fff' ?>;
                  color:<?= $period === $val ? '#fff' : '#4a5568' ?>;
                  border:1px solid <?= $period === $val ? '#1a365d' : '#e2e8f0' ?>;">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- KPI Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Revenue (<?= $period ?>d)</div>
        <div class="stat-value"><?= format_price($rev['period'] ?? 0) ?></div>
        <div class="stat-sub">
          Total: <?= format_price($rev['total'] ?? 0) ?>
          <?php if ($rev['growth_pct'] !== null): ?>
            · <span style="color:<?= $rev['growth_pct'] >= 0 ? '#38a169' : '#e53e3e' ?>">
              <?= $rev['growth_pct'] >= 0 ? '↑' : '↓' ?> <?= abs($rev['growth_pct']) ?>%
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Orders (<?= $period ?>d)</div>
        <div class="stat-value"><?= $orders['period_orders'] ?? 0 ?></div>
        <div class="stat-sub">Total: <?= $orders['total_orders'] ?? 0 ?> · Pending: <?= $orders['pending_orders'] ?? 0 ?></div>
      </div>
      <div class="stat-card orange">
        <div class="stat-label">New Customers (<?= $period ?>d)</div>
        <div class="stat-value"><?= $customers['new_customers'] ?? 0 ?></div>
        <div class="stat-sub">Total: <?= $customers['total_customers'] ?? 0 ?> · Repeat: <?= $customers['repeat_customers'] ?? 0 ?></div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Top Product</div>
        <div class="stat-value" style="font-size:16px;line-height:1.3;"><?= h($top['name'] ?? 'N/A') ?></div>
        <div class="stat-sub"><?= $top ? $top['units_sold'] . ' units sold' : 'No data yet' ?></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

      <!-- Top Products -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h3>Top Products</h3>
          <a href="/rg-trading-php/pages/admin/products.php" style="font-size:12px;color:#3182ce;">View all →</a>
        </div>
        <div class="admin-card-body" style="padding:0;">
          <table class="data-table">
            <thead>
              <tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr>
            </thead>
            <tbody>
              <?php foreach ($top_products as $p): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;font-size:12px;"><?= h($p['name']) ?></div>
                    <div style="font-size:11px;color:#a0aec0;"><?= h($p['brand']) ?></div>
                  </td>
                  <td><?= $p['units_sold'] ?></td>
                  <td><?= format_price($p['revenue_generated']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($top_products)): ?>
                <tr><td colspan="3" style="text-align:center;color:#a0aec0;padding:20px;">No sales data yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h3>Recent Orders</h3>
          <a href="/rg-trading-php/pages/admin/orders.php" style="font-size:12px;color:#3182ce;">View all →</a>
        </div>
        <div class="admin-card-body" style="padding:0;">
          <table class="data-table">
            <thead>
              <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent_orders as $o): ?>
                <tr>
                  <td style="font-weight:600;font-size:12px;"><?= h($o['order_number']) ?></td>
                  <td style="font-size:12px;"><?= h($o['first_name'] . ' ' . $o['last_name']) ?></td>
                  <td><?= format_price($o['total_amount']) ?></td>
                  <td><span class="badge badge-<?= h($o['status']) ?>"><?= h($o['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($recent_orders)): ?>
                <tr><td colspan="4" style="text-align:center;color:#a0aec0;padding:20px;">No orders yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
