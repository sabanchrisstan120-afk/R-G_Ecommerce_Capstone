<?php
require_once __DIR__ . '/../includes/config.php';
// SUBMIT REVIEW
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {

    require_login();

  $payload = [
    'product_id' => trim($_POST['product_id']),
    'rating'     => intval($_POST['rating']),
    'comment'    => trim($_POST['comment'])
];
     
   $res = api_request(
    'POST',
    '/reviews',
    $payload,
    true
);




    if ($res['status'] === 201) {
        set_flash('success', 'Review submitted successfully.');
    } else {
        set_flash('error', $res['body']['message'] ?? 'Failed to submit review.');
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$id = trim($_GET['id'] ?? '');
if (!$id) { header('Location: /rg-trading-php/index.php'); exit; }

$res     = api_request('GET', '/products/' . urlencode($id));
$product = $res['body']['data']['product'] ?? null;
$review_res = api_request(
    'GET',
    '/reviews/product/' . $id
);

$reviews = $review_res['body']['reviews'] ?? [];
/* COMPUTE AVERAGE */
$total_rating = 0;

foreach ($reviews as $r) {
    $total_rating += intval($r['rating'] ?? 0);
}

$average_rating = count($reviews)
    ? round($total_rating / count($reviews), 1)
    : 0;

    // PRODUCT RECOMMENDATIONS — same category, similar price (±30%)
$rec_res = api_request('GET', '/products?category=' . urlencode($product['category_slug']) . '&limit=4');
$all_rec = $rec_res['body']['data']['products'] ?? [];

$product_price = floatval($product['price']);
$recommendations = array_filter($all_rec, function($p) use ($product, $product_price) {
    if ($p['id'] === $product['id']) return false; // exclude current product
    $p_price = floatval($p['price']);
    $lower   = $product_price * 0.70;
    $upper   = $product_price * 1.30;
    return $p_price >= $lower && $p_price <= $upper;
});
$recommendations = array_slice($recommendations, 0, 4);

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: /rg-trading-php/index.php'); exit;
}

$page_title = h($product['name']) . ' — ' . APP_NAME;
include __DIR__ . '/../includes/header.php';

/* ── IMAGE LOGIC (unchanged) ── */
$images = $product['image_urls'] ?? [];
if (is_string($images)) {
    $decoded = json_decode($images, true);
    $images  = is_array($decoded) ? $decoded : [];
}
if (empty($images) && !empty($product['image_url'])) {
    $images = [$product['image_url']];
}
?>

<!-- ═══════════════════════════════════════════════════════════════
     GALLERY STYLES
════════════════════════════════════════════════════════════════ -->
<style>
/* ── Reset & tokens ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --accent:       #ee4d2d;      /* Shopee orange-red */
  --accent-light: #fff3f0;
  --border:       #e8e8e8;
  --text-main:    #212121;
  --text-muted:   #757575;
  --radius:       8px;
  --thumb-size:   72px;
  --gap:          10px;
  --trans:        0.22s ease;
}

/* ── Page shell ── */
.pd-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px 16px 60px;
}

