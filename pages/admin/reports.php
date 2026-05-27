<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

$period = intval($_GET['period'] ?? 30);

$summary  = api_request('GET', "/admin/dashboard/summary?period={$period}", [], true);
$revenue  = api_request('GET', "/admin/dashboard/revenue-trends?granularity=day&months=1", [], true);
$topProds = api_request('GET', "/admin/dashboard/top-products", [], true);
$seasonal = api_request('GET', "/admin/dashboard/seasonal-demand", [], true);
$peaks    = api_request('GET', "/admin/dashboard/peak-periods", [], true);
$repeats  = api_request('GET', "/admin/dashboard/repeat-customers", [], true);

$s         = $summary['body']['data']  ?? [];
$rev_data  = $revenue['body']['data']['trends']  ?? [];
$top_prods = $topProds['body']['data']['products'] ?? [];
$season    = $seasonal['body']['data']['months']   ?? [];
$peak_hrs  = $peaks['body']['data']['hours']       ?? [];
$repeat    = $repeats['body']['data']['customers'] ?? [];

$page_title = 'Reports — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<style>
.chart-wrap{position:relative;height:260px;margin-top:10px;}
</style>

<div class="admin-layout">
  <div class="admin-sidebar">
    <div class="sidebar-title">Admin Panel</div>
    <a href="/rg-trading-php/pages/admin/dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a href="/rg-trading-php/pages/admin/products.php"><span class="icon">❄️</span> Products</a>
    <a href="/rg-trading-php/pages/admin/orders.php"><span class="icon">📦</span> Orders</a>
    <a href="/rg-trading-php/pages/admin/users.php"><span class="icon">👥</span> Users</a>
    <a href="/rg-trading-php/pages/admin/categories.php"><span class="icon">🏷️</span> Categories</a>
    <a href="/rg-trading-php/pages/admin/reports.php" class="active"><span class="icon">📈</span> Reports</a>
    <a href="/rg-trading-php/index.php"><span class="icon">🏪</span> View Store</a>
  </div>

  <div class="admin-main">
    <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div><h1>Sales Reports</h1><p>Analytics and business performance overview</p></div>
      <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label style="font-size:13px;color:#718096;">Period:</label>
        <select name="period" onchange="this.form.submit()" style="padding:7px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
          <option value="7"  <?= $period===7?'selected':'' ?>>Last 7 days</option>
          <option value="30" <?= $period===30?'selected':'' ?>>Last 30 days</option>
          <option value="90" <?= $period===90?'selected':'' ?>>Last 90 days</option>
        </select>
      </form>
    </div>

    <!-- KPI Cards -->
    <div class="stats-grid">
      <?php
      $rev = $s['revenue'] ?? [];
      $ord = $s['orders']  ?? [];
      $cus = $s['customers'] ?? [];
      $growth = $rev['growth_pct'] ?? null;
      ?>
      <div class="stat-card">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value">₱<?= number_format(floatval($rev['total'] ?? 0), 0) ?></div>
        <div class="stat-sub">All time</div>
        <?php if ($growth !== null): ?>
          <div class="stat-growth <?= $growth >= 0 ? 'up' : 'down' ?>">
            <?= $growth >= 0 ? '↑' : '↓' ?> <?= abs($growth) ?>% vs prev period
          </div>
        <?php endif; ?>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Orders (<?= $period ?>d)</div>
        <div class="stat-value"><?= number_format($ord['period_orders'] ?? 0) ?></div>
        <div class="stat-sub"><?= $ord['pending_orders'] ?? 0 ?> pending · <?= $ord['delivered_orders'] ?? 0 ?> delivered</div>
      </div>
      <div class="stat-card orange">
        <div class="stat-label">Customers</div>
        <div class="stat-value"><?= number_format($cus['total_customers'] ?? 0) ?></div>
        <div class="stat-sub">+<?= $cus['new_customers'] ?? 0 ?> new · <?= $cus['repeat_customers'] ?? 0 ?> repeat</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-label">Period Revenue</div>
        <div class="stat-value">₱<?= number_format(floatval($rev['period'] ?? 0), 0) ?></div>
        <div class="stat-sub">Last <?= $period ?> days</div>
      </div>
    </div>

    <div class="report-grid">
      <!-- Revenue Trend Chart -->
      <div class="admin-card">
        <div class="admin-card-header"><h3>Revenue Trend</h3></div>
        <div class="admin-card-body">
          <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
        </div>
      </div>

      <!-- Seasonal Demand Chart -->
      <div class="admin-card">
        <div class="admin-card-header"><h3>Monthly Sales Pattern</h3></div>
        <div class="admin-card-body">
          <div class="chart-wrap"><canvas id="seasonChart"></canvas></div>
        </div>
      </div>
    </div>

    <div class="report-grid">
      <!-- Top Products -->
      <div class="admin-card">
        <div class="admin-card-header"><h3>Top Selling Products</h3></div>
        <div class="admin-card-body" style="padding:0;">
          <table class="data-table">
            <thead><tr><th>Product</th><th style="text-align:right;">Units Sold</th><th style="text-align:right;">Revenue</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($top_prods, 0, 8) as $tp): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;font-size:13px;"><?= h($tp['name']) ?></div>
                    <div style="font-size:11px;color:#a0aec0;"><?= h($tp['model_number'] ?? '') ?></div>
                  </td>
                  <td style="text-align:right;font-weight:700;"><?= number_format($tp['units_sold'] ?? 0) ?></td>
                  <td style="text-align:right;color:#38a169;font-weight:700;"><?= format_price($tp['total_revenue'] ?? 0) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($top_prods)): ?>
                <tr><td colspan="3" style="text-align:center;color:#a0aec0;padding:24px;">No sales data yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Repeat Customers -->
      <div class="admin-card">
        <div class="admin-card-header"><h3>Repeat Customers</h3></div>
        <div class="admin-card-body" style="padding:0;">
          <table class="data-table">
            <thead><tr><th>Customer</th><th style="text-align:center;">Orders</th><th style="text-align:right;">Lifetime Value</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($repeat, 0, 8) as $r): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;"><?= h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?></div>
                    <div style="font-size:11px;color:#a0aec0;"><?= h($r['email'] ?? '') ?></div>
                  </td>
                  <td style="text-align:center;"><span class="badge" style="background:#ebf4ff;color:#2b6cb0;"><?= $r['order_count'] ?? 0 ?></span></td>
                  <td style="text-align:right;font-weight:700;color:#1a365d;"><?= format_price($r['total_spent'] ?? 0) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($repeat)): ?>
                <tr><td colspan="3" style="text-align:center;color:#a0aec0;padding:24px;">No repeat customers yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
