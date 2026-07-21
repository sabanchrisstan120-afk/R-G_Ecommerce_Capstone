<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

/* ===============================
   HANDLE ADMIN ORDER UPDATES
=================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_update_order_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $order_id = trim($_POST['admin_update_order_id']);
    $status = trim($_POST['status'] ?? '');
    $delivery_status = trim($_POST['delivery_status'] ?? '');
    $payment_status = trim($_POST['payment_status'] ?? '');
    $expected_delivery_date = trim($_POST['expected_delivery_date'] ?? '');

    $payload = [];
    if ($status !== '') {
        $payload['status'] = $status;
    }
    if ($delivery_status !== '') {
        $payload['delivery_status'] = $delivery_status;
    }
    if ($payment_status !== '') {
        $payload['payment_status'] = $payment_status;
    }
    if ($expected_delivery_date !== '') {
        $payload['expected_delivery_date'] = $expected_delivery_date;
    }

    if (!empty($payload)) {
        $update_result = api_update_order_status($order_id, $payload, true);
        $ok = (($update_result['status'] ?? 500) === 200)
            && (($update_result['body']['success'] ?? false) === true);

        if ($ok) {
            set_flash('success', 'Order updated successfully.');
        } else {
            $message = $update_result['body']['message'] ?? 'Failed to update the order. Please try again.';
            set_flash('error', $message);
        }
    } else {
        set_flash('error', 'No order fields were changed.');
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

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
   Filters
=================================*/
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 10;

$query_params = ['page' => $page, 'limit' => $limit];
if ($status !== '') $query_params['status'] = $status;
if ($search !== '') $query_params['search'] = $search;

$params = http_build_query($query_params);

/* ===============================
   Fetch Orders
=================================*/
$result     = api_request('GET', '/orders/admin?' . $params, [], true);
$orders     = enrich_orders_with_details($result['body']['data']['orders'] ?? $result['body']['orders'] ?? []);
$pagination = $result['body']['data']['pagination'] ?? $result['body']['pagination'] ?? ['total' => 0];
$total_pages = ceil(($pagination['total'] ?? 0) / $limit);

$page_title = 'Orders — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div class="admin-layout">
  <?php include __DIR__ . '/../../includes/admin-sidebar.php'; ?>

