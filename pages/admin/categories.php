<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

$result = api_request('GET', '/products/admin/categories', [], true);
$categories = $result['body']['data']['categories'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create') {
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
        ];
        $res = api_request('POST', '/products/admin/categories', $payload, true);
        set_flash($res['status'] === 201 ? 'success' : 'error', $res['body']['message'] ?? 'Category create failed.');
        header('Location: /rg-trading-php/pages/admin/categories.php'); exit;
    }

    if ($action === 'update') {
        $id = intval($_POST['category_id']);
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
        ];
        $res = api_request('PUT', '/products/admin/categories/' . $id, $payload, true);
        set_flash($res['status'] === 200 ? 'success' : 'error', $res['body']['message'] ?? 'Category update failed.');
        header('Location: /rg-trading-php/pages/admin/categories.php'); exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['category_id']);
        $res = api_request('DELETE', '/products/admin/categories/' . $id, [], true);
        set_flash($res['status'] === 200 ? 'success' : 'error', $res['body']['message'] ?? 'Category delete failed.');
        header('Location: /rg-trading-php/pages/admin/categories.php'); exit;
    }
}

$page_title = 'Categories — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:14px;width:min(520px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid #e2e8f0;position:sticky;top:0;background:#fff;z-index:10;}
.modal-header h2{font-size:17px;font-weight:700;color:#1a202c;margin:0;}
.modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:#718096;padding:2px 8px;border-radius:6px;}
.modal-close:hover{background:#f7fafc;}
.modal-body{padding:22px 24px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:12px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.03em;}
.form-group input,.form-group select,.form-group textarea{padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1a202c;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.12);}
.form-group textarea{resize:vertical;min-height:66px;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:14px 24px;border-top:1px solid #e2e8f0;background:#f9fafb;border-radius:0 0 14px 14px;}
.mfbtn{padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;}
.mfbtn-primary{background:#667eea;color:#fff;}
.mfbtn-primary:hover{background:#5a67d8;}
.mfbtn-cancel{background:#e2e8f0;color:#4a5568;}
.mfbtn-cancel:hover{background:#cbd5e0;}
.add-btn{display:inline-flex;align-items:center;gap:7px;background:#667eea;color:#fff;padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;border:none;cursor:pointer;}
.add-btn:hover{background:#5a67d8;}
.action-btns{display:flex;gap:5px;}
</style>

<div class="admin-layout">
  <div class="admin-sidebar">
    <div class="sidebar-title">Admin Panel</div>
    <a href="/rg-trading-php/pages/admin/dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a href="/rg-trading-php/pages/admin/products.php"><span class="icon">❄️</span> Products</a>
    <a href="/rg-trading-php/pages/admin/orders.php"><span class="icon">📦</span> Orders</a>
    <a href="/rg-trading-php/pages/admin/users.php"><span class="icon">👥</span> Users</a>
    <a href="/rg-trading-php/pages/admin/categories.php" class="active"><span class="icon">🏷️</span> Categories</a>
    <a href="/rg-trading-php/pages/admin/reports.php"><span class="icon">📈</span> Reports</a>
    <a href="/rg-trading-php/index.php"><span class="icon">🏪</span> View Store</a>
  </div>

  <div class="admin-main">
    <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <h1>Categories</h1>
        <p>Create and manage product categories</p>
      </div>
      <button class="add-btn" onclick="openCreate()">+ Add Category</button>
    </div>

    <div class="admin-card" style="margin-top:16px;">
      <div class="admin-card-header">
        <h3>All Categories (<?= count($categories) ?>)</h3>
      </div>
      <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Slug</th>
              <th>Description</th>
              <th>Products</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
              <tr>
                <td><strong><?= h($c['name']) ?></strong></td>
                <td style="font-size:12px;color:#718096;font-family:monospace;"><?= h($c['slug']) ?></td>
                <td style="font-size:12px;color:#718096;max-width:300px;"><?= h($c['description'] ?? '—') ?></td>
                <td>
                  <span class="badge" style="background:#ebf8ff;color:#2b6cb0;border:1px solid #bee3f8;">
                    <?= intval($c['product_count'] ?? 0) ?>
                  </span>
                </td>
                <td>
                  <div class="action-btns">
                    <button class="btn-sm btn-sm-blue"
                      onclick='openEdit(<?= json_encode($c) ?>)'>Edit</button>
                    <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Delete category &quot;<?= h(addslashes($c['name'])) ?>&quot;? Products in this category will be unlinked.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="category_id" value="<?= h($c['id']) ?>">
                      <button type="submit" class="btn-sm btn-sm-red">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
              <tr><td colspan="5" style="text-align:center;color:#a0aec0;padding:30px;">No categories found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- CREATE MODAL -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <h2>Add Category</h2>
      <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Name *</label>
            <input type="text" name="name" required id="c_name" placeholder="e.g. Window Type">
          </div>
          <div class="form-group">
            <label>Slug *</label>
            <input type="text" name="slug" required id="c_slug" placeholder="e.g. window-type">
            <small style="color:#718096;font-size:11px;">Lowercase, numbers, hyphens only. Auto-filled from name.</small>
          </div>
          <div class="form-group full">
            <label>Description</label>
            <textarea name="description" placeholder="Optional description..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="mfbtn mfbtn-cancel" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="mfbtn mfbtn-primary">Create Category</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h2>Edit Category</h2>
      <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="category_id" id="e_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Name *</label>
            <input type="text" name="name" required id="e_name">
          </div>
          <div class="form-group">
            <label>Slug *</label>
            <input type="text" name="slug" required id="e_slug">
            <small style="color:#718096;font-size:11px;">Lowercase, numbers, hyphens only.</small>
          </div>
          <div class="form-group full">
            <label>Description</label>
            <textarea name="description" id="e_desc"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="mfbtn mfbtn-cancel" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="mfbtn mfbtn-primary">Update Category</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreate(){ document.getElementById('createModal').classList.add('open'); }

function openEdit(c){
  document.getElementById('e_id').value   = c.id;
  document.getElementById('e_name').value = c.name        || '';
  document.getElementById('e_slug').value = c.slug        || '';
  document.getElementById('e_desc').value = c.description || '';
  document.getElementById('editModal').classList.add('open');
}

function closeModal(id){ document.getElementById(id).classList.remove('open'); }

function toSlug(s){
  return (s||'').toLowerCase().trim()
    .replace(/[^a-z0-9]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
}

var cName = document.getElementById('c_name');
var cSlug = document.getElementById('c_slug');
if(cName && cSlug){
  cName.addEventListener('input', function(){
    if(!cSlug.dataset.touched) cSlug.value = toSlug(this.value);
  });
  cSlug.addEventListener('input', function(){
    this.dataset.touched = '1';
  });
}

document.querySelectorAll('.modal-overlay').forEach(function(o){
  o.addEventListener('click',function(e){ if(e.target===o) o.classList.remove('open'); });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
