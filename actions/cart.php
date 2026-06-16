<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$product_id = $_GET['product_id'] ?? $_POST['product_id'] ?? '';

// Validate product id
if (empty($product_id)) {
  set_flash('error', 'No product selected.');
  header('Location: ' . BASE_URL . '/index.php');
  exit;
}

$qty        = max(1, intval($_GET['qty'] ?? 1));
$error      = '';

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
          header('Location: ' . BASE_URL . '/pages/orders.php');
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

<div class="container-sm">
  <div class="page-header">
    <h1>Place Order</h1>
    <p><a href="<?= BASE_URL ?>/index.php" class="btn-link">← Back to Products</a></p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error flash-inline"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- Product Summary -->
  <div class="card">
    <div class="muted-meta"><?= h($product['brand']) ?></div>
    <div class="product-title"><?= h($product['name']) ?></div>
    <div class="model-text">Model: <?= h($product['model_number']) ?></div>
    <div class="row-between">
      <span class="price-large"><?= format_price($product['price']) ?></span>
      <span class="stock-small">In stock: <?= $product['stock_qty'] ?> units</span>
    </div>
  </div>

  <!-- Order Form -->
  <div class="card-lg">
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
      <div class="mb-18">
        <label class="label-strong">Delivery Address</label>

        <?php if ($saved_address): ?>
          <!-- Saved address option -->
          <div class="address-box">
            <label class="address-label">
              <input type="checkbox" name="use_saved_address" id="use_saved_address"
                     <?= $use_saved_checked ? 'checked' : '' ?>
                     class="mt-3"
                     onchange="toggleAddressFields(this)">
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
        <label>Notes <span class="small-muted">(optional)</span></label>
        <textarea name="notes" rows="3" placeholder="Special instructions for delivery..." class="form-textarea"><?= h($_POST['notes'] ?? '') ?></textarea>
      </div>

      <!-- Order Summary -->
      <div class="summary-card">
        <div class="summary-row"><span class="muted-meta">Subtotal</span><span id="subtotal"><?= format_price($product['price']) ?></span></div>
        <div class="summary-row"><span class="muted-meta">Shipping</span><span class="text-success"><?= $product['price'] >= 10000 ? 'FREE' : '₱500.00' ?></span></div>
        <div class="summary-row total"><span>Total</span><span id="total"><?= format_price($product['price'] >= 10000 ? $product['price'] : $product['price'] + 500) ?></span></div>
      </div>

      <button type="submit" class="btn-primary">Place Order</button>
    </form>
  </div>
</div>

<script>
function toggleAddressFields(checkbox) {
    document.getElementById('address-fields').style.display = checkbox.checked ? 'none' : 'block';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>