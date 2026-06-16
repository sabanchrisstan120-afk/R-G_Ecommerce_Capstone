<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$product_id = $_GET['product_id'] ?? '';
$qty        = max(1, intval($_GET['qty'] ?? 1));
$error      = '';
$step       = 'form'; // 'form' or 'confirm'

// Fetch product details
$result  = api_request('GET', '/products/' . urlencode($product_id));
$product = $result['body']['data']['product'] ?? null;

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch saved address from user profile
$profile_result  = api_request('GET', '/profile', [], true);
$saved_address   = $profile_result['body']['data']['user']['address'] ?? null;

// Variables to hold form data
$form_data = [
    'quantity'          => $qty,
    'payment_method'    => 'cash_on_delivery',
    'notes'             => '',
    'phone'             => '',
    'use_saved_address' => false,
    'street'            => '',
    'city'              => '',
    'province'          => '',
    'zip'               => '',
    'address'           => null,
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['quantity']           = max(1, intval($_POST['quantity'] ?? 1));
    $form_data['payment_method']     = $_POST['payment_method'] ?? 'cash_on_delivery';
    $form_data['notes']              = trim($_POST['notes'] ?? '');
    $form_data['phone']              = trim($_POST['phone'] ?? '');
    $form_data['use_saved_address']  = isset($_POST['use_saved_address']) && $saved_address;
    
    // Get address (from saved or manual)
    if ($form_data['use_saved_address']) {
        $form_data['address'] = $saved_address;
    } else {
        $form_data['street']    = trim($_POST['street'] ?? '');
        $form_data['city']      = trim($_POST['city'] ?? '');
        $form_data['province']  = trim($_POST['province'] ?? '');
        $form_data['zip']       = trim($_POST['zip'] ?? '');
        
        $form_data['address'] = [
            'street'   => $form_data['street'],
            'city'     => $form_data['city'],
            'province' => $form_data['province'],
            'zip'      => $form_data['zip'],
        ];
    }

    // Check if this is final confirmation step
    if (isset($_POST['step']) && $_POST['step'] === 'confirm') {
        // Validate address
        if (empty($form_data['address']['street']) || empty($form_data['address']['city']) || 
            empty($form_data['address']['province']) || empty($form_data['address']['zip'])) {
            $error = 'Please complete all address fields.';
        } else {
            // Validate phone
            if (empty($form_data['phone'])) {
                $error = 'Please provide a phone number for delivery contact.';
            } else {
                // Submit order to API
                $payload = [
                    'items'          => [['product_id' => $product_id, 'quantity' => $form_data['quantity']]],
                    'payment_method' => $form_data['payment_method'],
                    'address'        => $form_data['address'],
                    'phone'          => $form_data['phone'],
                ];
                if (!empty($form_data['notes'])) $payload['notes'] = $form_data['notes'];

                $order_result = api_request('POST', '/orders', $payload, true);

                if ($order_result['status'] === 201) {
                    $order_num = $order_result['body']['data']['order']['order_number'] ?? 'N/A';
                    set_flash('success', "Order #{$order_num} placed successfully!");
                    header('Location: ' . BASE_URL . '/pages/orders.php');
                    exit;
                } else {
                    $error = $order_result['body']['message'] ?? 'Failed to place order. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === 'form') {
        // Validate and show confirmation page
        if (empty($form_data['address']['street']) || empty($form_data['address']['city']) || 
            empty($form_data['address']['province']) || empty($form_data['address']['zip'])) {
            $error = 'Please complete all address fields.';
        } else {
            $step = 'confirm';
        }
    }
}

// Pre-fill form fields for display
$use_saved_checked = !isset($_POST['use_saved_address']) && $saved_address;
$post_street   = h($_POST['street']   ?? '');
$post_city     = h($_POST['city']     ?? '');
$post_province = h($_POST['province'] ?? '');
$post_zip      = h($_POST['zip']      ?? '');
$post_phone    = h($_POST['phone']    ?? '');

$page_title = 'Order — ' . h($product['name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="container-sm">
  <div class="page-header">
    <h1><?= $step === 'form' ? 'Place Order' : 'Review Order' ?></h1>
    <p><a href="<?= BASE_URL ?>/index.php" class="btn-link">← Back to Products</a></p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error flash-inline"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- Product Summary (always visible) -->
  <div class="checkout-card">
    <div class="muted-meta"><?= h($product['brand']) ?></div>
    <div class="product-title"><?= h($product['name']) ?></div>
    <div class="model-text">Model: <?= h($product['model_number']) ?></div>
    <div class="row-between">
      <span class="price-large"><?= format_price($product['price']) ?></span>
      <span class="stock-small">In stock: <?= $product['stock_qty'] ?> units</span>
    </div>
  </div>

  <?php if ($step === 'form'): ?>
    <!-- Order Form - Step 1 -->
    <div class="card-panel">
      <form method="POST">
        <input type="hidden" name="step" value="form">
        
        <div class="form-group">
          <label>Quantity</label>
          <input type="number" name="quantity" value="<?= $qty ?>" min="1" max="<?= $product['stock_qty'] ?>" required>
        </div>

        <div class="form-group">
          <label>Payment Method</label>
          <select name="payment_method">
            <option value="cash_on_delivery">Cash on Delivery</option>
            <option value="gcash">GCash</option>
            <option value="maya">Maya</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="credit_card">Credit Card</option>
          </select>
        </div>

        <!-- Delivery Address -->
        <div class="mb-18">
          <label class="form-label-strong">Delivery Address</label>

          <?php if ($saved_address): ?>
            <!-- Saved address option -->
            <div class="address-box">
              <label class="address-label">
                <input type="checkbox" name="use_saved_address" id="use_saved_address" <?= $use_saved_checked ? 'checked' : '' ?> class="mt-3" onchange="toggleAddressFields(this)">
                <div>
                  <div class="address-strong">Use saved address</div>
                  <div class="small-muted">
                    <?= h($saved_address['street']) ?><br>
                    <?= h($saved_address['city']) ?>, <?= h($saved_address['province']) ?> <?= h($saved_address['zip']) ?>
                  </div>
                </div>
              </label>
            </div>
          <?php endif; ?>

          <!-- Manual address fields -->
          <div id="address-fields" class="<?= ($use_saved_checked) ? 'hidden' : '' ?>">
            <div class="form-group">
              <label class="form-label">Street / House No.</label>
              <input type="text" name="street" value="<?= $post_street ?>" placeholder="e.g. 123 Rizal St., Brgy. San Jose">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">City / Municipality</label>
                <input type="text" name="city" value="<?= $post_city ?>" placeholder="e.g. Iloilo City">
              </div>
              <div class="form-group">
                <label class="form-label">Province</label>
                <input type="text" name="province" value="<?= $post_province ?>" placeholder="e.g. Iloilo">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">ZIP Code</label>
              <input type="text" name="zip" value="<?= $post_zip ?>" placeholder="e.g. 5000" maxlength="10">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Phone Number <span class="text-danger">*</span></label>
          <input type="tel" name="phone" value="<?= $post_phone ?>" placeholder="e.g. 09123456789" pattern="[0-9+\-\s()]*" required>
          <div class="small-muted mt-6">We'll share this with the delivery rider so they can contact you.</div>
        </div>

        <div class="form-group">
          <label>Notes <span class="small-muted">(optional)</span></label>
          <textarea name="notes" rows="3" placeholder="Special instructions for delivery..." class="form-textarea"><?= h($_POST['notes'] ?? '') ?></textarea>
        </div>

        <!-- Order Summary -->
        <div class="summary-card">
          <div class="summary-row"><span class="muted-meta">Quantity</span><span><?= $qty ?> unit<?= $qty !== 1 ? 's' : '' ?></span></div>
          <div class="summary-row"><span class="muted-meta">Subtotal</span><span id="subtotal"><?= format_price($product['price'] * $qty) ?></span></div>
          <div class="summary-row"><span class="muted-meta">Shipping</span><span class="text-success"><?= $product['price'] >= 10000 ? 'FREE' : '₱500.00' ?></span></div>
          <div class="summary-row total"><span>Total</span><span id="total"><?= format_price(($product['price'] * $qty) + ($product['price'] >= 10000 ? 0 : 500)) ?></span></div>
        </div>

        <button type="submit" class="btn-primary">Review Order</button>
      </form>
    </div>

  <?php else: ?>
    <!-- Order Confirmation - Step 2 -->
    <div class="card-panel">
      <h2 class="text-large text-ice-strong mb-24">Order Summary</h2>
      
      <!-- Quantity & Product -->
      <div class="confirm-section">
        <h3 class="confirm-heading">Order Details</h3>
        <div class="confirm-row">
          <span class="confirm-label">Product:</span>
          <span class="confirm-value"><?= h($product['name']) ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">Model:</span>
          <span class="confirm-value"><?= h($product['model_number']) ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">Quantity:</span>
          <span class="confirm-value"><?= $form_data['quantity'] ?> unit<?= $form_data['quantity'] !== 1 ? 's' : '' ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">Unit Price:</span>
          <span class="confirm-value"><?= format_price($product['price']) ?></span>
        </div>
      </div>

      <!-- Delivery Address -->
      <div class="confirm-section">
        <h3 class="confirm-heading">Delivery Address</h3>
        <div class="confirm-row">
          <span class="confirm-label">Street:</span>
          <span class="confirm-value"><?= h($form_data['address']['street']) ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">City/Municipality:</span>
          <span class="confirm-value"><?= h($form_data['address']['city']) ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">Province:</span>
          <span class="confirm-value"><?= h($form_data['address']['province']) ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">ZIP Code:</span>
          <span class="confirm-value"><?= h($form_data['address']['zip']) ?></span>
        </div>
        <div class="confirm-row">
          <span class="confirm-label">Phone Number:</span>
          <span class="confirm-value"><?= h($form_data['phone']) ?></span>
        </div>
      </div>

      <!-- Payment & Notes -->
      <div class="confirm-section">
        <h3 class="confirm-heading">Payment & Notes</h3>
        <div class="confirm-row">
          <span class="confirm-label">Payment Method:</span>
          <span class="confirm-value"><?= ucwords(str_replace('_', ' ', $form_data['payment_method'])) ?></span>
        </div>
        <?php if (!empty($form_data['notes'])): ?>
        <div class="confirm-row">
          <span class="confirm-label">Special Instructions:</span>
          <span class="confirm-value"><?= h($form_data['notes']) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Price Breakdown -->
      <div class="summary-card">
        <div class="summary-row"><span class="muted-meta">Subtotal</span><span><?= format_price($product['price'] * $form_data['quantity']) ?></span></div>
          <div class="summary-row"><span class="muted-meta">Shipping</span><span class="text-success"><?= $product['price'] >= 10000 ? 'FREE' : '₱500.00' ?></span></div>

      <!-- Hidden form fields to preserve data -->
      <form method="POST" id="confirmForm">
        <input type="hidden" name="step" value="confirm">
        <input type="hidden" name="quantity" value="<?= $form_data['quantity'] ?>">
        <input type="hidden" name="payment_method" value="<?= h($form_data['payment_method']) ?>">
        <input type="hidden" name="notes" value="<?= h($form_data['notes']) ?>">
        <input type="hidden" name="phone" value="<?= h($form_data['phone']) ?>">
        <?php if ($form_data['use_saved_address']): ?>
          <input type="hidden" name="use_saved_address" value="1">
        <?php else: ?>
          <input type="hidden" name="street" value="<?= h($form_data['address']['street']) ?>">
          <input type="hidden" name="city" value="<?= h($form_data['address']['city']) ?>">
          <input type="hidden" name="province" value="<?= h($form_data['address']['province']) ?>">
          <input type="hidden" name="zip" value="<?= h($form_data['address']['zip']) ?>">
        <?php endif; ?>

        <div class="d-flex gap-12 mt-20">
          <button type="button" class="btn-secondary" onclick="history.back()">← Edit Details</button>
          <button type="submit" class="btn-primary flex-1">Place Order</button>
        </div>
      </form>
    </div>

  <?php endif; ?>
</div>

<script>
function toggleAddressFields(checkbox) {
    document.getElementById('address-fields').style.display = checkbox.checked ? 'none' : 'block';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>