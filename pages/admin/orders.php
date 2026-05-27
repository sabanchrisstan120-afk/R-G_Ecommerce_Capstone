<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

/* ===============================
   Helper Functions
=================================*/
function format_address(?array $addr): string {
    if (!$addr) return '—';
    $parts = array_filter([
        $addr['street'] ?? '',
        $addr['city'] ?? '',
        $addr['province'] ?? '',
        $addr['zip'] ?? ''
    ]);
    return implode(', ', $parts) ?: '—';
}

/* ===============================
   Update Order Status
=================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_id'])) {
    $upd_result = api_request(
        'PATCH',
        '/admin/orders/' . $_POST['update_order_id'] . '/status',
        [
            'status'         => $_POST['new_status'] ?? null,
            'payment_status' => $_POST['new_payment_status'] ?? null
        ],
        true
    );

    set_flash(
        $upd_result['status'] === 200 ? 'success' : 'error',
        $upd_result['body']['message'] ?? 'Update failed.'
    );

    header('Location: orders.php?' . http_build_query([
        'status' => $_GET['status'] ?? '',
        'page'   => $_GET['page'] ?? 1
    ]));
    exit;
}

/* ===============================
   Filters
=================================*/
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 15;

$query_params = ['page' => $page, 'limit' => $limit];
if ($status !== '') $query_params['status'] = $status;
if ($search !== '') $query_params['search'] = $search;

$params = http_build_query($query_params);

/* ===============================
   Fetch Orders
=================================*/
$result     = api_request('GET', '/orders/admin?' . $params, [], true);
$orders     = $result['body']['data']['orders'] ?? $result['body']['orders'] ?? [];
$pagination = $result['body']['data']['pagination'] ?? $result['body']['pagination'] ?? ['total' => 0];
$total_pages = ceil(($pagination['total'] ?? 0) / $limit);

$page_title = 'Orders — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-layout">

<div class="admin-sidebar">
  <div class="sidebar-title">Admin Panel</div>
  <a href="/rg-trading-php/pages/admin/dashboard.php">📊 Dashboard</a>
  <a href="/rg-trading-php/pages/admin/products.php">❄️ Products</a>
  <a href="/rg-trading-php/pages/admin/orders.php" class="active">📦 Orders</a>
  <a href="/rg-trading-php/pages/admin/users.php">👥 Users</a>
  <a href="/rg-trading-php/pages/admin/categories.php">🏷️ Categories</a>
  <a href="/rg-trading-php/pages/admin/reports.php">📈 Reports</a>
  <a href="/rg-trading-php/index.php">🏪 View Store</a>
</div>

<div class="admin-main">

  <div class="admin-header">
    <h1>Orders</h1>
    <p>View and manage all customer orders</p>
  </div>

  <!-- Filters -->
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
    <?php
    $statuses = [
      ''           => 'All',
      'pending'    => 'Pending',
      'confirmed'  => 'Confirmed',
      'processing' => 'Processing',
      'shipped'    => 'Shipped',
      'delivered'  => 'Delivered',
      'cancelled'  => 'Cancelled',
    ];
    foreach ($statuses as $val => $label):
    ?>
      <a href="?status=<?= urlencode($val) ?>"
         style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
                background:<?= $status === $val ? '#1a365d' : '#fff' ?>;
                color:<?= $status === $val ? '#fff' : '#4a5568' ?>;
                border:1px solid <?= $status === $val ? '#1a365d' : '#e2e8f0' ?>;">
        <?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="GET" class="search-bar" style="margin-bottom:20px;">
    <input type="hidden" name="status" value="<?= h($status) ?>">
    <input type="text" name="search" placeholder="Search by order # or email..."
           value="<?= h($search) ?>">
    <button type="submit">Search</button>
  </form>

  <!-- Orders Table -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Orders (<?= $pagination['total'] ?? 0 ?>)</h3>
    </div>

    <div class="admin-card-body" style="padding:0;overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Delivery Address</th>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Update</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:#a0aec0;padding:30px;">
                No orders found
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><strong style="font-size:12px;"><?= h($o['order_number'] ?? '-') ?></strong></td>

                <td>
                  <div style="font-size:13px;">
                    <?= h(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? $o['customer_name'] ?? '')) ?>
                  </div>
                  <div style="font-size:11px;color:#a0aec0;">
                    <?= h($o['email'] ?? '') ?>
                  </div>
                </td>

                <td style="font-size:12px;color:#4a5568;min-width:180px;">
                  <?php if (!empty($o['street'])): ?>
                    <div><?= h($o['street']) ?></div>
                    <div style="color:#a0aec0;">
                      <?= h(implode(', ', array_filter([$o['city'] ?? '', $o['province'] ?? '']))) ?>
                      <?php if (!empty($o['zip'])): ?>
                        <?= h($o['zip']) ?>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span style="color:#cbd5e0;">—</span>
                  <?php endif; ?>
                </td>

                <td style="font-size:12px;">
                  <?= !empty($o['ordered_at']) ? date('M d, Y', strtotime($o['ordered_at'])) : '—' ?>
                </td>

                <td>
                  <strong><?= format_price($o['total_amount'] ?? 0) ?></strong>
                </td>

                <td>
                  <span class="badge badge-<?= h($o['status'] ?? '') ?>">
                    <?= h(ucfirst($o['status'] ?? '')) ?>
                  </span>
                </td>

                <td>
                  <span class="badge badge-<?= h($o['payment_status'] ?? '') ?>">
                    <?= h(ucfirst($o['payment_status'] ?? '')) ?>
                  </span>
                </td>

                <td>
                  <form method="POST" style="display:flex;gap:4px;">
                    <input type="hidden" name="update_order_id" value="<?= h($o['id']) ?>">

                    <select name="new_status" style="font-size:11px;padding:3px 6px;border:1px solid #e2e8f0;border-radius:6px;">
                      <option value="">Status...</option>
                      <?php foreach (['confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($o['status'] ?? '') === $s ? 'selected' : '' ?>>
                          <?= ucfirst($s) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>

                    <select name="new_payment_status" style="font-size:11px;padding:3px 6px;border:1px solid #e2e8f0;border-radius:6px;">
                      <option value="">Payment...</option>
                      <?php foreach (['paid','pending','failed','refunded'] as $ps): ?>
                        <option value="<?= $ps ?>" <?= ($o['payment_status'] ?? '') === $ps ? 'selected' : '' ?>>
                          <?= ucfirst($ps) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-sm btn-sm-blue">Save</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
            <?= $i ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>