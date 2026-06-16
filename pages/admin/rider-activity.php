<?php
require_once __DIR__ . '/../../includes/config.php';
require_admin();

$filters = [
    'rider_id' => trim($_GET['rider_id'] ?? ''),
    'order_id' => trim($_GET['order_id'] ?? ''),
    'page'     => max(1, intval($_GET['page'] ?? 1)),
    'limit'    => 25,
];

$query_params = array_filter($filters, function ($value) {
    return $value !== '' && $value !== null;
});

$params = http_build_query($query_params);

$riders = [];
$rider_result = api_request('GET', '/admin/users?role=rider&limit=200', [], true);
$riders = $rider_result['body']['data']['users'] ?? $rider_result['body']['users'] ?? [];

$result = api_request('GET', '/admin/rider-activity' . ($params ? '?' . $params : ''), [], true);
$activity = $result['body']['data']['activity'] ?? $result['body']['activity'] ?? [];
$pagination = $result['body']['data']['pagination'] ?? $result['body']['pagination'] ?? ['total' => 0];
$total_pages = ceil(($pagination['total'] ?? 0) / $filters['limit']);

$order_ids = [];
foreach ($activity as $item) {
    if (!empty($item['order_id'])) {
        $order_ids[] = trim((string)$item['order_id']);
        continue;
    }
    if (!empty($item['metadata'])) {
        $meta = json_decode($item['metadata'], true);
        if (is_array($meta) && !empty($meta['order_id'])) {
            $order_ids[] = trim((string)$meta['order_id']);
        }
    }
}
$order_ids = array_values(array_filter(array_unique($order_ids)));
$order_proof_urls = [];
foreach ($order_ids as $order_id) {
    $order_response = api_request('GET', '/orders/' . urlencode($order_id), [], true);
    $order_data = $order_response['body']['data']['order'] ?? $order_response['body']['order'] ?? $order_response['body'] ?? [];
    if (!empty($order_data['delivery_proof_url'])) {
        $order_proof_urls[$order_id] = trim((string)$order_data['delivery_proof_url']);
    }
}

$page_title = 'Rider Activity — Admin — ' . APP_NAME;
include __DIR__ . '/../../includes/header.php';

function normalize_metadata_label(string $key): string {
    return h(ucwords(str_replace(['_', '-'], ' ', trim($key))));
}

function format_metadata_value($value): string {
    if ($value === null || $value === '') {
        return 'N/A';
    }
    if (is_bool($value)) {
        return h($value ? 'Yes' : 'No');
    }
    if (is_array($value)) {
        if (empty($value)) {
            return 'N/A';
        }
        if (array_keys($value) === range(0, count($value) - 1)) {
            return h(implode(', ', array_map(function ($item) {
                return is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$item;
            }, $value)));
        }
        $parts = [];
        foreach ($value as $sub_key => $sub_value) {
            $parts[] = normalize_metadata_label($sub_key) . ': ' . h(is_array($sub_value)
                ? json_encode($sub_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string)$sub_value);
        }
        return h(implode(', ', $parts));
    }
    return h((string)$value);
}

function render_metadata($json) {
    if (!$json) {
        return '<div class="detail-empty">No additional details</div>';
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return '<div class="detail-value">' . h((string)$json) . '</div>';
    }
    $data = array_filter($data, function ($value) {
        return $value !== null && $value !== '' && $value !== [];
    });
    foreach (['event_type', 'order_id', 'created_at', 'delivery_proof_uploaded', 'proof_uploaded'] as $hidden) {
        unset($data[$hidden]);
    }
    if (empty($data)) {
        return '<div class="detail-empty">No additional details</div>';
    }
    $priority = ['action', 'status', 'message', 'note', 'reason', 'location', 'details'];
    $ordered = [];
    foreach ($priority as $key) {
        if (array_key_exists($key, $data)) {
            $ordered[$key] = $data[$key];
            unset($data[$key]);
        }
    }
    $data = array_merge($ordered, $data);
    $limit = 4;
    $display = array_slice($data, 0, $limit, true);
    $parts = [];
    foreach ($display as $key => $value) {
        $label = normalize_metadata_label($key);
        $parts[] = '<span class="detail-pair"><strong>' . $label . ':</strong> ' . format_metadata_value($value) . '</span>';
    }
    if (count($data) > $limit) {
        $parts[] = '<span class="detail-pair detail-more">+' . (count($data) - $limit) . ' more</span>';
    }
    return '<div class="detail-pairs">' . implode('', $parts) . '</div>';
}

function get_order_id_from_activity(array $item): ?string {
    if (!empty($item['order_id'])) {
        return trim((string)$item['order_id']);
    }
    if (!empty($item['metadata'])) {
        $meta = json_decode($item['metadata'], true);
        if (is_array($meta) && !empty($meta['order_id'])) {
            return trim((string)$meta['order_id']);
        }
    }
    return null;
}

