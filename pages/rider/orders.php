<?php
require_once __DIR__ . '/../../includes/config.php';
require_rider();

$status = $_GET['status'] ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 10;

$query_params = ['page' => $page, 'limit' => $limit];
if ($status !== '') $query_params['status'] = $status;

$params = http_build_query($query_params);

$result = api_request('GET', '/orders/assigned?' . $params, [], true);
$orders = $result['body']['orders'] ?? $result['body']['data']['orders'] ?? [];
$pagination = $result['body']['pagination'] ?? $result['body']['data']['pagination'] ?? ['total' => 0];
$total_pages = ceil(($pagination['total'] ?? 0) / $limit);

$page_title = 'Rider Orders — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div class="page">
  <div class="page-header">
    <h1>Assigned Orders</h1>
    <p>Manage deliveries and upload proof</p>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state">No assigned orders.</div>
  <?php else: ?>
    <div class="orders-table">
      <table>
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Customer note</th>
            <th>Total</th>
            <th>Order Status</th>
            <th>Delivery Status</th>
            <th>Proof</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><strong><?= h($order['order_number'] ?? '-') ?></strong></td>
              <td style="font-size:13px;">
                <?= h($order['customer_name'] ?? 'Customer') ?><br>
                <span style="color:#718096;font-size:12px;display:block;"><?= h($order['email'] ?? '') ?></span>
              </td>
              <td style="font-size:12px;font-weight:500;">
                <a href="tel:<?= h($order['phone'] ?? '') ?>" style="color:#2b6cb0;text-decoration:none;">
                  <?= h($order['phone'] ?? 'No phone') ?>
                </a>
              </td>
              <td style="font-size:12px;min-width:180px;">
                <?php
                  $street = $order['street'] ?? '';
                  $city   = $order['city'] ?? '';
                  $province = $order['province'] ?? '';
                  $zip    = $order['zip_code'] ?? $order['zip'] ?? '';
                ?>
                <?php if (!empty($street)): ?>
                  <?= h($street) ?><br>
                  <?= h(implode(', ', array_filter([$city, $province, $zip]))) ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td style="font-size:12px;max-width:200px;min-width:190px;">
                <?= !empty($order['notes']) ? h($order['notes']) : '—' ?>
              </td>
              <td><strong><?= format_price($order['total_amount'] ?? 0) ?></strong></td>
              <td><span class="badge badge-<?= h($order['status'] ?? 'pending') ?>"><?= h(ucfirst($order['status'] ?? 'Pending')) ?></span></td>
              <td><span class="badge badge-<?= h($order['delivery_status'] ?? 'pending') ?>"><?= h(ucfirst(str_replace('_', ' ', $order['delivery_status'] ?? 'Pending'))) ?></span></td>
              <td>
                <?php if (!empty($order['delivery_proof_url'])): ?>
                  <div style="display:grid;gap:6px;">
                    <a href="<?= BASE_URL ?>/pages/proof.php?path=<?= urlencode($order['delivery_proof_url']) ?>" target="_blank" class="btn-sm btn-sm-green">View proof</a>
                    <button
                      type="button"
                      class="btn-sm btn-sm-red proof-delete"
                      data-order-id="<?= h($order['id']) ?>"
                      style="font-size:11px;"
                    >Delete proof</button>
                  </div>
                <?php else: ?>
                  <span class="badge badge-pending">No proof</span>
                <?php endif; ?>
              </td>
              <td>
                <form class="delivery-form" data-order-id="<?= h($order['id']) ?>" style="display:grid;gap:6px;">
                  <select name="delivery_status" class="delivery-status" style="font-size:11px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;">
                    <option value="">Update status...</option>
                    <?php foreach (['pending','out_for_delivery','delivered','cannot_find_customer','failed','cancelled'] as $ds): ?>
                      <option value="<?= $ds ?>" <?= ($order['delivery_status'] ?? '') === $ds ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $ds)) ?></option>
                    <?php endforeach; ?>
                  </select>

                  <input type="text" name="delivery_note" class="delivery-note" placeholder="Reason if failed or cancelled" value="<?= h($order['delivery_note'] ?? '') ?>" style="font-size:11px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:6px;">

                  <div class="proof-row" style="display:none;">
                    <input type="file" name="delivery_proof" class="delivery-proof-file proof-upload" data-order-id="<?= h($order['id']) ?>" accept="image/png,image/jpeg,image/webp" style="font-size:11px;padding:6px 0;border:none;">
                  </div>

                  <button type="button" class="btn-sm btn-sm-blue delivery-submit" data-order-id="<?= h($order['id']) ?>">Save update</button>
                  <div id="delivery-msg-<?= h($order['id']) ?>" class="delivery-message" style="font-size:12px;color:#4a5568;"></div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:20px;">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</div>