/* breadcrumb */
.pd-crumb {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 20px;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
}
.pd-crumb a { color: #1a94ff; text-decoration: none; }
.pd-crumb a:hover { text-decoration: underline; }
.pd-crumb span { color: #bdbdbd; }

/* ── Main card grid ── */
.pd-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 16px rgba(0,0,0,.07);
  display: grid;
  grid-template-columns: 520px 1fr;
  gap: 32px;
  padding: 28px;
}

/* ════════════════════════════════
   GALLERY COLUMN
════════════════════════════════ */
.pd-gallery {
  display: flex;
  flex-direction: row;
  gap: 12px;
  user-select: none;
}

/* ── Thumbnail strip (left sidebar) ── */
.pd-thumbs {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: var(--thumb-size);
  flex-shrink: 0;
}

.pd-thumb-viewport {
  overflow: hidden;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pd-thumb {
  width: var(--thumb-size);
  height: var(--thumb-size);
  border-radius: 6px;
  border: 2px solid var(--border);
  overflow: hidden;
  cursor: pointer;
  flex-shrink: 0;
  transition: border-color var(--trans), transform var(--trans), box-shadow var(--trans);
  background: #f5f5f5;
}
.pd-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform var(--trans);
}
.pd-thumb:hover {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(238,77,45,.12);
  transform: translateY(-1px);
}
.pd-thumb:hover img { transform: scale(1.06); }
.pd-thumb.active {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(238,77,45,.18);
}

/* thumb scroll arrows */
.pd-thumb-arrow {
  width: var(--thumb-size);
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
  border: 1px solid var(--border);
  border-radius: 4px;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 13px;
  color: var(--text-muted);
  transition: background var(--trans), color var(--trans);
}
.pd-thumb-arrow:hover { background: #eee; color: var(--text-main); }
.pd-thumb-arrow.hidden { visibility: hidden; pointer-events: none; }

/* ── Main image area ── */
.pd-main-img-wrap {
  flex: 1;
  position: relative;
  overflow: hidden;
  border-radius: 10px;
  background: #f7f7f7;
  aspect-ratio: 1 / 1;
  max-height: 440px;
}

/* zoom lens overlay */
.pd-main-img-wrap::after {
  content: '🔍';
  position: absolute;
  bottom: 12px;
  right: 12px;
  font-size: 18px;
  background: rgba(255,255,255,.85);
  backdrop-filter: blur(4px);
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity var(--trans);
  pointer-events: none;
}
.pd-main-img-wrap:hover::after { opacity: 1; }

.pd-main-slides {
  width: 100%;
  height: 100%;
  position: relative;
}

.pd-slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 0.32s ease, transform 0.32s ease;
  transform: scale(1.012);
}
.pd-slide.active {
  opacity: 1;
  transform: scale(1);
  z-index: 1;
}
.pd-slide img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
  transition: transform 0.4s ease;
}
.pd-main-img-wrap:hover .pd-slide.active img {
  transform: scale(1.04);
}

/* Prev / Next arrows on main image */
.pd-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: rgba(255,255,255,.92);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(0,0,0,.18);
  font-size: 18px;
  color: var(--text-main);
  opacity: 0;
  transition: opacity var(--trans), background var(--trans), transform var(--trans);
}
.pd-arrow.prev { left: 10px; }
.pd-arrow.next { right: 10px; }
.pd-main-img-wrap:hover .pd-arrow { opacity: 1; }
.pd-arrow:hover { background: #fff; transform: translateY(-50%) scale(1.1); }
.pd-arrow:active { transform: translateY(-50%) scale(.97); }

/* dot indicators */
.pd-dots {
  position: absolute;
  bottom: 10px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 5px;
  z-index: 10;
}
.pd-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(255,255,255,.55);
  border: 1px solid rgba(0,0,0,.18);
  transition: background var(--trans), transform var(--trans);
  cursor: pointer;
}
.pd-dot.active {
  background: var(--accent);
  border-color: var(--accent);
  transform: scale(1.4);
}

/* No image fallback */
.pd-no-img {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 72px;
  color: #ccc;
}

/* ════════════════════════════════
   INFO COLUMN
════════════════════════════════ */
.pd-info { display: flex; flex-direction: column; gap: 14px; }

.pd-brand {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--accent);
  background: var(--accent-light);
  border: 1px solid #fad4cb;
  border-radius: 4px;
  padding: 3px 8px;
}

.pd-name {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-main);
  line-height: 1.35;
}

.pd-model {
  font-size: 12px;
  color: var(--text-muted);
  background: #f5f5f5;
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
}

