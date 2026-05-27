<?php
require_once __DIR__ . '/includes/config.php';

$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = $_GET['sort']          ?? 'created_at';
$page     = max(1, intval($_GET['page'] ?? 1));
$limit    = 12;

$params = http_build_query(array_filter([
    'search' => $search, 'category' => $category,
    'sort' => $sort, 'page' => $page, 'limit' => $limit,
]));

$result      = api_request('GET', '/products?' . $params);
$products    = $result['body']['data']['products']   ?? [];
$pagination  = $result['body']['data']['pagination'] ?? [];
$total       = $pagination['total'] ?? 0;
$total_pages = ceil($total / $limit);

$cat_result = api_request('GET', '/products/categories');
$categories = $cat_result['body']['data']['categories'] ?? [];

$page_title = 'Shop — ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<?php if (!$search && !$category): ?>
<div class="hero">
  <div class="hero-inner">
    <h1>Stay Cool with R&G Trading</h1>
    <p>Premium air conditioner units for every home and business in Iloilo</p>
    <div class="hero-search">
      <form method="GET" style="display:contents;">
        <input type="text" name="search" placeholder="Search by brand, model, or type..." value="<?= h($search) ?>">
        <button type="submit">Search</button>
      </form>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><strong><?= count($categories) ?>+</strong><span>Categories</span></div>
      <div class="hero-stat"><strong><?= $total ?>+</strong><span>Products</span></div>
      <div class="hero-stat"><strong>Free</strong><span>Shipping ₱10k+</span></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="main-content">

  <?php if (!$search && !$category): ?>
  <div class="category-pills">
    <a href="/rg-trading-php/" class="cat-pill active">All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="?category=<?= h($cat['slug']) ?>" class="cat-pill"><?= h($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($search || $category): ?>
  <div class="page-header">
    <h1>
      <?php if ($search): ?>Results for "<?= h($search) ?>"
      <?php elseif ($category): ?>
        <?php foreach ($categories as $c) { if ($c['slug'] === $category) echo h($c['name']); } ?>
      <?php endif; ?>
    </h1>
    <p><?= $total ?> product<?= $total !== 1 ? 's' : '' ?> found
      — <a href="/rg-trading-php/" style="color:#3182ce;">Clear filters</a>
    </p>
  </div>
  <?php endif; ?>

  <form method="GET" class="filter-bar">
    <?php if ($category): ?><input type="hidden" name="category" value="<?= h($category) ?>"><?php endif; ?>
    <input type="text" name="search" placeholder="Search..." value="<?= h($search) ?>" style="min-width:220px;">
    <select name="category">
      <option value="">All Categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= h($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort">
      <option value="created_at" <?= $sort==='created_at'?'selected':'' ?>>Newest</option>
      <option value="price"      <?= $sort==='price'?'selected':'' ?>>Price: Low to High</option>
      <option value="name"       <?= $sort==='name'?'selected':'' ?>>Name A–Z</option>
    </select>
    <button type="submit">Filter</button>
  </form>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="icon">❄️</div>
      <p>No products found. Try adjusting your search.</p>
    </div>
  <?php else: ?>
    <?php if (!$search && !$category): ?><h2 class="section-title">All Products</h2><?php endif; ?>
    <div class="products-grid">
      <?php foreach ($products as $p): ?>
        <div class="product-card">
          <a href="/rg-trading-php/pages/product-detail.php?id=<?= h($p['id']) ?>">
            <div class="product-img-wrap">
              <?php if (!empty($p['image_url'])): ?>
                <img src="<?= h($p['image_url']) ?>" alt="<?= h($p['name']) ?>">
              <?php else: ?>
                <div class="product-img-placeholder">❄️</div>
              <?php endif; ?>
              <?php if ($p['stock_qty'] <= 0): ?>
                <span class="product-badge">Out of Stock</span>
              <?php elseif ($p['stock_qty'] <= 5): ?>
                <span class="product-badge">Low Stock</span>
              <?php endif; ?>
            </div>
          </a>
          <div class="product-body">
            <div class="product-brand"><?= h($p['brand']) ?></div>
            <a href="/rg-trading-php/pages/product-detail.php?id=<?= h($p['id']) ?>">
              <div class="product-name"><?= h($p['name']) ?></div>
            </a>
            <div class="product-model">Model: <?= h($p['model_number']) ?></div>
            <div class="product-specs">
              <?php if ($p['horsepower']): ?><span class="spec-tag"><?= h($p['horsepower']) ?>HP</span><?php endif; ?>
              <?php if ($p['energy_rating']): ?><span class="spec-tag"><?= h($p['energy_rating']) ?></span><?php endif; ?>
              <?php if ($p['category']): ?><span class="spec-tag"><?= h($p['category']) ?></span><?php endif; ?>
            </div>
            <div class="product-footer">
              <div>
                <div class="product-price"><?= format_price($p['price']) ?></div>
                <?php if ($p['stock_qty'] <= 5 && $p['stock_qty'] > 0): ?>
                  <span class="stock-low">Only <?= $p['stock_qty'] ?> left!</span>
                <?php elseif ($p['stock_qty'] <= 0): ?>
                  <span class="stock-low">Out of stock</span>
                <?php else: ?>
                  <span class="stock-ok">In stock</span>
                <?php endif; ?>
              </div>
              <?php if (is_logged_in() && $p['stock_qty'] > 0): ?>
                <a href="/rg-trading-php/pages/checkout.php?product_id=<?= h($p['id']) ?>">
                  <button class="btn-add-cart">Order Now</button>
                </a>
              <?php elseif (!is_logged_in()): ?>
                <a href="/rg-trading-php/login.php">
                  <button class="btn-view">Login to Order</button>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="active"><?= $i ?></span>
          <?php else: ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