<div class="admin-main">

  <div class="admin-header">
    <h1>Orders</h1>
    <p>View and manage all customer orders</p>
  </div>

  <!-- Filters -->
  <div class="filters-wrap">
    <?php
    $statuses = [
      '' => 'All',
      'pending_review' => 'Pending Review',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'out_for_delivery' => 'Out for Delivery',
      'delivered' => 'Delivered',
      'cancelled' => 'Cancelled',
    ];
    foreach ($statuses as $val => $label):
    ?>
      <a href="?status=<?= urlencode($val) ?>" class="pill <?= $status === $val ? 'active' : '' ?>">
        <?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="GET" class="search-bar mb-20">
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

    <div class="admin-card-body card-no-padding">
      <table class="data-table">
        <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Delivery Address</th>
              <th>Contact</th>
              <th>Date</th>
              <th>Expected Delivery</th>
              <th>Total</th>
              <th>Status</th>
              <th>Delivery</th>
              <th>Payment</th>
              <th>Actions</th>
              <!-- <th>Proof</th> -->
            </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="12" class="table-empty">No orders found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><strong class="text-small font-700"><?= h($o['order_number'] ?? '-') ?></strong></td>

                <td>
                  <div class="text-small">
                    <?= h(get_order_customer_name($o)) ?>
                  </div>
                  <div class="muted-small">
                    <?= h(get_order_customer_email($o)) ?>
                  </div>
                  <?php $phone = get_order_phone($o); if ($phone !== ''): ?>
                    <div class="muted-small">
                      <?= h($phone) ?>
                    </div>
                  <?php endif; ?>
                </td>

                <td class="text-small min-w-180">
                  <?php $address = format_order_address($o); ?>
                  <?php if ($address !== '—'): ?>
                    <?= h($address) ?>
                  <?php else: ?>
                    <span class="text-small muted-small">—</span>
                  <?php endif; ?>
                </td>

                <td class="text-small">
                  <?php $phone = get_order_phone($o); ?>
                  <?= $phone !== '' ? h($phone) : '<span class="text-small muted-small">—</span>' ?>
                </td>

                <td class="text-small">
                  <?= !empty($o['ordered_at']) ? date('M d, Y', strtotime($o['ordered_at'])) : '—' ?>
                </td>

                <td class="text-small">
                  <div class="text-small mb-6">
                    <?= !empty($o['expected_delivery_date']) ? date('M d, Y', strtotime($o['expected_delivery_date'])) : '<span class="muted-small">—</span>' ?>
                  </div>
                  <input
                    type="date"
                    class="form-input"
                    form="admin-order-update-<?= h($o['id']) ?>"
                    name="expected_delivery_date"
                    value="<?= !empty($o['expected_delivery_date']) ? h(date('Y-m-d', strtotime($o['expected_delivery_date']))) : '' ?>"
                    aria-label="Expected delivery date"
                  />
                </td>

                <td>
                  <strong><?= format_price($o['total_amount'] ?? 0) ?></strong>
                </td>

                <td>
                  <select class="form-select" form="admin-order-update-<?= h($o['id']) ?>" name="status" aria-label="Order status">
                    <?php
                      $statusOptions = order_status_options();
                      $currentStatus = strtolower($o['status'] ?? 'pending');
                    ?>
                    <?php foreach ($statusOptions as $option): ?>
                      <option value="<?= h($option) ?>" <?= $option === $currentStatus ? 'selected' : '' ?>>
                        <?= h(order_status_label($option)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <td>
                  <select class="form-select" form="admin-order-update-<?= h($o['id']) ?>" name="delivery_status" aria-label="Delivery status">
                    <?php
                      $deliveryOptions = delivery_status_options();
                      $currentDelivery = strtolower($o['delivery_status'] ?? 'pending');
                    ?>
                    <?php foreach ($deliveryOptions as $option): ?>
                      <option value="<?= h($option) ?>" <?= $option === $currentDelivery ? 'selected' : '' ?>>
                        <?= h(ucfirst(str_replace('_', ' ', $option))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <td>
                  <select class="form-select" form="admin-order-update-<?= h($o['id']) ?>" name="payment_status" aria-label="Payment status">
                    <?php
                      $paymentOptions = payment_status_options();
                      $currentPayment = strtolower($o['payment_status'] ?? 'pending');
                    ?>
                    <?php foreach ($paymentOptions as $option): ?>
                      <option value="<?= h($option) ?>" <?= $option === $currentPayment ? 'selected' : '' ?>>
                        <?= h(ucfirst(str_replace('_', ' ', $option))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <td>
                  <form id="admin-order-update-<?= h($o['id']) ?>" method="POST" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="admin_update_order_id" value="<?= h($o['id']) ?>">
                    <button type="submit" class="btn-sm btn-sm-green">Save</button>
                  </form>

                  <?php if (strtolower($o['status'] ?? '') === 'pending'): ?>
                    <div class="display-grid gap-6">
                      <form method="POST" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="admin_update_order_id" value="<?= h($o['id']) ?>">
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" class="btn-sm btn-sm-green">Approve</button>
                      </form>
                      <form method="POST" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="admin_update_order_id" value="<?= h($o['id']) ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="btn-sm btn-sm-red">Reject</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </td>
                <!-- <td class="proof-cell">
                  <?php
                    $proofUrl = !empty($o['proof_of_delivery_image']) ? $o['proof_of_delivery_image'] : ($o['delivery_proof_url'] ?? '');
                    $proofUploadedAt = !empty($o['proof_uploaded_at']) ? date('M d, Y H:i', strtotime($o['proof_uploaded_at'])) : '';
                    $hasProof = !empty($proofUrl);
                    $canUploadProof = !$hasProof && ($o['status'] ?? '') === 'shipped' && ($o['delivery_status'] ?? '') === 'out_for_delivery';
                  ?>
                  <div class="proof-cell-inner">
                    <?php if ($hasProof): ?>
                      <span class="badge badge-success" title="<?= h($proofUploadedAt ?: 'Proof uploaded') ?>">PROOF UPLOADED</span>
                      <div class="proof-actions mt-8">
                        <button type="button" class="btn-sm btn-sm-green proof-view-btn" data-proof-url="<?= h($proofUrl) ?>" data-proof-title="<?= h($proofUploadedAt ?: 'Proof uploaded') ?>">View Proof</button>
                        <button type="button" class="btn-sm btn-sm-blue proof-replace-btn" data-order-id="<?= h($o['id']) ?>" data-proof-url="<?= h($proofUrl) ?>" data-proof-uploaded-at="<?= h($proofUploadedAt) ?>">Replace Proof</button>
                        <button type="button" class="btn-sm btn-sm-red proof-delete-btn" data-order-id="<?= h($o['id']) ?>" data-proof-url="<?= h($proofUrl) ?>" data-current-order-status="<?= h($o['status'] ?? '') ?>" data-current-delivery-status="<?= h($o['delivery_status'] ?? '') ?>">Delete Proof</button>
                      </div>
                    <?php elseif ($canUploadProof): ?>
                      <span class="badge badge-warning">NO PROOF</span>
                      <button type="button" class="btn-sm btn-sm-blue proof-upload-btn" data-order-id="<?= h($o['id']) ?>" data-current-order-status="<?= h($o['status'] ?? '') ?>" data-current-delivery-status="<?= h($o['delivery_status'] ?? '') ?>">Upload Proof</button>
                    <?php else: ?>
                      <span class="badge badge-warning">NO PROOF</span>
                    <?php endif; ?>
                  </div>
                </td> -->

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
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-arrow">&lsaquo;</a>
      <?php else: ?>
        <span class="pagination-arrow disabled">&lsaquo;</span>
      <?php endif; ?>

      <?php
        $window     = 10;
        $page_start = max(1, min($page - intdiv($window, 2), $total_pages - $window + 1));
        $page_end   = min($total_pages, $page_start + $window - 1);
      ?>
      <?php for ($i = $page_start; $i <= $page_end; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
            <?= $i ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-arrow">&rsaquo;</a>
      <?php else: ?>
        <span class="pagination-arrow disabled">&rsaquo;</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>
</div>

<div id="proofUploadModal" class="modal-overlay">
  <div class="modal modal-large">
    <div class="modal-header">
      <h2 id="proofUploadModalTitle">Upload Proof of Delivery</h2>
      <button type="button" class="modal-close" id="proofUploadCloseBtn">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div>
          <label class="form-label-strong" for="proofFileInput">Choose image</label>
          <input id="proofFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="form-input">
          <div id="proofFileHint" class="muted-small mt-8">JPG, JPEG, PNG, WEBP. Max 5MB.</div>
        </div>
      </div>
      <div id="proofPreviewContainer" class="proof-preview hidden mt-16">
        <div class="proof-preview-label">Preview</div>
        <img id="proofPreviewImage" class="proof-preview-image" alt="Proof preview">
      </div>
      <div id="proofUploadMessage" class="muted-small text-danger mt-12"></div>
      <div id="proofUploadProgress" class="upload-progress hidden mt-16">
        <div class="upload-progress-bar" style="width:0%"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-sm btn-sm-red" id="proofUploadCancelBtn">Cancel</button>
      <button type="button" class="btn-sm btn-sm-green" id="proofUploadConfirmBtn">Upload</button>
    </div>
  </div>
</div>

<div id="proofViewModal" class="modal-overlay">
  <div class="modal modal-large">
    <div class="modal-header">
      <h2 id="proofViewTitle">View Proof of Delivery</h2>
      <button type="button" class="modal-close" id="proofViewCloseBtn">&times;</button>
    </div>
    <div class="modal-body">
      <div class="proof-view-wrapper">
        <img id="proofViewImage" class="proof-view-image" alt="Proof image">
      </div>
    </div>
    <div class="modal-footer">
      <a id="proofDownloadLink" class="btn-sm btn-sm-blue" download="proof-of-delivery.jpg">Download</a>
      <button type="button" class="btn-sm btn-sm-red" id="proofViewCloseBtn2">Close</button>
    </div>
  </div>
</div>

<div id="adminToast" class="toast hidden"></div>

<script>
(function () {
  const proofEndpoint = '<?= BASE_URL ?>/pages/admin/proof-upload.php';
  const csrfToken = '<?= h(csrf_token()) ?>';
  const proofUploadModal = document.getElementById('proofUploadModal');
  const proofViewModal = document.getElementById('proofViewModal');
  const proofFileInput = document.getElementById('proofFileInput');
  const proofPreviewContainer = document.getElementById('proofPreviewContainer');
  const proofPreviewImage = document.getElementById('proofPreviewImage');
  const proofUploadMessage = document.getElementById('proofUploadMessage');
  const proofUploadProgress = document.getElementById('proofUploadProgress');
  const proofUploadBar = proofUploadProgress.querySelector('.upload-progress-bar');
  const proofUploadTitle = document.getElementById('proofUploadModalTitle');
  const proofDownloadLink = document.getElementById('proofDownloadLink');
  const proofViewImage = document.getElementById('proofViewImage');
  const proofViewTitle = document.getElementById('proofViewTitle');
  const adminToast = document.getElementById('adminToast');

  let currentOrderId = null;
  let currentProofUrl = '';

  function showToast(message, type = 'success') {
    adminToast.textContent = message;
    adminToast.className = 'toast ' + (type === 'error' ? 'toast-error' : 'toast-success');
    adminToast.classList.remove('hidden');
    window.setTimeout(() => adminToast.classList.add('hidden'), 4200);
  }

  function toggleModal(modal, isOpen) {
    if (isOpen) {
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
    } else {
      modal.classList.remove('open');
      document.body.style.overflow = '';
    }
  }

  function resetUploadModal() {
    currentOrderId = null;
    currentProofUrl = '';
    proofFileInput.value = '';
    proofPreviewImage.src = '';
    proofPreviewContainer.classList.add('hidden');
    proofUploadMessage.textContent = '';
    proofUploadProgress.classList.add('hidden');
    proofUploadBar.style.width = '0%';
  }

  function openUploadModal(orderId, title, proofUrl = '') {
    currentOrderId = orderId;
    currentProofUrl = proofUrl;
    proofUploadTitle.textContent = title;
    resetUploadModal();
    toggleModal(proofUploadModal, true);
  }

  function openViewModal(url, title) {
    proofViewTitle.textContent = title;
    proofViewImage.src = url;
    proofDownloadLink.href = url;
    proofDownloadLink.download = url.split('/').pop() || 'proof-of-delivery.jpg';
    toggleModal(proofViewModal, true);
  }

  function previewFile(file) {
    if (!file) {
      proofPreviewContainer.classList.add('hidden');
      return;
    }

    const allowed = ['image/png', 'image/jpeg', 'image/webp'];
    if (!allowed.includes(file.type)) {
      proofUploadMessage.textContent = 'Only JPG, JPEG, PNG, or WEBP files are allowed.';
      proofPreviewContainer.classList.add('hidden');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      proofUploadMessage.textContent = 'File must be 5MB or smaller.';
      proofPreviewContainer.classList.add('hidden');
      return;
    }

    proofUploadMessage.textContent = '';
    const reader = new FileReader();
    reader.onload = function (event) {
      proofPreviewImage.src = event.target.result;
      proofPreviewContainer.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
  }

  function closeModal(modal) {
    toggleModal(modal, false);
    if (modal === proofUploadModal) {
      resetUploadModal();
    }
  }

  function uploadProof() {
    const file = proofFileInput.files[0];
    if (!currentOrderId) {
      proofUploadMessage.textContent = 'Missing order selection.';
      return;
    }
    if (!file) {
      proofUploadMessage.textContent = 'Please select a proof image before uploading.';
      return;
    }

    const allowed = ['image/png', 'image/jpeg', 'image/webp'];
    if (!allowed.includes(file.type)) {
      proofUploadMessage.textContent = 'Only JPG, JPEG, PNG, or WEBP files are allowed.';
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      proofUploadMessage.textContent = 'Proof must be 5MB or smaller.';
      return;
    }

    proofUploadMessage.textContent = '';
    proofUploadProgress.classList.remove('hidden');
    proofUploadBar.style.width = '0%';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'upload_proof');
    formData.append('order_id', currentOrderId);
    formData.append('proof_image', file);
    if (currentProofUrl) {
      formData.append('replace_proof_url', currentProofUrl);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', proofEndpoint, true);
    xhr.upload.onprogress = function (event) {
      if (event.lengthComputable) {
        const percent = Math.round((event.loaded / event.total) * 100);
        proofUploadBar.style.width = percent + '%';
      }
    };
    xhr.onload = function () {
      proofUploadProgress.classList.add('hidden');
      if (xhr.status >= 200 && xhr.status < 300) {
        const response = JSON.parse(xhr.responseText || '{}');
        if (response.success) {
          showToast(response.message || 'Proof uploaded successfully.');
          closeModal(proofUploadModal);
          window.setTimeout(() => window.location.reload(), 900);
          return;
        }
        proofUploadMessage.textContent = response.message || 'Upload failed.';
      } else {
        proofUploadMessage.textContent = 'Upload failed with status ' + xhr.status + '.';
      }
    };
    xhr.onerror = function () {
      proofUploadProgress.classList.add('hidden');
      proofUploadMessage.textContent = 'Upload failed. Please try again.';
    };
    xhr.send(formData);
  }

  function deleteProof(orderId, proofUrl, currentOrderStatus, currentDeliveryStatus) {
    if (!orderId || !proofUrl) return;
    if (!confirm('Delete proof of delivery for this order?')) return;

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'delete_proof');
    formData.append('order_id', orderId);
    formData.append('proof_url', proofUrl);
    formData.append('revert_order_status', currentOrderStatus === 'delivered' ? 'shipped' : currentOrderStatus);
    formData.append('revert_delivery_status', currentDeliveryStatus === 'delivered' ? 'out_for_delivery' : currentDeliveryStatus);

    fetch(proofEndpoint, { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message || 'Proof deleted successfully.');
          window.setTimeout(() => window.location.reload(), 900);
        } else {
          showToast(data.message || 'Could not delete proof.', 'error');
        }
      })
      .catch(() => showToast('Could not delete proof. Please try again.', 'error'));
  }

  proofFileInput.addEventListener('change', function () {
    previewFile(this.files[0]);
  });

  document.getElementById('proofUploadCloseBtn').addEventListener('click', () => closeModal(proofUploadModal));
  document.getElementById('proofUploadCancelBtn').addEventListener('click', () => closeModal(proofUploadModal));
  document.getElementById('proofUploadConfirmBtn').addEventListener('click', uploadProof);
  document.getElementById('proofViewCloseBtn').addEventListener('click', () => closeModal(proofViewModal));
  document.getElementById('proofViewCloseBtn2').addEventListener('click', () => closeModal(proofViewModal));

  document.querySelectorAll('.proof-upload-btn, .proof-replace-btn').forEach(button => {
    button.addEventListener('click', function () {
      openUploadModal(
        this.dataset.orderId,
        this.classList.contains('proof-replace-btn') ? 'Replace Proof of Delivery' : 'Upload Proof of Delivery',
        this.dataset.proofUrl || ''
      );
    });
  });

  document.querySelectorAll('.proof-view-btn').forEach(button => {
    button.addEventListener('click', function () {
      openViewModal(this.dataset.proofUrl, this.dataset.proofTitle || 'Proof of Delivery');
    });
  });

  document.querySelectorAll('.proof-delete-btn').forEach(button => {
    button.addEventListener('click', function () {
      deleteProof(this.dataset.orderId, this.dataset.proofUrl, this.dataset.currentOrderStatus, this.dataset.currentDeliveryStatus);
    });
  });
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>