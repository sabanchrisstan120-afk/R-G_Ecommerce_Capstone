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
$orders = apply_order_state_overrides($orders);
$orders = enrich_orders_with_details($orders);

$store = get_order_state_store();
foreach ($store as $order_id => $updates) {
    $order_status = strtolower((string)($updates['status'] ?? ''));
    $rider_id = (string)($updates['rider_id'] ?? '');
    $current_user_id = (string)($_SESSION['user']['id'] ?? '');

    $should_show_for_rider = $order_status === 'assigned_to_rider'
        && ($rider_id === '' || $current_user_id === '' || $rider_id === $current_user_id);

    if ($should_show_for_rider) {
        $exists = false;
        foreach ($orders as $existing_order) {
            if ((string)($existing_order['id'] ?? '') === (string) $order_id) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $orders[] = [
                'id' => $order_id,
                'order_number' => $order_id,
                'customer_name' => 'Customer',
                'email' => '',
                'phone' => '',
                'street' => '',
                'city' => '',
                'province' => '',
                'zip' => '',
                'notes' => '',
                'total_amount' => 0,
                'status' => $order_status,
                'delivery_status' => 'pending',
                'delivery_proof_url' => '',
                'payment_status' => 'pending',
                'expected_delivery_date' => '',
                'delivery_note' => '',
            ];
        }
    }
}

$pagination = $result['body']['pagination'] ?? $result['body']['data']['pagination'] ?? ['total' => 0];
$total_pages = ceil(($pagination['total'] ?? 0) / $limit);

$page_title = 'Rider Orders — ' . APP_NAME;
$page_css = '/assets/css/rider-orders.css';
include __DIR__ . '/../../includes/header.php';
?>

<div id="rider-orders-page" class="page">
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
                <?= h(get_order_customer_name($order) ?: 'Customer') ?><br>
                <span class="muted-small"><?= h(get_order_customer_email($order)) ?></span>
              </td>
              <td class="text-small font-600">
                <?php $phone = get_order_phone($order); ?>
                <?php if ($phone !== ''): ?>
                  <a href="tel:<?= h($phone) ?>" class="btn-link">
                    <?= h($phone) ?>
                  </a>
                <?php else: ?>
                  <span class="muted-small">No phone</span>
                <?php endif; ?>
              </td>
              <td class="text-small min-w-180">
                <?php $address = format_order_address($order); ?>
                <?php if ($address !== '—'): ?>
                  <?= h($address) ?>
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
                    <label for="proof-upload-<?= h($order['id']) ?>"
                           class="btn-sm btn-sm-green proof-upload text-small"
                           data-order-id="<?= h($order['id']) ?>">Upload proof</label>
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
                  <div class="display-grid gap-6">
                    <label for="proof-upload-<?= h($order['id']) ?>"
                           class="btn-sm btn-sm-green proof-upload text-small"
                           data-order-id="<?= h($order['id']) ?>">Upload proof</label>
                    <span class="badge badge-pending">No proof</span>
                  </div>
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
                      <?php foreach (delivery_status_options() as $ds): ?>
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
                      <?php foreach (order_status_options() as $os): ?>
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

                  <select name="payment_status" class="form-select payment-status">
                    <option value="">Payment status…</option>
                    <?php foreach (['paid','pending','failed','refunded'] as $ps): ?>
                      <option value="<?= $ps ?>" <?= ($order['payment_status'] ?? '')  === $ps ? 'selected' : '' ?>>
                        <?= ucfirst($ps) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <input type="date"
                         name="expected_delivery_date"
                         class="form-input expected-delivery-date"
                         value="<?= !empty($order['expected_delivery_date']) ? date('Y-m-d', strtotime($order['expected_delivery_date'])) : '' ?>"
                         placeholder="Expected delivery date">

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
                      <input id="proof-upload-<?= h($order['id']) ?>"
                             type="file"
                             name="delivery_proof"
                             class="delivery-proof-file"
                             data-order-id="<?= h($order['id']) ?>"
                             accept="image/png,image/jpeg,image/webp">
                    </label>
                    <div class="proof-preview-wrap" style="display:none;">
                      <img class="proof-preview" src="" alt="Preview">
                    </div>
                  </div>

                  <?php if (strtolower($order['status'] ?? '') === 'assigned_to_rider'): ?>
                    <button type="button"
                            class="btn-sm btn-sm-green rider-action"
                            data-order-id="<?= h($order['id']) ?>"
                            data-next-status="accepted_by_rider">Accept delivery</button>
                    <button type="button"
                            class="btn-sm btn-sm-red rider-action"
                            data-order-id="<?= h($order['id']) ?>"
                            data-next-status="waiting_for_new_rider">Decline delivery</button>
                  <?php elseif (strtolower($order['status'] ?? '') === 'accepted_by_rider'): ?>
                    <button type="button"
                            class="btn-sm btn-sm-blue rider-action"
                            data-order-id="<?= h($order['id']) ?>"
                            data-next-status="out_for_delivery">Mark out for delivery</button>
                  <?php endif; ?>

                  <button type="button"
                          class="btn-sm btn-sm-blue delivery-submit"
                          data-order-id="<?= h($order['id']) ?>">Save update</button>

                  <div class="delivery-message" id="delivery-msg-<?= h($order['id']) ?>" style="display:none;"></div>
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

