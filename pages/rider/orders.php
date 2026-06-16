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
              <td class="text-small">
                <?= h($order['customer_name'] ?? 'Customer') ?><br>
                <span class="muted-small"><?= h($order['email'] ?? '') ?></span>
              </td>
              <td class="text-small font-600">
                <a href="tel:<?= h($order['phone'] ?? '') ?>" class="btn-link">
                  <?= h($order['phone'] ?? 'No phone') ?>
                </a>
              </td>
              <td class="text-small min-w-180">
                <?php
                  $street   = $order['street']   ?? '';
                  $city     = $order['city']      ?? '';
                  $province = $order['province']  ?? '';
                  $zip      = $order['zip_code']  ?? $order['zip'] ?? '';
                ?>
                <?php if (!empty($street)): ?>
                  <?= h($street) ?><br>
                  <?= h(implode(', ', array_filter([$city, $province, $zip]))) ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="text-small max-w-200 min-w-190">
                <?= !empty($order['notes']) ? h($order['notes']) : '—' ?>
              </td>
              <td><strong><?= format_price($order['total_amount'] ?? 0) ?></strong></td>
              <td>
                <span class="badge badge-<?= h($order['status'] ?? 'pending') ?>">
                  <?= h(ucfirst($order['status'] ?? 'Pending')) ?>
                </span>
              </td>
              <td>
                <span class="badge badge-<?= h($order['delivery_status'] ?? 'pending') ?>">
                  <?= h(ucfirst(str_replace('_', ' ', $order['delivery_status'] ?? 'Pending'))) ?>
                </span>
              </td>
              <td>
                <?php if (!empty($order['delivery_proof_url'])): ?>
                  <div class="display-grid gap-6">
                    <a href="<?= BASE_URL ?>/pages/proof.php?path=<?= urlencode($order['delivery_proof_url']) ?>"
                       target="_blank" class="btn-sm btn-sm-green">View proof</a>
                    <button type="button"
                            class="btn-sm btn-sm-orange proof-replace text-small"
                            data-order-id="<?= h($order['id']) ?>">Replace proof</button>
                    <button type="button"
                            class="btn-sm btn-sm-red proof-delete text-small"
                            data-order-id="<?= h($order['id']) ?>">Delete proof</button>
                  </div>
                <?php else: ?>
                  <span class="badge badge-pending">No proof</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $currentDelivery = $order['delivery_status'] ?? '';
                  $currentOrder    = $order['status']          ?? '';
                  $hasProof        = !empty($order['delivery_proof_url']) ? '1' : '0';
                ?>
                <form class="delivery-form form-grid"
                      data-order-id="<?= h($order['id']) ?>"
                      data-has-proof="<?= $hasProof ?>">

                  <!-- Delivery status dropdown -->
                  <div class="status-dropdown">
                    <button type="button"
                            class="dropdown-toggle btn-sm btn-sm-blue"
                            data-target="delivery_status">
                      <?= $currentDelivery
                            ? h(ucfirst(str_replace('_', ' ', $currentDelivery)))
                            : 'Update delivery status…' ?>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <?php foreach (['pending','out_for_delivery','delivered','cannot_find_customer','failed','cancelled'] as $ds): ?>
                        <button type="button"
                                class="dropdown-option <?= $ds === $currentDelivery ? 'is-selected' : '' ?>"
                                data-value="<?= $ds ?>">
                          <?= ucfirst(str_replace('_', ' ', $ds)) ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="delivery_status"
                           class="delivery-status" value="<?= h($currentDelivery) ?>">
                  </div>

                  <!-- Order status dropdown -->
                  <div class="status-dropdown">
                    <button type="button"
                            class="dropdown-toggle btn-sm btn-sm-blue"
                            data-target="order_status">
                      <?= $currentOrder ? h(ucfirst($currentOrder)) : 'Update order status…' ?>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $os): ?>
                        <button type="button"
                                class="dropdown-option <?= $os === $currentOrder ? 'is-selected' : '' ?>"
                                data-value="<?= $os ?>">
                          <?= ucfirst($os) ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="order_status"
                           class="order-status" value="<?= h($currentOrder) ?>">
                  </div>

                  <!-- Delivery note -->
                  <input type="text"
                         name="delivery_note"
                         class="delivery-note form-input"
                         placeholder="Reason if failed or cancelled"
                         value="<?= h($order['delivery_note'] ?? '') ?>">

                  <!-- Proof upload row (shown when needed) -->
                  <div class="proof-row" style="display:none;">
                    <label class="proof-label">
                      📷 Upload proof of delivery
                      <input type="file"
                             name="delivery_proof"
                             class="delivery-proof-file"
                             data-order-id="<?= h($order['id']) ?>"
                             accept="image/png,image/jpeg,image/webp">
                    </label>
                    <div class="proof-preview-wrap" style="display:none;">
                      <img class="proof-preview" src="" alt="Preview">
                    </div>
                  </div>

                  <button type="button"
                          class="btn-sm btn-sm-blue delivery-submit"
                          data-order-id="<?= h($order['id']) ?>">Save update</button>

                  <div class="delivery-message" style="display:none;"></div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($total_pages > 1): ?>
    <div class="pagination mt-20">
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

