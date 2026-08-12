
  const LOCAL_DELIVERY_ENDPOINT = '<?= BASE_URL ?>/pages/rider/update-delivery.php';

  document.addEventListener('DOMContentLoaded', () => {

    /* ── Helpers ──────────────────────────────────────── */

    function msg(el, text, visible = true) {
      el.textContent = text;
      el.style.display = visible ? '' : 'none';
    }

    function getFields(form) {
      return {
        orderId:        form.getAttribute('data-order-id') || '',

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
      // Rider UI should not attempt to update order status (no matching backend route).
      // if (orderStatus)       formData.append('order_status', orderStatus);
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
