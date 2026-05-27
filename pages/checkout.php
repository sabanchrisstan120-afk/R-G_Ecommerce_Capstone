<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$product_id = $_GET['product_id'] ?? '';
$qty        = max(1, intval($_GET['qty'] ?? 1));
$error      = '';

// Fetch product details
$result  = api_request('GET', '/products/' . urlencode($product_id));
$product = $result['body']['data']['product'] ?? null;

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: /rg-trading-php/index.php');
    exit;
}

// Fetch saved address from user profile
$profile_result  = api_request('GET', '/profile', [], true);
$saved_address   = $profile_result['body']['data']['user']['address'] ?? null;
// Expected shape: ['street' => '', 'city' => '', 'province' => '', 'zip' => '']

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty            = max(1, intval($_POST['quantity'] ?? 1));
    $payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';
    $notes          = trim($_POST['notes'] ?? '');
    $use_saved      = isset($_POST['use_saved_address']) && $saved_address;

    if ($use_saved) {
        $address = $saved_address;
    } else {
        $address = [
            'street'   => trim($_POST['street'] ?? ''),
            'city'     => trim($_POST['city'] ?? ''),
            'province' => trim($_POST['province'] ?? ''),
            'zip'      => trim($_POST['zip'] ?? ''),
        ];
    }

    // Basic address validation
    if (empty($address['street']) || empty($address['city']) || empty($address['province']) || empty($address['zip'])) {
        $error = 'Please complete all address fields.';
    } else {
        $payload = [
            'items'          => [['product_id' => $product_id, 'quantity' => $qty]],
            'payment_method' => $payment_method,
            'address'        => $address,
        ];
        if (!empty($notes)) $payload['notes'] = $notes;

        $order_result = api_request('POST', '/orders', $payload, true);

        if ($order_result['status'] === 201) {
            $order_num = $order_result['body']['data']['order']['order_number'] ?? 'N/A';
            set_flash('success', "Order #{$order_num} placed successfully!");
            header('Location: /rg-trading-php/pages/orders.php');
            exit;
        } else {
            $error = $order_result['body']['message'] ?? 'Failed to place order. Please try again.';
        }
    }
}

// Determine what to pre-fill in the manual fields
$use_saved_checked = !isset($_POST['use_saved_address']) && $saved_address; // default: use saved if available
$post_street   = h($_POST['street']   ?? '');
$post_city     = h($_POST['city']     ?? '');
$post_province = h($_POST['province'] ?? '');
$post_zip      = h($_POST['zip']      ?? '');

$page_title = 'Order — ' . h($product['name']);
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:560px; margin:0 auto;">
  <div class="page-header">
    <h1>Place Order</h1>
    <p><a href="/rg-trading-php/index.php" style="color:#3182ce;">← Back to Products</a></p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error" style="border-radius:8px; margin-bottom:16px;"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- Product Summary -->
  <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.07);margin-bottom:24px;">
    <div style="font-size:11px;font-weight:700;color:#3182ce;text-transform:uppercase;letter-spacing:.05em;"><?= h($product['brand']) ?></div>
    <div style="font-size:17px;font-weight:700;color:#1a202c;margin:4px 0;"><?= h($product['name']) ?></div>
    <div style="font-size:12px;color:#a0aec0;margin-bottom:12px;">Model: <?= h($product['model_number']) ?></div>
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:22px;font-weight:700;color:#1a365d;"><?= format_price($product['price']) ?></span>
      <span style="font-size:12px;color:#38a169;">In stock: <?= $product['stock_qty'] ?> units</span>
    </div>
  </div>

  <!-- Order Form -->
  <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.07);">
    <form method="POST">
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
      <div style="margin-bottom:18px;">
        <label style="display:block;font-weight:600;margin-bottom:10px;">Delivery Address</label>

        <?php if ($saved_address): ?>
          <!-- Saved address option -->
          <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:12px;">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:400;">
              <input type="checkbox" name="use_saved_address" id="use_saved_address"
                     <?= $use_saved_checked ? 'checked' : '' ?>
                     style="margin-top:3px;"
                     onchange="toggleAddressFields(this)">
              <div>
                <div style="font-size:13px;font-weight:600;color:#1a202c;margin-bottom:2px;">Use saved address</div>
                <div style="font-size:13px;color:#4a5568;">
                  <?= h($saved_address['street']) ?><br>
                  <?= h($saved_address['city']) ?>, <?= h($saved_address['province']) ?> <?= h($saved_address['zip']) ?>
                </div>
              </div>
            </label>
          </div>
        <?php endif; ?>

        <!-- Manual address fields -->
        <div id="address-fields" style="<?= ($use_saved_checked) ? 'display:none;' : '' ?>">
          <div class="form-group" style="margin-bottom:12px;">
            <label style="font-weight:400;font-size:13px;">Street / House No.</label>
            <input type="text" name="street" value="<?= $post_street ?>"
                   placeholder="e.g. 123 Rizal St., Brgy. San Jose"
                   style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;box-sizing:border-box;">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group" style="margin-bottom:0;">
              <label style="font-weight:400;font-size:13px;">City / Municipality</label>
              <input type="text" name="city" value="<?= $post_city ?>"
                     placeholder="e.g. Iloilo City"
                     style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label style="font-weight:400;font-size:13px;">Province</label>
              <input type="text" name="province" value="<?= $post_province ?>"
                     placeholder="e.g. Iloilo"
                     style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;box-sizing:border-box;">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;margin-bottom:0;">
            <label style="font-weight:400;font-size:13px;">ZIP Code</label>
            <input type="text" name="zip" value="<?= $post_zip ?>"
                   placeholder="e.g. 5000" maxlength="10"
                   style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;box-sizing:border-box;">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Notes <span style="color:#a0aec0;font-weight:400">(optional)</span></label>
        <textarea name="notes" rows="3" placeholder="Special instructions for delivery..." style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;resize:vertical;"><?= h($_POST['notes'] ?? '') ?></textarea>
      </div>

      <!-- Order Summary -->
      <div style="background:#f7fafc;border-radius:8px;padding:14px;margin-bottom:18px;font-size:13px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#718096;">Subtotal</span>
          <span id="subtotal"><?= format_price($product['price']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#718096;">Shipping</span>
          <span style="color:#38a169;"><?= $product['price'] >= 10000 ? 'FREE' : '₱500.00' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid #e2e8f0;padding-top:8px;margin-top:6px;">
          <span>Total</span>
          <span style="color:#1a365d;font-size:16px;" id="total">
            <?= format_price($product['price'] >= 10000 ? $product['price'] : $product['price'] + 500) ?>
          </span>
        </div>
      </div>

      <button type="submit" class="btn-primary">Confirm Order</button>
    </form>
  </div>
</div>

<script>
function toggleAddressFields(checkbox) {
    document.getElementById('address-fields').style.display = checkbox.checked ? 'none' : 'block';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>