<script>
console.log('Rider orders script initializing');
const API_BASE = '<?= h(API_BASE) ?>';
const ACCESS_TOKEN = '<?= h($_SESSION['access_token'] ?? '') ?>';

document.addEventListener('DOMContentLoaded', () => {
  console.log('Rider orders DOM ready', { API_BASE, hasToken: !!ACCESS_TOKEN });

  async function uploadProof(fileInput) {
    const orderId = fileInput.dataset.orderId;
    const messageEl = document.getElementById(`delivery-msg-${orderId}`);

    if (!fileInput.files || !fileInput.files[0]) return;

    const file = fileInput.files[0];
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
      messageEl.textContent = 'Only PNG, JPG, or WEBP images are allowed.';
      return;
    }

    if (file.size > 8 * 1024 * 1024) {
      messageEl.textContent = 'Maximum image size is 8MB.';
      return;
    }

    messageEl.textContent = 'Uploading proof...';

    const reader = new FileReader();
    reader.onload = async () => {
      try {
        console.log('Uploading proof for', orderId);
        const response = await fetch(`${API_BASE}/orders/${orderId}/delivery`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${ACCESS_TOKEN}`,
          },
          body: JSON.stringify({
            delivery_proof_base64: reader.result,
            delivery_status: 'delivered',
          }),
        });

        let payload;
        try { payload = await response.json(); } catch (e) { payload = { message: 'No JSON response' }; }

        if (!response.ok) {
          console.error('Proof upload failed', response.status, payload);
          messageEl.textContent = payload.message || `Upload failed (${response.status})`;
          return;
        }

        messageEl.textContent = 'Proof uploaded successfully. Refreshing...';
        setTimeout(() => window.location.reload(), 800);
      } catch (error) {
        console.error('uploadProof error', error);
        messageEl.textContent = error.message || 'Upload failed. Please try again.';
      }
    };
    reader.readAsDataURL(file);
  }

  function updateFormFields(form) {
    const status = form.querySelector('.delivery-status').value;
    const noteField = form.querySelector('.delivery-note');
    const proofRow = form.querySelector('.proof-row');
    if (!proofRow) return;

    if (status === 'delivered') {
      proofRow.style.display = 'block';
      noteField.placeholder = 'Optional note for delivered orders';
    } else if (status === 'failed' || status === 'cancelled' || status === 'cannot_find_customer') {
      proofRow.style.display = 'none';
      noteField.placeholder = 'Reason required for failed or cancelled delivery';
    } else {
      proofRow.style.display = 'none';
      noteField.placeholder = 'Add a delivery note (optional)';
    }
  }

  async function updateDeliveryStatus(orderId, status, note) {
    try {
      const response = await fetch(`${API_BASE}/orders/${orderId}/delivery`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${ACCESS_TOKEN}`,
        },
        body: JSON.stringify({ delivery_status: status, delivery_note: note || null }),
      });
      const payload = await response.json().catch(() => ({}));
      return { ok: response.ok, status: response.status, payload };
    } catch (err) {
      console.error('updateDeliveryStatus fetch error', err);
      return { ok: false, status: 0, payload: { message: err.message } };
    }
  }

  async function deleteDeliveryProof(orderId) {
    const attempts = [
      {
        method: 'DELETE',
        url: `${API_BASE}/orders/${orderId}/delivery-proof`,
        body: null,
      },
      {
        method: 'PATCH',
        url: `${API_BASE}/orders/${orderId}/delivery`,
        body: {
          remove_delivery_proof: true,
          delivery_proof_url: null,
        },
      },
      {
        method: 'PATCH',
        url: `${API_BASE}/orders/${orderId}/delivery`,
        body: {
          clear_delivery_proof: true,
          delivery_proof_base64: null,
          delivery_proof_url: null,
        },
      },
    ];

    for (const attempt of attempts) {
      try {
        const response = await fetch(attempt.url, {
          method: attempt.method,
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${ACCESS_TOKEN}`,
          },
          body: attempt.body ? JSON.stringify(attempt.body) : null,
        });
        const payload = await response.json().catch(() => ({}));
        if (response.ok) {
          return { ok: true, payload };
        }
      } catch (err) {
        console.error('deleteDeliveryProof attempt failed', err);
      }
    }

    return {
      ok: false,
      payload: { message: 'Unable to delete proof right now. Please try again.' },
    };
  }

  async function handleDeleteProof(event) {
    const button = event.currentTarget;
    const orderId = button.dataset.orderId;
    const messageEl = document.getElementById(`delivery-msg-${orderId}`);
    if (!orderId || !messageEl) return;

    if (!confirm('Delete the uploaded proof photo for this order?')) {
      return;
    }

    button.disabled = true;
    messageEl.textContent = 'Deleting proof...';

    const result = await deleteDeliveryProof(orderId);
    if (!result.ok) {
      messageEl.textContent = result.payload.message || 'Failed to delete proof.';
      button.disabled = false;
      return;
    }

    messageEl.textContent = 'Proof deleted. Refreshing...';
    setTimeout(() => window.location.reload(), 800);
  }

  async function handleDeliverySave(event) {
    const button = event.currentTarget;
    const form = button.closest('.delivery-form');
    const orderId = button.dataset.orderId;
    const status = form.querySelector('.delivery-status').value;
    const note = form.querySelector('.delivery-note').value.trim();
    const proofInput = form.querySelector('.delivery-proof-file');
    const messageEl = form.querySelector('.delivery-message');

    console.log('handleDeliverySave', { orderId, status, note, hasProof: proofInput && proofInput.files && proofInput.files.length });

    messageEl.textContent = '';
    if (!status) {
      messageEl.textContent = 'Please select a delivery status to save.';
      return;
    }

    if ((status === 'failed' || status === 'cancelled' || status === 'cannot_find_customer') && note === '') {
      messageEl.textContent = 'Please provide a reason when delivery failed or was cancelled.';
      return;
    }

    if (status === 'delivered' && (!proofInput || !proofInput.files || proofInput.files.length === 0)) {
      messageEl.textContent = 'Please upload proof image when marking as delivered.';
      return;
    }

    button.disabled = true;
    messageEl.textContent = 'Saving delivery update...';

    try {
      const result = await updateDeliveryStatus(orderId, status, note);
      if (!result.ok) {
        console.error('save failed', result.status, result.payload);
        messageEl.textContent = result.payload.message || `Failed to save delivery update (${result.status})`;
        button.disabled = false;
        return;
      }

      if (status === 'delivered' && proofInput.files.length) {
        await uploadProof(proofInput);
        return;
      }

      messageEl.textContent = 'Delivery status saved successfully. Refreshing...';
      setTimeout(() => window.location.reload(), 800);
    } catch (error) {
      console.error('handleDeliverySave error', error);
      messageEl.textContent = error.message || 'Unable to save delivery update. Please try again.';
    } finally {
      button.disabled = false;
    }
  }

  document.querySelectorAll('.delivery-form').forEach(form => {
    try {
      updateFormFields(form);
      const statusEl = form.querySelector('.delivery-status');
      const submitEl = form.querySelector('.delivery-submit');
      if (statusEl) statusEl.addEventListener('change', () => updateFormFields(form));
      if (submitEl) submitEl.addEventListener('click', handleDeliverySave);
    } catch (e) {
      console.error('Error attaching handlers to form', e);
    }
  });

  document.querySelectorAll('.proof-upload').forEach(input => {
    input.addEventListener('change', () => uploadProof(input));
  });

  document.querySelectorAll('.proof-delete').forEach(button => {
    button.addEventListener('click', handleDeleteProof);
  });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