<style>
  /* ── Dropdown ─────────────────────────────────────── */
  .status-dropdown { position: relative; width: 100%; }

  .status-dropdown .dropdown-toggle {
    width: 100%;
    text-align: left;
  }

  .status-dropdown .dropdown-menu {
    display: none;           /* hidden by default — toggled via JS */
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
    z-index: 50;
    overflow: hidden;
  }

  .status-dropdown .dropdown-menu.is-open { display: block; }

  .status-dropdown .dropdown-option {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 12px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 13px;
    color: #111827;
    transition: background 0.15s;
  }
  .status-dropdown .dropdown-option:hover   { background: #f3f4f6; }
  .status-dropdown .dropdown-option.is-selected { background: #eff6ff; font-weight: 600; }

  /* ── Proof upload ─────────────────────────────────── */
  .proof-row { margin-top: 6px; }

  .proof-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    color: #374151;
    cursor: pointer;
  }

  .proof-label input[type="file"] {
    font-size: 12px;
    color: #6b7280;
  }

  .proof-preview {
    margin-top: 6px;
    max-width: 120px;
    max-height: 90px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    object-fit: cover;
  }

  /* ── Status messages ──────────────────────────────── */
  .delivery-message {
    font-size: 12px;
    margin-top: 4px;
    padding: 4px 0;
  }
</style>

<script>
  const LOCAL_DELIVERY_ENDPOINT = '<?= BASE_URL ?>/pages/rider/update-delivery.php';

  document.addEventListener('DOMContentLoaded', () => {

    /* ── Helpers ──────────────────────────────────────── */

    function msg(el, text, visible = true) {
      el.textContent = text;
      el.style.display = visible ? '' : 'none';
    }

    function getFields(form) {
      return {
        orderId:        form.dataset.orderId || '',
        deliveryStatus: form.querySelector('.delivery-status')?.value  || '',
        orderStatus:    form.querySelector('.order-status')?.value     || '',
        note:           form.querySelector('.delivery-note')?.value.trim() || '',
        proofInput:     form.querySelector('.delivery-proof-file'),
        messageEl:      form.querySelector('.delivery-message'),
        proofRow:       form.querySelector('.proof-row'),
        hasProof:       form.dataset.hasProof === '1',
      };
    }

    /* ── Dropdown logic ───────────────────────────────── */

    function closeAllDropdowns(except = null) {
      document.querySelectorAll('.status-dropdown .dropdown-menu.is-open').forEach(m => {
        if (m !== except) m.classList.remove('is-open');
      });
    }

    document.querySelectorAll('.status-dropdown').forEach(container => {
      const toggle      = container.querySelector('.dropdown-toggle');
      const menu        = container.querySelector('.dropdown-menu');
      const hiddenInput = container.querySelector('input[type="hidden"]');
      if (!toggle || !menu || !hiddenInput) return;

      toggle.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = menu.classList.contains('is-open');
        closeAllDropdowns();
        if (!isOpen) menu.classList.add('is-open');
      });

      // Stop clicks inside the menu from bubbling to document close handler
      menu.addEventListener('click', e => e.stopPropagation());

      container.querySelectorAll('.dropdown-option').forEach(option => {
        option.addEventListener('click', function () {
          const value = this.dataset.value || '';
          hiddenInput.value = value;
          toggle.textContent = this.textContent.trim();

          // Mark selected option
          container.querySelectorAll('.dropdown-option').forEach(o => o.classList.remove('is-selected'));
          this.classList.add('is-selected');

          menu.classList.remove('is-open');

          // Re-evaluate form UI (show/hide proof row, update placeholder)
          const form = container.closest('.delivery-form');
          if (form) refreshFormUI(form);
        });
      });
    });

    document.addEventListener('click', () => closeAllDropdowns());

    /* ── Form UI refresh ──────────────────────────────── */

    function refreshFormUI(form) {
      const { deliveryStatus, proofRow, messageEl, hasProof } = getFields(form);

      // Show proof upload when: status = delivered AND no proof on file yet
      if (deliveryStatus === 'delivered' && !hasProof) {
        proofRow.style.display = '';
      } else {
        proofRow.style.display = 'none';
      }

      // Update placeholder hint
      const noteInput = form.querySelector('.delivery-note');
      if (noteInput) {
        const failStates = ['failed', 'cancelled', 'cannot_find_customer'];
        noteInput.placeholder = failStates.includes(deliveryStatus)
          ? 'Reason required for failed or cancelled delivery'
          : deliveryStatus === 'delivered'
            ? 'Optional note for delivered orders'
            : 'Add a delivery note (optional)';
      }

      // Clear any stale messages on status change
      if (messageEl) msg(messageEl, '', false);
    }

    // Run on page load for each form (in case of pre-selected statuses)
    document.querySelectorAll('.delivery-form').forEach(form => refreshFormUI(form));

    /* ── Proof image preview ──────────────────────────── */

    document.querySelectorAll('.delivery-proof-file').forEach(input => {
      input.addEventListener('change', function () {
        const wrap    = this.closest('.proof-row')?.querySelector('.proof-preview-wrap');
        const preview = this.closest('.proof-row')?.querySelector('.proof-preview');
        if (!wrap || !preview) return;

        if (this.files && this.files[0]) {
          const reader = new FileReader();
          reader.onload = e => {
            preview.src = e.target.result;
            wrap.style.display = '';
          };
          reader.readAsDataURL(this.files[0]);
        } else {
          wrap.style.display = 'none';
          preview.src = '';
        }
      });
    });

    /* ── API call ─────────────────────────────────────── */

    async function postDeliveryUpdate(orderId, deliveryStatus, orderStatus, note, proofFile = null, removeProof = false) {
      const formData = new FormData();
      formData.append('order_id', orderId);
      if (deliveryStatus)       formData.append('delivery_status',    deliveryStatus);
      if (orderStatus)          formData.append('order_status',        orderStatus);
      if (note !== '')          formData.append('delivery_note',       note);
      if (proofFile)            formData.append('delivery_proof',      proofFile);
      if (removeProof)          formData.append('remove_delivery_proof', '1');

      try {
        const response = await fetch(LOCAL_DELIVERY_ENDPOINT, { method: 'POST', body: formData });
        const payload  = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, payload };
      } catch (err) {
        return { ok: false, status: 0, payload: { message: err.message } };
      }
    }

    /* ── Save handler ─────────────────────────────────── */

    async function handleDeliverySave(e) {
      const button = e.currentTarget;
      const form   = button.closest('.delivery-form');
      if (!form) return;

      const { orderId, deliveryStatus, orderStatus, note, proofInput, messageEl, hasProof } = getFields(form);

      // Validation
      if (!deliveryStatus) {
        msg(messageEl, '⚠️ Please select a delivery status.');
        return;
      }

      const failStates = ['failed', 'cancelled', 'cannot_find_customer'];
      if (failStates.includes(deliveryStatus) && note === '') {
        msg(messageEl, '⚠️ Please provide a reason for failed or cancelled delivery.');
        return;
      }

      if (deliveryStatus === 'delivered' && !hasProof &&
          (!proofInput?.files?.length)) {
        msg(messageEl, '⚠️ Please upload a proof of delivery image.');
        return;
      }

      button.disabled = true;
      msg(messageEl, '⏳ Saving…');

      const proofFile = proofInput?.files?.length ? proofInput.files[0] : null;
      const result    = await postDeliveryUpdate(orderId, deliveryStatus, orderStatus, note, proofFile);

      if (!result.ok) {
        msg(messageEl, '❌ ' + (result.payload.message || `Save failed (${result.status})`));
        button.disabled = false;
        return;
      }

      msg(messageEl, '✅ Saved! Refreshing…');
      setTimeout(() => window.location.reload(), 1000);
    }

    document.querySelectorAll('.delivery-submit').forEach(btn => {
      btn.addEventListener('click', handleDeliverySave);
    });

    /* ── Replace proof ────────────────────────────────── */

    document.querySelectorAll('.proof-replace').forEach(button => {
      button.addEventListener('click', function () {
        const orderId = this.dataset.orderId;
        const form    = document.querySelector(`.delivery-form[data-order-id="${orderId}"]`);
        if (!form) return;

        // Treat as "no proof" so the upload row appears
        form.dataset.hasProof = '0';
        refreshFormUI(form);

        // Force-show the proof row (even if delivery status isn't "delivered" yet)
        form.querySelector('.proof-row').style.display = '';

        const messageEl = form.querySelector('.delivery-message');
        msg(messageEl, 'Choose a new proof file, then click Save update.');
      });
    });

    /* ── Delete proof ─────────────────────────────────── */

    document.querySelectorAll('.proof-delete').forEach(button => {
      button.addEventListener('click', async function () {
        const orderId   = this.dataset.orderId;
        const messageEl = document.getElementById(`delivery-msg-${orderId}`);

        if (!confirm('Delete the uploaded proof photo for this order?')) return;

        this.disabled = true;
        if (messageEl) msg(messageEl, '⏳ Deleting proof…');

        const result = await postDeliveryUpdate(orderId, '', '', '', null, true);

        if (!result.ok) {
          if (messageEl) msg(messageEl, '❌ ' + (result.payload.message || 'Failed to delete proof.'));
          this.disabled = false;
          return;
        }

        if (messageEl) msg(messageEl, '✅ Proof deleted. Refreshing…');
        setTimeout(() => window.location.reload(), 1000);
      });
    });

  }); // DOMContentLoaded
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>