<script>
  const LOCAL_DELIVERY_ENDPOINT = '<?= BASE_URL ?>/pages/rider/update-delivery.php';

  document.addEventListener('DOMContentLoaded', () => {

    /* ── Helpers ──────────────────────────────────────── */

    function msg(el, text, visible = true) {
      el.textContent = text;
      el.style.display = visible ? '' : 'none';
    }

    function getFields(form) {
      var deliveryStatusEl = form.querySelector('.delivery-status');
      var orderStatusEl    = form.querySelector('.order-status');
      var paymentStatusEl  = form.querySelector('.payment-status');
      var expectedDateEl   = form.querySelector('.expected-delivery-date');
      var noteEl           = form.querySelector('.delivery-note');
      return {
        orderId:               form.getAttribute('data-order-id') || '',
        deliveryStatus:        deliveryStatusEl ? deliveryStatusEl.value : '',
        orderStatus:           orderStatusEl ? orderStatusEl.value : '',
        paymentStatus:         paymentStatusEl ? paymentStatusEl.value : '',
        expectedDeliveryDate:  expectedDateEl ? expectedDateEl.value : '',
        note:                  noteEl ? noteEl.value.trim() : '',
        proofInput:            form.querySelector('.delivery-proof-file'),
        proofRow:              form.querySelector('.proof-row'),
        hasProof:              form.dataset.hasProof === '1',
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

    function ensureMessageEl(form) {
      var messageEl = form.querySelector('.delivery-message');
      if (!messageEl) {
        messageEl = document.createElement('div');
        messageEl.className = 'delivery-message';
        messageEl.style.display = 'none';
        form.appendChild(messageEl);
      }
      return messageEl;
    }

    function refreshFormUI(form) {
      var fields = getFields(form);
      var deliveryStatus = fields.deliveryStatus;
      var proofRow = fields.proofRow;
      var hasProof = fields.hasProof;
      var messageEl = ensureMessageEl(form);

      if (proofRow) {
        if (deliveryStatus === 'delivered' && !hasProof) {
          proofRow.style.display = '';
        } else {
          proofRow.style.display = 'none';
        }
      }

      var noteInput = form.querySelector('.delivery-note');
      if (noteInput) {
        var failStates = ['failed', 'cancelled', 'cannot_find_customer'];
        noteInput.placeholder = failStates.indexOf(deliveryStatus) !== -1
          ? 'Reason required for failed or cancelled delivery'
          : deliveryStatus === 'delivered'
            ? 'Optional note for delivered orders'
            : 'Add a delivery note (optional)';
      }

      msg(messageEl, '', false);
    }

    document.querySelectorAll('.delivery-form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
      });
      refreshFormUI(form);
    });

    /* ── Proof image preview ──────────────────────────── */

    document.querySelectorAll('.delivery-proof-file').forEach(function (input) {
      input.addEventListener('change', function () {
        var proofRow = this.closest('.proof-row');
        var wrap = proofRow ? proofRow.querySelector('.proof-preview-wrap') : null;
        var preview = proofRow ? proofRow.querySelector('.proof-preview') : null;
        if (!wrap || !preview) return;

        if (this.files && this.files.length && this.files[0]) {
          var reader = new FileReader();
          reader.onload = function (e) {
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

    async function postDeliveryUpdate(orderId, deliveryStatus, orderStatus, note, paymentStatus = '', expectedDeliveryDate = '', proofFile = null, removeProof = false) {
      const formData = new FormData();
      formData.append('order_id', orderId);
      if (deliveryStatus)          formData.append('delivery_status',    deliveryStatus);
      if (orderStatus)             formData.append('order_status',       orderStatus);
      if (paymentStatus)           formData.append('payment_status',     paymentStatus);
      if (expectedDeliveryDate)    formData.append('expected_delivery_date', expectedDeliveryDate);
      if (note !== '')             formData.append('delivery_note',       note);
      if (proofFile)               formData.append('delivery_proof',      proofFile);
      if (removeProof)             formData.append('remove_delivery_proof', '1');


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

      var fields = getFields(form);
      var orderId = fields.orderId;
      var deliveryStatus = fields.deliveryStatus;
      var orderStatus = fields.orderStatus;
      var paymentStatus = fields.paymentStatus;
      var expectedDeliveryDate = fields.expectedDeliveryDate;
      var note = fields.note;
      var proofInput = fields.proofInput;
      var hasProof = fields.hasProof;
      var messageEl = ensureMessageEl(form);
      // orderStatus is ignored in the rider flow because the backend only supports delivery updates for riders.

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
          !(proofInput && proofInput.files && proofInput.files.length)) {
        msg(messageEl, '⚠️ Please upload a proof of delivery image.');
        return;
      }

      button.disabled = true;
      msg(messageEl, '⏳ Saving…');

      var proofFile = (proofInput && proofInput.files && proofInput.files.length) ? proofInput.files[0] : null;
      var result = await postDeliveryUpdate(orderId, deliveryStatus, orderStatus, note, paymentStatus, expectedDeliveryDate, proofFile);

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

    document.querySelectorAll('.rider-action').forEach(btn => {
      btn.addEventListener('click', async function () {
        const orderId = this.dataset.orderId;
        const nextStatus = this.dataset.nextStatus || '';
        const form = document.querySelector(`.delivery-form[data-order-id="${orderId}"]`);
        if (!form || !nextStatus) return;

        const orderStatusEl = form.querySelector('.order-status');
        if (orderStatusEl) orderStatusEl.value = nextStatus;

        const button = form.querySelector('.delivery-submit');
        if (button) {
          const event = new Event('click', { bubbles: true });
          button.dispatchEvent(event);
        }
      });
    });

    function showProofUploadForOrder(orderId, message) {
      var form = document.querySelector('.delivery-form[data-order-id="' + orderId + '"]');
      if (!form) return;

      form.dataset.hasProof = '0';
      refreshFormUI(form);

      var proofRow = form.querySelector('.proof-row');
      if (proofRow) {
        proofRow.style.display = '';
      }

      var messageEl = form.querySelector('.delivery-message');
      msg(messageEl, message || 'Choose a proof file, then click Save update.');
    }

    /* ── Replace proof ────────────────────────────────── */

    document.querySelectorAll('.proof-replace').forEach(button => {
      button.addEventListener('click', function () {
        showProofUploadForOrder(this.dataset.orderId, 'Choose a new proof file, then click Save update.');
      });
    });

    document.querySelectorAll('.proof-upload').forEach(button => {
      button.addEventListener('click', function () {
        showProofUploadForOrder(this.dataset.orderId, 'Choose a proof file, then click Save update.');
      });
    });

    /* ── Delete proof ─────────────────────────────────── */

    document.querySelectorAll('.proof-delete').forEach(button => {
      button.addEventListener('click', async function () {
        var orderId   = this.dataset.orderId;
        var form = document.querySelector('.delivery-form[data-order-id="' + orderId + '"]');
        var messageEl = form ? ensureMessageEl(form) : null;

        if (!confirm('Delete the uploaded proof photo for this order?')) return;

        this.disabled = true;
        if (messageEl) msg(messageEl, '⏳ Deleting proof…');

        const result = await postDeliveryUpdate(orderId, '', '', '', '', '', null, true);

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