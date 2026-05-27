<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id     = $_GET['id'] ?? '';
$result = api_request('GET', '/orders/' . urlencode($id), [], true);
$order  = $result['body']['data']['order'] ?? null;

if (!$order) {
    set_flash('error', 'Order not found.');
    header('Location: /rg-trading-php/pages/orders.php'); exit;
}

// Order status steps
$steps = ['pending','confirmed','processing','shipped','delivered'];
$current_status = $order['status'];
$is_cancelled   = $current_status === 'cancelled';

function step_state(string $step, string $current, bool $cancelled): string {
    if ($cancelled) return $step === $current ? 'cancelled' : 'done';
    $steps = ['pending','confirmed','processing','shipped','delivered'];
    $cur_i = array_search($current, $steps);
    $step_i = array_search($step, $steps);
    if ($step_i < $cur_i)  return 'done';
    if ($step_i === $cur_i) return 'active';
    return '';
}

$page_title = 'Order ' . $order['order_number'] . ' — ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="main-content" style="max-width:780px;">
  <div class="page-header">
    <h1>Order #<?= h($order['order_number']) ?></h1>
    <p><a href="/rg-trading-php/pages/orders.php" style="color:#3182ce;">← Back to My Orders</a></p>
  </div>

  <!-- Status Timeline -->
  <?php if (!$is_cancelled): ?>
  <div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
      <div class="status-timeline">
        <?php
        $icons = ['pending'=>'🕐','confirmed'=>'✅','processing'=>'⚙️','shipped'=>'🚚','delivered'=>'🏠'];
        foreach ($steps as $s):
          $state = step_state($s, $current_status, $is_cancelled);
        ?>
          <div class="timeline-step <?= $state ?>">
            <div class="timeline-dot"><?= $icons[$s] ?></div>
            <div class="timeline-label"><?= ucfirst($s) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div style="background:#fff5f5;border:1px solid #fed7d7;border-radius:12px;padding:16px 20px;margin-bottom:20px;color:#9b2c2c;font-weight:600;">
    ✕ This order was cancelled.
  </div>
  <?php endif; ?>

  <!-- Summary Row -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
    <?php $info = [
      'Order Status'   => ucfirst($order['status']),
      'Payment'        => ucfirst($order['payment_status']),
      'Payment Method' => ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')),
      'Date Ordered'   => date('M d, Y', strtotime($order['ordered_at'])),
    ]; foreach ($info as $label => $val): ?>
      <div style="background:#fff;border-radius:10px;padding:14px 16px;box-shadow:0 1px 6px rgba(0,0,0,.07);">
        <div style="font-size:11px;color:#718096;font-weight:600;text-transform:uppercase;margin-bottom:5px;"><?= h($label) ?></div>
        <div style="font-size:14px;font-weight:700;color:#1a202c;"><?= h($val) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Items -->
  <div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header"><h3>Items Ordered</h3></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Product</th>
          <th style="text-align:center;">Qty</th>
          <th style="text-align:right;">Unit Price</th>
          <th style="text-align:right;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($order['items'] ?? [] as $item): ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= h($item['product_name']) ?></div>
              <div style="font-size:11px;color:#a0aec0;">Model: <?= h($item['model_number']) ?></div>
            </td>
            <td style="text-align:center;"><?= $item['quantity'] ?></td>
            <td style="text-align:right;"><?= format_price($item['unit_price']) ?></td>
            <td style="text-align:right;font-weight:700;"><?= format_price($item['total_price']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="padding:16px 20px;border-top:1px solid #edf2f7;background:#f7fafc;">
      <div class="summary-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
      <div class="summary-row"><span>Shipping</span><span><?= $order['shipping_fee'] > 0 ? format_price($order['shipping_fee']) : 'FREE' ?></span></div>
      <div class="summary-row total"><span>Total Amount</span><span><?= format_price($order['total_amount']) ?></span></div>
    </div>
  </div>

  <!-- Cancel button -->
  <?php if (in_array($order['status'], ['pending','confirmed'])): ?>
    <form method="POST" action="/rg-trading-php/pages/orders.php"
          onsubmit="return confirm('Cancel this order?')">
      <input type="hidden" name="cancel_order_id" value="<?= h($order['id']) ?>">
      <button type="submit" class="btn-sm btn-sm-red" style="font-size:13px;padding:9px 18px;">Cancel Order</button>
    </form>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