.pd-price-row {
  background: #fafafa;
  border-radius: 8px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.pd-price {
  font-size: 30px;
  font-weight: 800;
  color: var(--accent);
  letter-spacing: -.5px;
}

.pd-stock-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 20px;
}
.stock-in   { background: #e6f4ea; color: #1e7e34; }
.stock-low  { background: #fff3e0; color: #e65100; }
.stock-out  { background: #fce8e8; color: #c62828; }

.pd-desc {
  font-size: 14px;
  color: #555;
  line-height: 1.75;
  border-left: 3px solid var(--border);
  padding-left: 12px;
}

/* specs table */
.pd-specs {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.pd-specs tr { border-bottom: 1px solid #f0f0f0; }
.pd-specs tr:last-child { border-bottom: none; }
.pd-specs td {
  padding: 9px 8px;
  vertical-align: top;
}
.pd-specs td:first-child {
  color: var(--text-muted);
  width: 40%;
  font-weight: 500;
}
.pd-specs td:last-child { color: var(--text-main); font-weight: 600; }

/* CTA buttons */
.pd-cta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 4px; }

.btn-order {
  flex: 1;
  min-width: 140px;
  padding: 14px 20px;
  font-size: 15px;
  font-weight: 700;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  background: var(--accent);
  color: #fff;
  letter-spacing: .02em;
  transition: background var(--trans), transform var(--trans), box-shadow var(--trans);
  box-shadow: 0 4px 14px rgba(238,77,45,.35);
}
.btn-order:hover {
  background: #d93f20;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(238,77,45,.45);
}
.btn-order:active { transform: translateY(0); }
.btn-order.secondary {
  background: #fff;
  color: var(--accent);
  border: 2px solid var(--accent);
  box-shadow: none;
}
.btn-order.secondary:hover { background: var(--accent-light); }
.btn-order:disabled {
  background: #c0c0c0;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

/* ════════════════════════════════
   RESPONSIVE
════════════════════════════════ */
@media (max-width: 900px) {
  .pd-card {
    grid-template-columns: 1fr;
    padding: 16px;
    gap: 20px;
  }
  .pd-gallery { flex-direction: column-reverse; }
  .pd-thumbs {
    flex-direction: row;
    width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .pd-thumbs::-webkit-scrollbar { display: none; }
  .pd-thumb-viewport { flex-direction: row; flex: unset; width: 100%; }
  .pd-thumb-arrow { width: 26px; height: var(--thumb-size); writing-mode: horizontal-tb; }
  .pd-main-img-wrap { max-height: 320px; }
  .pd-arrow { opacity: 1; }
  .pd-name { font-size: 18px; }
  .pd-price { font-size: 24px; }
}

@media (max-width: 480px) {
  --thumb-size: 58px;
  .pd-main-img-wrap { max-height: 260px; }
}
</style>

<!-- ═══════════════════════════════════════════════════════════════
     PAGE MARKUP
════════════════════════════════════════════════════════════════ -->
<div class="pd-wrap">

  <!-- Breadcrumb -->
  <nav class="pd-crumb">
    <a href="/rg-trading-php/index.php">Products</a>
    <?php if ($product['category']): ?>
      <span>›</span>
      <a href="/rg-trading-php/index.php?category=<?= h($product['category_slug']) ?>"><?= h($product['category']) ?></a>
    <?php endif; ?>
    <span>›</span>
    <span><?= h($product['name']) ?></span>
  </nav>

  <!-- Card -->
  <div class="pd-card">

    <!-- ══ GALLERY ══ -->
    <div class="pd-gallery" id="pdGallery">

      <?php if (!empty($images)): ?>

        <!-- Thumbnail strip -->
        <div class="pd-thumbs" id="pdThumbs">
          <button class="pd-thumb-arrow hidden" id="thumbPrev" onclick="scrollThumbs(-1)" aria-label="Previous thumbnails">▲</button>
          <div class="pd-thumb-viewport" id="thumbViewport">
            <?php foreach ($images as $i => $img): ?>
              <div class="pd-thumb <?= $i === 0 ? 'active' : '' ?>"
                   data-index="<?= $i ?>"
                   onclick="goToSlide(<?= $i ?>)"
                   role="button"
                   aria-label="Product image <?= $i + 1 ?>">
                <img src="<?= h($img) ?>" alt="<?= h($product['name']) ?> thumbnail <?= $i + 1 ?>" loading="lazy">
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (count($images) > 1): ?>
            <button class="pd-thumb-arrow" id="thumbNext" onclick="scrollThumbs(1)" aria-label="Next thumbnails">▼</button>
          <?php endif; ?>
        </div>

        <!-- Main image -->
        <div class="pd-main-img-wrap" id="pdMainWrap">
          <div class="pd-main-slides">
            <?php foreach ($images as $i => $img): ?>
              <div class="pd-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
                <img src="<?= h($img) ?>" alt="<?= h($product['name']) ?> image <?= $i + 1 ?>">
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($images) > 1): ?>
            <button class="pd-arrow prev" onclick="changeSlide(-1)" aria-label="Previous image">&#8249;</button>
            <button class="pd-arrow next" onclick="changeSlide(1)"  aria-label="Next image">&#8250;</button>
            <div class="pd-dots" id="pdDots">
              <?php foreach ($images as $i => $_): ?>
                <div class="pd-dot <?= $i === 0 ? 'active' : '' ?>"
                     data-index="<?= $i ?>"
                     onclick="goToSlide(<?= $i ?>)"
                     role="button"
                     aria-label="Image <?= $i + 1 ?>"></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <div class="pd-main-img-wrap"><div class="pd-no-img">❄️</div></div>
      <?php endif; ?>

    </div><!-- /.pd-gallery -->


    <!-- ══ INFO ══ -->
    <div class="pd-info">

      <div>
        <span class="pd-brand"><?= h($product['brand']) ?></span>
      </div>

      <h1 class="pd-name"><?= h($product['name']) ?></h1>
      <span class="pd-model">Model No: <?= h($product['model_number']) ?></span>

      <!-- Price + stock -->
      <div class="pd-price-row">
        <span class="pd-price"><?= format_price($product['price']) ?></span>
        <?php
          $qty = (int)$product['stock_qty'];
          if ($qty <= 0):
        ?>
          <span class="pd-stock-badge stock-out">● Out of Stock</span>
        <?php elseif ($qty <= 5): ?>
          <span class="pd-stock-badge stock-low">● Only <?= $qty ?> left!</span>
        <?php else: ?>
          <span class="pd-stock-badge stock-in">● In Stock (<?= $qty ?>)</span>
        <?php endif; ?>
      </div>
      <!-- Ratings -->
<div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">

  <div style="font-size:18px;color:#f6ad55;font-weight:700;">
    ⭐ <?= number_format($average_rating, 1) ?>
  </div>

  <div style="font-size:14px;color:#718096;">
    <?= count($reviews) ?> Reviews
  </div>

</div>

<!-- Review List -->
<div style="margin-top:20px;">
<?php

$review_res = api_request(
    'GET',
    '/reviews/product/' . $id
);

$reviews = $review_res['body']['reviews'] ?? [];

?>
  <h3 style="margin-bottom:15px;font-size:18px;">
    Customer Reviews
  </h3>

  <?php if (empty($reviews)): ?>

    <div style="
      padding:20px;
      background:#f7fafc;
      border-radius:10px;
      color:#718096;
    ">
      No reviews yet.
    </div>

 <?php else: ?>

  <?php foreach ($reviews as $review): ?>

    <div style="
      padding:18px;
      border:1px solid #e2e8f0;
      border-radius:10px;
      margin-bottom:15px;
      background:#fff;
    ">

      <div style="
        display:flex;
        justify-content:space-between;
        margin-bottom:8px;
      ">

        <strong>
          <?= h(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? ''))) ?>
        </strong>

        <span style="color:#f6ad55;">
          <?= str_repeat('⭐', intval($review['rating'] ?? 0)) ?>
        </span>

      </div>

      <div style="
        color:#4a5568;
        line-height:1.6;
        font-size:14px;
      ">
        <?= h($review['comment'] ?? '') ?>
      </div>

    </div>

  <?php endforeach; ?>

<?php endif; ?>

</div>
      <!-- Description -->
      <?php if (!empty($product['description'])): ?>
        <p class="pd-desc"><?= h($product['description']) ?></p>
      <?php endif; ?>

      <!-- Specs -->
      <table class="pd-specs">
        <?php if ($product['horsepower']): ?>
          <tr><td>Horsepower</td><td><?= h($product['horsepower']) ?> HP</td></tr>
        <?php endif; ?>
        <?php if ($product['cooling_capacity_btu']): ?>
          <tr><td>Cooling Capacity</td><td><?= number_format($product['cooling_capacity_btu']) ?> BTU</td></tr>
        <?php endif; ?>
        <?php if ($product['energy_rating']): ?>
          <tr><td>Energy Rating</td><td><?= h($product['energy_rating']) ?></td></tr>
        <?php endif; ?>
        <?php if ($product['category']): ?>
          <tr><td>Category</td><td><?= h($product['category']) ?></td></tr>
        <?php endif; ?>
        <tr><td>Shipping</td><td><?= $product['price'] >= 10000 ? '🚚 FREE Shipping' : '₱500 flat rate' ?></td></tr>
      </table>

      <!-- CTA -->
      <div class="pd-cta">
        <?php if (is_logged_in() && $qty > 0): ?>
          <a href="/rg-trading-php/pages/checkout.php?product_id=<?= h($product['id']) ?>" style="flex:1;display:block;">
            <button class="btn-order" style="width:100%;">Order Now</button>
          </a>
        <?php elseif (!is_logged_in()): ?>
          <a href="/rg-trading-php/login.php" style="flex:1;display:block;">
            <button class="btn-order secondary" style="width:100%;">Login to Order</button>
          </a>
        <?php else: ?>
          <button class="btn-order" disabled style="width:100%;">Out of Stock</button>
        <?php endif; ?>
      </div>
    <!-- ADD REVIEW -->
<?php
$can_review = false;

if (is_logged_in()) {

    $orders_res = api_request(
        'GET',
        '/orders/my-orders',
        [],
        true
    );

    $orders = $orders_res['body']['data']['orders'] ?? [];

    foreach ($orders as $o) {

        foreach (($o['items'] ?? []) as $item) {

            if (($item['product_id'] ?? '') == $product['id']) {
                $can_review = true;
                break 2;
            }
        }
    }
}
?>

<?php if ($can_review): ?>

<div style="
  margin-top:30px;
  padding:25px;
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:12px;
">

  <h3 style="margin-bottom:18px;">
    Write a Review
  </h3>

  <form method="POST">

    <input type="hidden"
           name="submit_review"
           value="1">

    <input type="hidden"
           name="product_id"
           value="<?= h($product['id']) ?>">

    <!-- Rating -->
    <div style="margin-bottom:15px;">

      <label style="
        display:block;
        margin-bottom:8px;
        font-weight:600;
      ">
        Rating
      </label>

      <select name="rating"
              required
              style="
                width:100%;
                padding:10px;
                border:1px solid #ddd;
                border-radius:8px;
              ">

        <option value="">Select Rating</option>
        <option value="5">⭐⭐⭐⭐⭐ (5)</option>
        <option value="4">⭐⭐⭐⭐ (4)</option>
        <option value="3">⭐⭐⭐ (3)</option>
        <option value="2">⭐⭐ (2)</option>
        <option value="1">⭐ (1)</option>

      </select>

    </div>

    <!-- Comment -->
    <div style="margin-bottom:15px;">

      <label style="
        display:block;
        margin-bottom:8px;
        font-weight:600;
      ">
        Comment
      </label>

      <textarea
        name="comment"
        required
        rows="4"
        placeholder="Write your review..."
        style="
          width:100%;
          padding:12px;
          border:1px solid #ddd;
          border-radius:8px;
          resize:vertical;
        "
      ></textarea>

    </div>
<button type="submit"
        id="reviewBtn"
        style="
          background:#ee4d2d;
          color:#fff;
          border:none;
          padding:12px 20px;
          border-radius:8px;
          cursor:pointer;
          font-weight:600;
        ">
  Submit Review
</button>
  </form>

</div>

<?php endif; ?>
    </div><!-- /.pd-info -->

  </div><!-- /.pd-card -->
</div><!-- /.pd-wrap -->


<!-- ═══════════════════════════════════════════════════════════════
     GALLERY JAVASCRIPT
════════════════════════════════════════════════════════════════ -->
<script>
(function () {
  'use strict';

  /* ── State ── */
  var current      = 0;
  var thumbOffset  = 0;   // how many thumbs scrolled past
  var VISIBLE      = 5;   // max visible thumbs at once
  var slides       = Array.from(document.querySelectorAll('.pd-slide'));
  var thumbs       = Array.from(document.querySelectorAll('.pd-thumb'));
  var dots         = Array.from(document.querySelectorAll('.pd-dot'));
  var total        = slides.length;
  var thumbPrevBtn = document.getElementById('thumbPrev');
  var thumbNextBtn = document.getElementById('thumbNext');

  if (total <= 1) return; // single image — nothing to wire up

  /* ── Core: go to slide N ── */
  window.goToSlide = function (n) {
    slides[current].classList.remove('active');
    thumbs[current].classList.remove('active');
    if (dots[current]) dots[current].classList.remove('active');

    current = (n + total) % total;

    slides[current].classList.add('active');
    thumbs[current].classList.add('active');
    if (dots[current]) dots[current].classList.add('active');

    ensureThumbVisible(current);
  };

  /* ── Arrow navigation ── */
  window.changeSlide = function (dir) {
    goToSlide(current + dir);
  };

  /* ── Thumb strip scrolling ── */
  window.scrollThumbs = function (dir) {
    thumbOffset = Math.max(0, Math.min(total - VISIBLE, thumbOffset + dir));
    applyThumbScroll();
    updateThumbArrows();
  };

  function applyThumbScroll () {
    var thumbSize = 72 + 8; // thumb height + gap
    var viewport  = document.getElementById('thumbViewport');
    if (viewport) {
      viewport.scrollTop = thumbOffset * thumbSize;
    }
  }

  function ensureThumbVisible (idx) {
    if (idx < thumbOffset) {
      thumbOffset = idx;
      applyThumbScroll();
    } else if (idx >= thumbOffset + VISIBLE) {
      thumbOffset = idx - VISIBLE + 1;
      applyThumbScroll();
    }
    updateThumbArrows();
  }

  function updateThumbArrows () {
    if (!thumbPrevBtn || !thumbNextBtn) return;
    thumbPrevBtn.classList.toggle('hidden', thumbOffset <= 0);
    thumbNextBtn.classList.toggle('hidden', thumbOffset >= total - VISIBLE);
  }

  /* ── Touch / swipe on main image ── */
  var wrap = document.getElementById('pdMainWrap');
  if (wrap) {
    var touchStartX = 0;
    wrap.addEventListener('touchstart', function (e) {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    wrap.addEventListener('touchend', function (e) {
      var diff = touchStartX - e.changedTouches[0].screenX;
      if (Math.abs(diff) > 40) changeSlide(diff > 0 ? 1 : -1);
    }, { passive: true });
  }

  /* ── Keyboard navigation ── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft')  changeSlide(-1);
    if (e.key === 'ArrowRight') changeSlide(1);
  });

  /* ── Init ── */
  updateThumbArrows();
  if (total <= VISIBLE && thumbNextBtn) thumbNextBtn.classList.add('hidden');

  /* ── Viewport: switch thumbs to horizontal on mobile ── */
  function checkLayout () {
    var viewport = document.getElementById('thumbViewport');
    if (!viewport) return;
    if (window.innerWidth <= 900) {
      viewport.scrollTop  = 0;
      viewport.scrollLeft = thumbOffset * (58 + 8);
    } else {
      applyThumbScroll();
    }
  }
  window.addEventListener('resize', checkLayout);

}());
</script>


<script>
document.querySelector('form').addEventListener('submit', function () {
    const btn = document.getElementById('reviewBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerText = "Submitting...";
    }
});
</script>



<!-- ═══════════════════════════════════════════════════════════════
     PRODUCT RECOMMENDATIONS
════════════════════════════════════════════════════════════════ -->
<?php if (!empty($recommendations)): ?>
<div class="pd-wrap" style="padding-top:0;">
  <div style="
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 16px rgba(0,0,0,.07);
    padding:28px;
    margin-top:24px;
  ">
    <h2 style="
      font-size:18px;
      font-weight:700;
      color:#212121;
      margin-bottom:20px;
      padding-bottom:12px;
      border-bottom:2px solid #ee4d2d;
      display:inline-block;
    ">You Might Also Like</h2>

    <div style="
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
      gap:16px;
    ">
      <?php foreach ($recommendations as $rec):
        $rec_images = $rec['image_urls'] ?? [];
        if (is_string($rec_images)) $rec_images = json_decode($rec_images, true) ?? [];
        if (empty($rec_images) && !empty($rec['image_url'])) $rec_images = [$rec['image_url']];
        $rec_img = $rec_images[0] ?? null;
        $rec_qty = (int)($rec['stock_qty'] ?? 0);
      ?>
      <a href="/rg-trading-php/pages/product-detail.php?id=<?= h($rec['id']) ?>"
         style="text-decoration:none;color:inherit;">
        <div style="
          border:1px solid #e8e8e8;
          border-radius:10px;
          overflow:hidden;
          transition:box-shadow .22s ease, transform .22s ease;
          background:#fff;
          cursor:pointer;
        "
        onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)';this.style.transform='translateY(-3px)'"
        onmouseout="this.style.boxShadow='none';this.style.transform='translateY(0)'">

          <!-- Image -->
          <div style="
            width:100%;
            aspect-ratio:1/1;
            background:#f7f7f7;
            overflow:hidden;
            display:flex;
            align-items:center;
            justify-content:center;
          ">
            <?php if ($rec_img): ?>
              <img src="<?= h($rec_img) ?>"
                   alt="<?= h($rec['name']) ?>"
                   style="width:100%;height:100%;object-fit:cover;transition:transform .3s ease;"
                   onmouseover="this.style.transform='scale(1.06)'"
                   onmouseout="this.style.transform='scale(1)'">
            <?php else: ?>
              <span style="font-size:40px;color:#ccc;">❄️</span>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div style="padding:12px;">
            <div style="
              font-size:10px;
              font-weight:700;
              color:#ee4d2d;
              text-transform:uppercase;
              letter-spacing:.06em;
              margin-bottom:4px;
            "><?= h($rec['brand']) ?></div>

            <div style="
              font-size:13px;
              font-weight:600;
              color:#212121;
              line-height:1.4;
              margin-bottom:8px;
              display:-webkit-box;
              -webkit-line-clamp:2;
              -webkit-box-orient:vertical;
              overflow:hidden;
            "><?= h($rec['name']) ?></div>

            <div style="
              font-size:16px;
              font-weight:800;
              color:#ee4d2d;
              margin-bottom:6px;
            "><?= format_price($rec['price']) ?></div>

            <?php if ($rec_qty <= 0): ?>
              <span style="font-size:11px;color:#c62828;font-weight:600;">● Out of Stock</span>
            <?php elseif ($rec_qty <= 5): ?>
              <span style="font-size:11px;color:#e65100;font-weight:600;">● Only <?= $rec_qty ?> left</span>
            <?php else: ?>
              <span style="font-size:11px;color:#1e7e34;font-weight:600;">● In Stock</span>
            <?php endif; ?>
          </div>

        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>