function get_proof_url(array $item, array $order_proof_urls = []): ?string {
    $fields = ['delivery_proof_url', 'proof_url', 'photo_url', 'image_url', 'url'];
    foreach ($fields as $field) {
        if (!empty($item[$field])) {
            return trim((string)$item[$field]);
        }
    }
    if (!empty($item['metadata'])) {
        $meta = json_decode($item['metadata'], true);
        if (is_array($meta)) {
            foreach ($fields as $field) {
                if (!empty($meta[$field])) {
                    return trim((string)$meta[$field]);
                }
            }
        }
    }
    $order_id = get_order_id_from_activity($item);
    if ($order_id && isset($order_proof_urls[$order_id])) {
        return $order_proof_urls[$order_id];
    }
    return null;
}

function get_event_badge_class(?string $event_type): string {
    $event_type = trim((string)$event_type);
    if ($event_type === '') {
        return 'badge-soft-yellow';
    }
    $key = strtolower(str_replace(' ', '_', $event_type));
    $map = [
        'delivered' => 'badge-soft-green',
        'out_for_delivery' => 'badge-soft-blue',
        'delivery_proof' => 'badge-soft-blue',
        'proof_uploaded' => 'badge-soft-blue',
        'pending' => 'badge-soft-yellow',
        'processing' => 'badge-soft-yellow',
        'cannot_find_customer' => 'badge-soft-yellow',
        'failed' => 'badge-soft-red',
        'cancelled' => 'badge-soft-red',
        'pickup' => 'badge-soft-blue',
        'returned' => 'badge-soft-red',
        'confirmed' => 'badge-soft-green',
    ];
    return $map[$key] ?? 'badge-soft-blue';
}
?>

<div class="admin-layout">
  <?php include __DIR__ . '/../../includes/admin-sidebar.php'; ?>

<div class="admin-main">
  <div class="admin-header">
    <h1>Rider Activity</h1>
    <p>Track delivery events and uploads from riders.</p>
  </div>

  <form method="GET" class="search-bar search-inline mb-20">
    <select name="rider_id" class="form-select search-input">
      <option value="">All Riders</option>
      <?php foreach ($riders as $rider): ?>
        <option value="<?= h($rider['id']) ?>" <?= $filters['rider_id'] === $rider['id'] ? 'selected' : '' ?>>
          <?= h(trim($rider['first_name'] . ' ' . $rider['last_name'])) ?> (<?= h($rider['email']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="order_id" placeholder="Order ID" value="<?= h($filters['order_id']) ?>" class="form-input search-input">
    <button type="submit" class="btn-filter">Filter</button>
  </form>

  <div class="admin-card">
    <div class="admin-card-header">
      <h3>Activity Logs (<?= $pagination['total'] ?? 0 ?>)</h3>
    </div>
    <div class="admin-card-body card-no-padding">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Rider</th>
            <th>Order</th>
            <th>Event</th>
            <th>Details</th>
            <th>Proof</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($activity)): ?>
            <tr>
              <td colspan="8" class="table-empty">No rider activity found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($activity as $item): ?>
              <?php $proof_url = get_proof_url($item, $order_proof_urls); ?>
              <?php $order_id = get_order_id_from_activity($item); ?>
              <tr>
                <td class="text-center text-13">
                  <?= !empty($item['created_at']) ? date('m/d/Y', strtotime($item['created_at'])) : '—' ?>
                </td>
                <td class="activity-time text-center text-small text-muted">
                  <?= !empty($item['created_at']) ? date('H:i', strtotime($item['created_at'])) : '—' ?>
                </td>
                <td class="activity-rider">
                  <div class="font-600"><?= h(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?: h($item['email'] ?? 'Unknown') ?></div>
                  <div class="muted-small"><?= h($item['email'] ?? '—') ?></div>
                </td>
                <td class="text-small text-muted">
                  <?= $order_id ? h($order_id) : '—' ?>
                </td>
                <td>
                  <?php $event_type = $item['event_type'] ?? ''; ?>
                  <span class="badge <?= get_event_badge_class($event_type) ?>">
                    <?= h(ucfirst(str_replace('_', ' ', $event_type ?: 'unknown'))) ?>
                  </span>
                </td>
                <td class="activity-details">
                  <?= render_metadata($item['metadata'] ?? '') ?>
                </td>
                <td class="min-w-160">
                  <?php if ($proof_url): ?>
                    <a href="<?= BASE_URL ?>/pages/proof.php?path=<?= urlencode($proof_url) ?>" target="_blank" class="proof-link proof-card">
                      <img src="<?= BASE_URL ?>/pages/proof.php?path=<?= urlencode($proof_url) ?>" alt="Proof" class="proof-img" />
                      <span class="text-small">View proof</span>
                    </a>
                  <?php else: ?>
                    <span class="table-cell-muted">No proof</span>
                  <?php endif; ?>
                </td>
                <td class="text-small text-center text-muted"><?= h($item['ip_address'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination mt-16">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <?php if ($p === $filters['page']): ?>
          <span class="active"><?= $p ?></span>
        <?php else: ?>
          <a href="?<?= h(http_build_query(array_merge($_GET, ['page' => $p]))) ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</div>
</div>
