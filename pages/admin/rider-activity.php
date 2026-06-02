<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

$filters = [
    'rider_id' => trim($_GET['rider_id'] ?? ''),
    'order_id' => trim($_GET['order_id'] ?? ''),
    'page'     => max(1, intval($_GET['page'] ?? 1)),
    'limit'    => 25,
];

$query_params = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});

$params = http_build_query($query_params);

$riders = [];
$rider_result = api_request('GET', '/admin/users?role=rider&limit=200', [], true);
$riders = $rider_result['body']['data']['users'] ?? $rider_result['body']['users'] ?? [];

$result = api_request('GET', '/admin/rider-activity' . ($params ? '?' . $params : ''), [], true);
$activity = $result['body']['data']['activity'] ?? $result['body']['activity'] ?? [];
$pagination = $result['body']['data']['pagination'] ?? $result['body']['pagination'] ?? ['total' => 0];
$total_pages = ceil(($pagination['total'] ?? 0) / $filters['limit']);

$page_title = 'Rider Activity — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';

function render_metadata($json) {
    if (!$json) return '—';
    $data = json_decode($json, true);
    if (!is_array($data)) return h($json);
    $parts = [];
    foreach ($data as $key => $value) {
        $parts[] = '<div><strong>' . h(str_replace('_', ' ', $key)) . ':</strong> ' . h(is_bool($value) ? ($value ? 'Yes' : 'No') : (is_scalar($value) ? $value : json_encode($value))) . '</div>';
    }
    return implode('', $parts);
}
?>

<div class="admin-layout">

<div class="admin-sidebar">
  <div class="sidebar-title">Admin Panel</div>
  <a href="/rg-trading-php/pages/admin/dashboard.php">📊 Dashboard</a>
  <a href="/rg-trading-php/pages/admin/products.php">❄️ Products</a>
  <a href="/rg-trading-php/pages/admin/orders.php">📦 Orders</a>
  <a href="/rg-trading-php/pages/admin/rider-activity.php" class="active">🚴 Rider Activity</a>
  <a href="/rg-trading-php/pages/admin/users.php">👥 Users</a>
  <a href="/rg-trading-php/pages/admin/categories.php">🏷️ Categories</a>
  <a href="/rg-trading-php/pages/admin/reports.php">📈 Reports</a>
  <a href="/rg-trading-php/index.php">🏪 View Store</a>
</div>

<div class="admin-main">
  <div class="admin-header">
    <h1>Rider Activity</h1>
    <p>Track delivery events and uploads from riders.</p>
  </div>

  <form method="GET" class="search-bar" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
    <select name="rider_id" style="padding:8px;border:1px solid #e2e8f0;border-radius:8px;min-width:220px;">
      <option value="">All Riders</option>
      <?php foreach ($riders as $rider): ?>
        <option value="<?= h($rider['id']) ?>" <?= $filters['rider_id'] === $rider['id'] ? 'selected' : '' ?>>
          <?= h(trim($rider['first_name'] . ' ' . $rider['last_name'])) ?> (<?= h($rider['email']) ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <input type="text" name="order_id" placeholder="Order ID" value="<?= h($filters['order_id']) ?>" style="padding:8px;border:1px solid #e2e8f0;border-radius:8px;min-width:220px;">
    <button type="submit" style="padding:10px 16px;border:none;border-radius:8px;background:#1a365d;color:#fff;">Filter</button>
  </form>

  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Activity Logs (<?= $pagination['total'] ?? 0 ?>)</h3>
    </div>
    <div class="admin-card-body" style="padding:0;overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Rider</th>
            <th>Order</th>
            <th>Event</th>
            <th>Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($activity)): ?>
            <tr>
              <td colspan="6" style="text-align:center;color:#a0aec0;padding:30px;">No rider activity found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($activity as $item): ?>
              <tr>
                <td><?= !empty($item['created_at']) ? date('M d, Y H:i', strtotime($item['created_at'])) : '—' ?></td>
                <td>
                  <?= h(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?: 'Unknown' ?><br>
                  <span style="color:#718096;font-size:12px;"><?= h($item['email'] ?? '') ?></span>
                </td>
                <td><?= !empty($item['metadata']) && ($m = json_decode($item['metadata'], true)) && !empty($m['order_id']) ? h($m['order_id']) : '—' ?></td>
                <td><?= h(ucfirst(str_replace('_', ' ', $item['event_type'] ?? ''))) ?></td>
                <td style="max-width:380px;overflow-wrap:anywhere;"><?= render_metadata($item['metadata'] ?? '') ?></td>
                <td><?= h($item['ip_address'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?<?= h(http_build_query(array_merge($_GET, ['page' => $p]))) ?>"
           style="padding:8px 12px;border-radius:8px;background:<?= $p === $filters['page'] ? '#1a365d' : '#fff' ?>;color:<?= $p === $filters['page'] ? '#fff' : '#4a5568' ?>;border:1px solid #e2e8f0;">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</div>
</div>
