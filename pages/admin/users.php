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

// Handle create rider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_rider'])) {
    $create_result = api_request('POST', '/admin/users', [
        'email'      => trim($_POST['email'] ?? ''),
        'password'   => trim($_POST['password'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name'] ?? ''),
        'phone'      => trim($_POST['phone'] ?? '') ?: null,
    ], true);

    set_flash($create_result['status'] === 201 ? 'success' : 'error',
              $create_result['body']['message'] ?? 'Could not create rider.');
    header('Location: /rg-trading-php/pages/admin/users.php');
    exit;
}

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
  <?php include __DIR__ . '/../../includes/admin-sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-header">
      <h1>Users</h1>
      <p>Manage customer, admin, and rider accounts</p>
    </div>

    <!-- Create Rider -->
    <div class="admin-card mb-20">
      <div class="admin-card-header"><h3>Create Rider Account</h3></div>
      <div class="admin-card-body card-padding">
        <form method="POST" class="form-grid">
          <input type="hidden" name="create_rider" value="1">
          <input type="text" name="first_name" placeholder="First name" required class="form-input">
          <input type="text" name="last_name" placeholder="Last name" required class="form-input">
          <input type="email" name="email" placeholder="Email" required class="form-input">
          <input type="text" name="phone" placeholder="Phone" class="form-input">
          <input type="password" name="password" placeholder="Password" required class="form-input">
          <button type="submit" class="btn-sm btn-sm-green btn-create">Create Rider</button>
        </form>
      </div>
    </div>

    <!-- Role Filter -->
    <div class="filters-wrap">
      <?php foreach (['' => 'All Users', 'customer' => 'Customers', 'admin' => 'Admins', 'rider' => 'Riders'] as $val => $label): ?>
        <a href="?role=<?= urlencode($val) ?>" class="pill <?= $role === $val ? 'active' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <form method="GET" class="search-bar mb-20">
      <input type="hidden" name="role" value="<?= h($role) ?>">
      <input type="text" name="search" placeholder="Search by name or email..." value="<?= h($search) ?>">
      <button type="submit">Search</button>
    </form>

    <div class="admin-card">
      <div class="admin-card-header">
        <h3>Users (<?= $pagination['total'] ?? 0 ?>)</h3>
      </div>
      <div class="admin-card-body card-no-padding">
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
                <td class="muted-small"><?= h($u['email']) ?></td>
                <td class="text-small"><?= h($u['phone'] ?? '—') ?></td>
                <td>
                  <?php
                    $roleClassMap = [
                      'admin' => 'badge-processing',
                      'customer' => 'badge-soft-blue',
                      'rider' => 'badge-soft-blue',
                    ];
                    $roleClass = $roleClassMap[$u['role']] ?? '';
                  ?>
                  <span class="badge <?= $roleClass ?>"><?= h($u['role']) ?></span>
                </td>
                <td class="muted-small">
                  <?= $u['last_login_at'] ? date('M d, Y', strtotime($u['last_login_at'])) : 'Never' ?>
                </td>
                <td>
                  <span class="badge <?= $u['is_active'] ? 'badge-soft-green' : 'badge-soft-red' ?>">
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
                    <span class="table-cell-muted">You</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
              <tr><td colspan="7" class="table-empty">No users found</td></tr>
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
