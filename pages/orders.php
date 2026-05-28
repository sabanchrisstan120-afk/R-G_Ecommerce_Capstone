<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

/* =========================
   SUBMIT REVIEW
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {

   $payload = [
    'product_id' => trim($_POST['product_id']),
    'rating'     => intval($_POST['rating']),
    'comment'    => trim($_POST['comment'])
];



   
    $review_result = api_request(
        'POST',
        '/reviews',
        $payload,
        true
    );

    if (($review_result['status'] ?? 500) === 201) {

        set_flash('success', 'Review submitted successfully.');

    } else {

        set_flash(
            'error',
            $review_result['body']['message'] ?? 'Failed to submit review.'
        );
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* =========================
   HANDLE CANCEL ORDER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $order_id = trim($_POST['cancel_order_id']);

    $cancel_result = api_request(
        'POST',
        '/orders/' . $order_id . '/cancel',
        [],
        true
    );

    if (($cancel_result['status'] ?? 500) === 200) {
        set_flash('success', 'Order cancelled successfully.');
    } else {
        set_flash('error', $cancel_result['body']['message'] ?? 'Could not cancel order.');
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* =========================
   FETCH ORDERS
========================= */
$status  = $_GET['status'] ?? '';
$page    = max(1, intval($_GET['page'] ?? 1));
$limit   = 10;
$user    = current_user();
$user_id = $user['id'] ?? null;

$endpoint = is_admin() ? '/orders/admin' : '/orders';

$query_params = ['page' => $page, 'limit' => $limit];
if ($status !== '') {
    $query_params['status'] = $status;
}
if (!is_admin() && $user_id) {
    $query_params['user_id'] = $user_id;
}

$params = http_build_query($query_params);
$result = api_request('GET', $endpoint . '?' . $params, [], true);



$orders     = $result['body']['data']['orders'] ?? $result['body']['orders'] ?? [];
$pagination = $result['body']['data']['pagination'] ?? $result['body']['pagination'] ?? [];
$total_pages = $pagination['total_pages'] ?? ceil(($pagination['total'] ?? 0) / $limit);

/* =========================
   PAGE
========================= */
$page_title = 'My Orders — ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>My Orders</h1>
  <p>Track and manage your aircon orders</p>
</div>

<!-- Status Filter -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
  <?php
  $statuses = [
    ''           => 'All',
    'pending'    => 'Pending',
    'confirmed'  => 'Confirmed',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
  ];
  foreach ($statuses as $val => $label):
  ?>
    <a href="?status=<?= urlencode($val) ?>"
       style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
              background:<?= $status === $val ? '#1a365d' : '#fff' ?>;
              color:<?= $status === $val ? '#fff' : '#4a5568' ?>;
              border:1px solid <?= $status === $val ? '#1a365d' : '#e2e8f0' ?>;">
      <?= h($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($orders)): ?>
  <div class="empty-state">
    <div class="icon">📦</div>
    <p>No orders found. <a href="/rg-trading-php/index.php" style="color:#3182ce;">Browse products</a></p>
  </div>
<?php else: ?>
  <div class="orders-table">
    <table>
      <thead>
        <tr>
          <th>Order #</th>
          <th>Date</th>
          <th>Total</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><strong><?= h($order['order_number'] ?? '-') ?></strong></td>

            <td>
              <?= !empty($order['ordered_at'])
                ? date('M d, Y', strtotime($order['ordered_at']))
                : '-' ?>
            </td>

            <td>
              <strong><?= format_price($order['total_amount'] ?? 0) ?></strong>
            </td>

            <td>
              <span class="badge badge-<?= strtolower(h($order['status'] ?? 'pending')) ?>">
                <?= h(ucfirst($order['status'] ?? 'Pending')) ?>
              </span>
            </td>

            <td>
              <span class="badge badge-<?= strtolower(h($order['payment_status'] ?? 'pending')) ?>">
                <?= h(ucfirst($order['payment_status'] ?? 'Pending')) ?>
              </span>
            </td>

           <td style="display:flex;gap:6px;align-items:center;">

  <!-- VIEW -->
  <a href="/rg-trading-php/pages/order-detail.php?id=<?= h($order['id']) ?>">
    <button class="btn-sm btn-sm-blue">View</button>
  </a>

  <!-- CANCEL -->
  <?php if (in_array(strtolower($order['status'] ?? ''), ['pending', 'confirmed'])): ?>
    <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this order?')">
      <input type="hidden" name="cancel_order_id" value="<?= h($order['id']) ?>">
      <button type="submit" class="btn-sm btn-sm-red">Cancel</button>
    </form>
  <?php endif; ?>

  <!-- ⭐ REVIEW BUTTON (ONLY DELIVERED) -->
  <?php if (strtolower($order['status'] ?? '') === 'delivered'): ?>
    <button type="button"
            class="btn-sm btn-sm-green"
          onclick="openReviewModal('<?= h(trim($order['items'][0]['product_id'] ?? '')) ?>')">
      ⭐ Review
    </button>
  <?php endif; ?>

</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="margin-top:20px;">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
            <?= $i ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<div id="reviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;">

  <div style="background:#fff;width:350px;margin:100px auto;padding:20px;border-radius:10px;">

    <h3>Write Review</h3>

    <form method="POST">

      <input type="hidden" name="submit_review" value="1">

      <!-- IMPORTANT: product_id will be filled by JS -->
      <input type="hidden" name="product_id" id="review_product_id">

      <label>Rating</label>
      <select name="rating" required style="width:100%;padding:8px;">
        <option value="5">⭐⭐⭐⭐⭐</option>
        <option value="4">⭐⭐⭐⭐</option>
        <option value="3">⭐⭐⭐</option>
        <option value="2">⭐⭐</option>
        <option value="1">⭐</option>
      </select>

      <br><br>

      <label>Comment</label>
      <textarea name="comment" required style="width:100%;padding:8px;"></textarea>

      <br><br>

      <button type="submit">Submit Review</button>
      <button type="button" onclick="closeReviewModal()">Close</button>

    </form>

  </div>

</div>
<script>
function openReviewModal(productId) {
    document.getElementById('review_product_id').value = productId;
    document.getElementById('reviewModal').style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>