<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

$role   = $_GET['role']   ?? '';
$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$params = http_build_query(array_filter(['role' => $role, 'search' => $search, 'page' => $page, 'limit' => 15]));

$result     = api_request('GET', '/admin/users?' . $params, [], true);
$users      = $result['body']['data']['users']      ?? [];
$pagination = $result['body']['data']['pagination'] ?? [];
$total_pages = ceil(($pagination['total'] ?? 0) / 15);

// Handle toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggle_result = api_request('PATCH', '/admin/users/' . $_POST['toggle_user_id'] . '/toggle-status', [], true);
    set_flash($toggle_result['status'] === 200 ? 'success' : 'error',
              $toggle_result['body']['message'] ?? 'Could not update user.');
    header('Location: /rg-trading-php/pages/admin/users.php');
    exit;
}

$page_title = 'Users — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-layout">
  <div class="admin-sidebar">
    <div class="sidebar-title">Admin Panel</div>
    <a href="/rg-trading-php/pages/admin/dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a href="/rg-trading-php/pages/admin/products.php"><span class="icon">❄️</span> Products</a>
    <a href="/rg-trading-php/pages/admin/orders.php"><span class="icon">📦</span> Orders</a>
    <a href="/rg-trading-php/pages/admin/users.php" class="active"><span class="icon">👥</span> Users</a>
    <a href="/rg-trading-php/pages/admin/categories.php"><span class="icon">🏷️</span> Categories</a>
    <a href="/rg-trading-php/pages/admin/reports.php"><span class="icon">📈</span> Reports</a>
    <a href="/rg-trading-php/index.php"><span class="icon">🏪</span> View Store</a>
  </div>

  <div class="admin-main">
    <div class="admin-header">
      <h1>Users</h1>
      <p>Manage customer and admin accounts</p>
    </div>

    <!-- Role Filter -->
    <div style="display:flex;gap:8px;margin-bottom:16px;">
      <?php foreach (['' => 'All Users', 'customer' => 'Customers', 'admin' => 'Admins'] as $val => $label): ?>
        <a href="?role=<?= urlencode($val) ?>"
           style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
                  background:<?= $role === $val ? '#1a365d' : '#fff' ?>;
                  color:<?= $role === $val ? '#fff' : '#4a5568' ?>;
                  border:1px solid <?= $role === $val ? '#1a365d' : '#e2e8f0' ?>;">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <form method="GET" class="search-bar" style="margin-bottom:20px;">
      <input type="hidden" name="role" value="<?= h($role) ?>">
      <input type="text" name="search" placeholder="Search by name or email..." value="<?= h($search) ?>">
      <button type="submit">Search</button>
    </form>

    <div class="admin-card">
      <div class="admin-card-header">
        <h3>Users (<?= $pagination['total'] ?? 0 ?>)</h3>
      </div>
      <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Role</th>
              <th>Last Login</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><strong><?= h($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
                <td style="font-size:12px;color:#718096;"><?= h($u['email']) ?></td>
                <td style="font-size:12px;"><?= h($u['phone'] ?? '—') ?></td>
                <td>
                  <span class="badge" style="background:<?= $u['role'] === 'admin' ? '#e9d8fd' : '#edf2f7' ?>;color:<?= $u['role'] === 'admin' ? '#553c9a' : '#4a5568' ?>;">
                    <?= h($u['role']) ?>
                  </span>
                </td>
                <td style="font-size:12px;color:#718096;">
                  <?= $u['last_login_at'] ? date('M d, Y', strtotime($u['last_login_at'])) : 'Never' ?>
                </td>
                <td>
                  <span class="badge" style="background:<?= $u['is_active'] ? '#c6f6d5' : '#fed7d7' ?>;color:<?= $u['is_active'] ? '#276749' : '#9b2c2c' ?>;">
                    <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td>
                  <?php if ($u['id'] !== (current_user()['id'] ?? '')): ?>
                    <form method="POST">
                      <input type="hidden" name="toggle_user_id" value="<?= h($u['id']) ?>">
                      <button type="submit"
                              class="btn-sm <?= $u['is_active'] ? 'btn-sm-red' : 'btn-sm-green' ?>"
                              data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?">
                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span style="font-size:11px;color:#a0aec0;">You</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
              <tr><td colspan="7" style="text-align:center;color:#a0aec0;padding:30px;">No users found</td></tr>
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
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