var revData  = <?= json_encode($rev_data) ?>;
var seasData = <?= json_encode($season) ?>;

if(revData && revData.length){
  new Chart(document.getElementById('revenueChart'), {
    type:'line',
    data:{
      labels: revData.map(function(d){ return d.period || d.date || ''; }),
      datasets:[{
        label:'Revenue (₱)',
        data: revData.map(function(d){ return parseFloat(d.revenue || d.total_revenue || 0); }),
        borderColor:'#3182ce',
        backgroundColor:'rgba(49,130,206,.08)',
        tension:0.4, fill:true, pointRadius:3,
      }]
    },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false } },
      scales:{ y:{ beginAtZero:true, ticks:{ callback:function(v){ return '₱'+v.toLocaleString(); } } } }
    }
  });
}

if(seasData && seasData.length){
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  new Chart(document.getElementById('seasonChart'), {
    type:'bar',
    data:{
      labels: seasData.map(function(d){ return months[(parseInt(d.month||1)-1)] || d.month; }),
      datasets:[{
        label:'Sales (₱)',
        data: seasData.map(function(d){ return parseFloat(d.revenue || d.total_revenue || 0); }),
        backgroundColor:'rgba(56,161,105,.7)',
        borderRadius:6,
      }]
    },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false } },
      scales:{ y:{ beginAtZero:true, ticks:{ callback:function(v){ return '₱'+v.toLocaleString(); } } } }
    }
  });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
