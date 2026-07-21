<?php
// ─── API Configuration ────────────────────────────────────────────────────────
define('API_BASE', 'http://localhost:3000/api');
define('APP_NAME', 'R&G Trading');
define('BASE_URL', '/rg-trading-php');
define('ORDER_STATE_STORE_FILE', __DIR__ . '/../uploads/order-state-store.json');

// ─── Direct DB Configuration (used only for password reset) ──────────────────
// The Node.js API has no password-reset route, so the forgot-password flow
// updates users.password_hash directly. Adjust these if your MySQL setup
// differs from the XAMPP defaults.
define('DB_HOST', 'localhost');
define('DB_NAME', 'rg_trading');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_db_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

/**
 * Directly resets a user's password by email, bypassing the API.
 * Returns true on success, false if no user with that email exists,
 * or a string with the failure reason on a DB error.
 */
function db_reset_password(string $email, string $newPassword) {
    try {
        $pdo = get_db_pdo();

        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if (!$check->fetch()) {
            return false;
        }

        // bcrypt, cost 12 — matches the $2a$12$... hashes already in the
        // users table, so password_verify() on the Node API side keeps working.
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $update = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?');
        $update->execute([$hash, $email]);

        return true;
    } catch (PDOException $e) {
        error_log('db_reset_password failed: ' . $e->getMessage());
        return $e->getMessage();
    }
}

// ─── Session Start ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── API Helper: Send request to Node.js backend ─────────────────────────────
function api_request(string $method, string $endpoint, array $data = [], bool $auth = false): array {
    $url = API_BASE . $endpoint;
    $headers = ['Content-Type: application/json'];

    if ($auth && isset($_SESSION['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    return ['status' => $http_code, 'body' => $decoded ?? []];
}

function api_request_with_fallback(string $method, array $endpoints, array $data = [], bool $auth = false): array {
    $last_response = ['status' => 0, 'body' => []];

    foreach ($endpoints as $endpoint) {
        $response = api_request($method, $endpoint, $data, $auth);
        $last_response = $response;

        $status = (int)($response['status'] ?? 0);
        if ($status >= 200 && $status < 400) {
            return $response;
        }
    }

    return $last_response;
}

function payment_status_options(): array {
    return ['pending', 'paid', 'failed', 'refunded'];
}

/**
 * Persists an admin order update directly to MySQL.
 *
 * `status` / `payment_status` belong to the `orders` table. `delivery_status`
 * and `expected_delivery_date` belong to the related `order_deliveries` row
 * (one-to-one via order_deliveries.order_id). The Node API has no route for
 * this yet, so — same pattern as db_reset_password() — we go straight to the
 * database rather than pretending the update happened.
 */
function api_update_order_status(string $order_id, array $payload = [], bool $auth = false): array {
    $order_id = trim($order_id);

    if ($order_id === '') {
        return ['status' => 400, 'body' => ['success' => false, 'message' => 'Missing order id.']];
    }

    if (empty($payload)) {
        return ['status' => 200, 'body' => ['success' => true, 'message' => 'No updates to apply.']];
    }

    $order_fields    = array_intersect_key($payload, array_flip(['status', 'payment_status']));
    $delivery_fields = array_intersect_key($payload, array_flip(['delivery_status', 'expected_delivery_date']));

    // Validate against the same enums the DB columns actually accept, so a
    // bad value fails loudly here instead of erroring (or silently
    // truncating) inside MySQL.
    if (isset($order_fields['status']) && !in_array($order_fields['status'], order_status_options(), true)) {
        return ['status' => 422, 'body' => ['success' => false, 'message' => 'Invalid order status value.']];
    }
    if (isset($order_fields['payment_status']) && !in_array($order_fields['payment_status'], payment_status_options(), true)) {
        return ['status' => 422, 'body' => ['success' => false, 'message' => 'Invalid payment status value.']];
    }
    if (isset($delivery_fields['delivery_status']) && !in_array($delivery_fields['delivery_status'], delivery_status_options(), true)) {
        return ['status' => 422, 'body' => ['success' => false, 'message' => 'Invalid delivery status value.']];
    }
    if (!empty($delivery_fields['expected_delivery_date'])) {
        $date = DateTime::createFromFormat('Y-m-d', $delivery_fields['expected_delivery_date']);
        if (!$date || $date->format('Y-m-d') !== $delivery_fields['expected_delivery_date']) {
            return ['status' => 422, 'body' => ['success' => false, 'message' => 'Invalid expected delivery date.']];
        }
    }

    $pdo = get_db_pdo();

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
        $check->execute([$order_id]);
        if (!$check->fetch()) {
            $pdo->rollBack();
            return ['status' => 404, 'body' => ['success' => false, 'message' => 'Order not found.']];
        }

        if (!empty($order_fields)) {
            $set = [];
            $params = [];
            foreach ($order_fields as $col => $val) {
                $set[] = "`$col` = ?";
                $params[] = $val;
            }
            $params[] = $order_id;
            $pdo->prepare('UPDATE orders SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ?')
                ->execute($params);
        }

        if (!empty($delivery_fields)) {
            $existing = $pdo->prepare('SELECT id FROM order_deliveries WHERE order_id = ? LIMIT 1');
            $existing->execute([$order_id]);

            if ($existing->fetch()) {
                $set = [];
                $params = [];
                foreach ($delivery_fields as $col => $val) {
                    $set[] = "`$col` = ?";
                    $params[] = ($val === '' ? null : $val);
                }
                $params[] = $order_id;
                $pdo->prepare('UPDATE order_deliveries SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE order_id = ?')
                    ->execute($params);
            } else {
                $cols = array_keys($delivery_fields);
                $vals = array_map(fn($v) => ($v === '' ? null : $v), array_values($delivery_fields));
                $cols[] = 'order_id';
                $vals[] = $order_id;
                $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO order_deliveries ($colList) VALUES ($placeholders)")
                    ->execute($vals);
            }
        }

        $pdo->commit();

        return [
            'status' => 200,
            'body' => ['success' => true, 'message' => 'Order updated.', 'stored' => $payload],
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('api_update_order_status failed: ' . $e->getMessage());
        return ['status' => 500, 'body' => ['success' => false, 'message' => 'A database error occurred while updating the order.']];
    }
}

// ─── Auth Helpers ─────────────────────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['access_token']) && isset($_SESSION['user']);
}

function is_admin(): bool {
    return is_logged_in() && in_array($_SESSION['user']['role'] ?? '', ['admin', 'superadmin']);
}

function is_rider(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'rider';
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /rg-trading-php/login.php');
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: /rg-trading-php/index.php?error=unauthorized');
        exit;
    }
}

function require_rider(): void {
    if (!is_rider()) {
        header('Location: /rg-trading-php/index.php?error=unauthorized');
        exit;
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function get_authenticated_profile(): array {
    $fallback = current_user();

    if (!is_logged_in()) {
        return $fallback;
    }

    $result = api_request('GET', '/auth/me', [], true);
    if (($result['status'] ?? 0) >= 200 && ($result['status'] ?? 0) < 400) {
        $body = $result['body'] ?? [];
        if (isset($body['data']['user']) && is_array($body['data']['user'])) {
            $_SESSION['user'] = $body['data']['user'];
            return $_SESSION['user'];
        }
        if (isset($body['user']) && is_array($body['user'])) {
            $_SESSION['user'] = $body['user'];
            return $_SESSION['user'];
        }
    }

    return $fallback;
}

// ─── Flash Messages ───────────────────────────────────────────────────────────
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function verify_csrf_token(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token(): string {
    return generate_csrf_token();
}

function get_order_state_store(): array {
    $store = [];
    $store_file = ORDER_STATE_STORE_FILE;

    if (is_file($store_file)) {
        $content = @file_get_contents($store_file);
        if ($content !== false && trim($content) !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $store = $decoded;
            }
        }
    }

    if (!isset($_SESSION['order_state_store']) || !is_array($_SESSION['order_state_store'])) {
        $_SESSION['order_state_store'] = $store;
    }

    return is_array($_SESSION['order_state_store']) ? $_SESSION['order_state_store'] : $store;
}

function save_order_state_store(array $store): void {
    $store_file = ORDER_STATE_STORE_FILE;
    $dir = dirname($store_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $encoded = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
        @file_put_contents($store_file, $encoded);
    }
}

function remember_order_update(string $order_id, array $payload): void {
    $order_id = (string) $order_id;
    if ($order_id === '') {
        return;
    }

    $store = get_order_state_store();
    $store[$order_id] = array_merge($store[$order_id] ?? [], $payload);
    $_SESSION['order_state_store'] = $store;
    save_order_state_store($store);
}

function apply_order_state_overrides(array $orders): array {
    $store = get_order_state_store();
    if ($store === []) {
        return $orders;
    }

    foreach ($orders as &$order) {
        $order_id = (string)($order['id'] ?? '');
        if ($order_id !== '' && isset($store[$order_id])) {
            $order = array_merge($order, $store[$order_id]);
        }
    }

    return $orders;
}

function is_associative_array(array $data): bool {
    if ($data === []) {
        return false;
    }
    return array_keys($data) !== range(0, count($data) - 1);
}

function normalize_api_order_response(array $body): array {
    if (isset($body['data']) && is_array($body['data'])) {
        if (isset($body['data']['order']) && is_array($body['data']['order'])) {
            return $body['data']['order'];
        }
        if (is_associative_array($body['data'])) {
            return $body['data'];
        }
    }
    if (isset($body['order']) && is_array($body['order'])) {
        return $body['order'];
    }
    if (is_associative_array($body)) {
        return $body;
    }
    return [];
}

function api_get_order_detail(string $order_id): array {
    $order_id = trim($order_id);
    if ($order_id === '') {
        return [];
    }

    $result = api_request('GET', '/orders/' . urlencode($order_id), [], true);
    if (($result['status'] ?? 0) >= 200 && ($result['status'] ?? 0) < 400) {
        return normalize_api_order_response($result['body'] ?? []);
    }

    return [];
}

function api_get_user_detail(string $user_id): array {
    static $user_cache = [];

    $user_id = trim($user_id);
    if ($user_id === '') {
        return [];
    }

    if (isset($user_cache[$user_id])) {
        return $user_cache[$user_id];
    }

    $endpoints = [
        '/admin/users?role=customer&limit=1&user_id=' . urlencode($user_id),
        '/admin/users?role=customer&limit=1&id=' . urlencode($user_id),
        '/admin/users/' . urlencode($user_id),
        '/users/' . urlencode($user_id),
    ];

    foreach ($endpoints as $endpoint) {
        $result = api_request('GET', $endpoint, [], true);
        if (($result['status'] ?? 0) < 200 || ($result['status'] ?? 0) >= 400) {
            continue;
        }

        $body = $result['body'] ?? [];
        if (isset($body['data']['users'][0]) && is_array($body['data']['users'][0])) {
            $user_cache[$user_id] = $body['data']['users'][0];
            return $user_cache[$user_id];
        }
        if (isset($body['users'][0]) && is_array($body['users'][0])) {
            $user_cache[$user_id] = $body['users'][0];
            return $user_cache[$user_id];
        }
        if (isset($body['data']['user']) && is_array($body['data']['user'])) {
            $user_cache[$user_id] = $body['data']['user'];
            return $user_cache[$user_id];
        }
        if (isset($body['user']) && is_array($body['user'])) {
            $user_cache[$user_id] = $body['user'];
            return $user_cache[$user_id];
        }
    }

    $user_cache[$user_id] = [];
    return [];
}

function enrich_orders_with_details(array $orders): array {
    foreach ($orders as &$order) {
        $has_address = trim(format_order_address($order)) !== '—';
        $has_phone = trim(get_order_phone($order)) !== '';
        if ($has_address && $has_phone) {
            continue;
        }

        $order_id = (string)($order['id'] ?? '');
        if ($order_id === '') {
            continue;
        }

        $detail = api_get_order_detail($order_id);
        if (!empty($detail)) {
            $order = array_replace_recursive($order, $detail);
        }

        if (trim(get_order_phone($order)) === '' && !empty($order['user_id'])) {
            $user = api_get_user_detail((string)$order['user_id']);
            if (!empty($user)) {
                $order['user'] = array_replace_recursive($order['user'] ?? [], $user);
                if (empty($order['email']) && !empty($user['email'])) {
                    $order['email'] = $user['email'];
                }
                if (empty($order['customer_name']) && (!empty($user['first_name']) || !empty($user['last_name']))) {
                    $order['customer_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                }
                if (empty($order['phone']) && !empty($user['phone'])) {
                    $order['phone'] = $user['phone'];
                }
                if (empty($order['customer_phone']) && !empty($user['phone'])) {
                    $order['customer_phone'] = $user['phone'];
                }
            }
        }
    }
    return $orders;
}

// ─── Utility ──────────────────────────────────────────────────────────────────
function format_price(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function get_order_nested_value(array $order, array $keys): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $order) && $order[$key] !== null && $order[$key] !== '') {
            return (string) $order[$key];
        }
    }

    foreach ($order as $value) {
        if (is_array($value)) {
            $found = get_order_nested_value($value, $keys);
            if ($found !== '') {
                return $found;
            }
        }
    }

    return '';
}

function get_order_customer_name(array $order): string {
    $direct = trim((string)($order['customer_name'] ?? $order['full_name'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $nested = trim((string)get_order_nested_value($order, ['customer_name', 'full_name', 'name', 'display_name']));
    if ($nested !== '') {
        return $nested;
    }

    $first = trim((string)($order['first_name'] ?? $order['customer_first_name'] ?? $order['user']['first_name'] ?? $order['customer']['first_name'] ?? ''));
    $last  = trim((string)($order['last_name'] ?? $order['customer_last_name'] ?? $order['user']['last_name'] ?? $order['customer']['last_name'] ?? ''));
    return trim($first . ' ' . $last);
}

function get_order_customer_email(array $order): string {
    $nested = trim((string)get_order_nested_value($order, ['email', 'customer_email', 'user_email', 'contact_email']));
    if ($nested !== '') {
        return $nested;
    }

    return trim((string)($order['email'] ?? $order['customer_email'] ?? $order['user']['email'] ?? $order['customer']['email'] ?? ''));
}

function find_phone_number_in_array(array $data): string {
    foreach ($data as $key => $value) {
        if (is_string($key)) {
            $lower = strtolower($key);
            if (preg_match('/phone|tel|mobile|whatsapp|landline/', $lower) && $value !== null && $value !== '') {
                if (!is_array($value)) {
                    return trim((string)$value);
                }
            }
        }

        if (is_array($value)) {
            $found = find_phone_number_in_array($value);
            if ($found !== '') {
                return trim($found);
            }
        }
    }

    return '';
}

function get_order_phone(array $order): string {
    $candidates = [
        'phone',
        'phone_number',
        'phone_no',
        'contact_no',
        'contact_phone',
        'customer_phone',
        'mobile',
        'mobile_phone',
        'mobile_no',
        'cellphone',
        'cell_number',
        'billing_phone',
        'recipient_phone',
        'contact_number',
        'telephone',
        'tel',
        'whatsapp',
        'whatsapp_number',
        'phone1',
        'phone2',
        'phone_home',
        'phone_work',
        'landline',
        'msisdn',
    ];

    foreach ($candidates as $key) {
        if (array_key_exists($key, $order) && $order[$key] !== null && $order[$key] !== '') {
            return trim((string)$order[$key]);
        }
    }

    $nested = get_order_nested_value($order, $candidates);
    if ($nested !== '') {
        return trim($nested);
    }

    foreach (['user', 'customer', 'buyer', 'account', 'contact', 'billing_address', 'shipping_address', 'delivery_address'] as $node) {
        if (isset($order[$node]) && is_array($order[$node])) {
            $nested = get_order_nested_value($order[$node], $candidates);
            if ($nested !== '') {
                return trim($nested);
            }
            $fallback = find_phone_number_in_array($order[$node]);
            if ($fallback !== '') {
                return trim($fallback);
            }
        }
    }

    $fallback = find_phone_number_in_array($order);
    if ($fallback !== '') {
        return trim($fallback);
    }

    return '';
}

function get_order_address_parts(array $order): array {
    $address = ['street' => '', 'city' => '', 'province' => '', 'zip' => ''];

    $address_candidates = [];
    foreach (['shipping_address', 'delivery_address', 'address', 'customer_address'] as $key) {
        if (array_key_exists($key, $order)) {
            $address_candidates[] = $order[$key];
        }
    }

    foreach ($order as $value) {
        if (is_array($value)) {
            foreach (['shipping_address', 'delivery_address', 'address', 'customer_address'] as $key) {
                if (array_key_exists($key, $value)) {
                    $address_candidates[] = $value[$key];
                }
            }
        }
    }

    foreach ($address_candidates as $candidate) {
        if (is_array($candidate)) {
            $address['street'] = $address['street'] !== '' ? $address['street'] : trim((string)($candidate['street'] ?? $candidate['address'] ?? $candidate['address_line_1'] ?? $candidate['address_line'] ?? ''));
            $address['city'] = $address['city'] !== '' ? $address['city'] : trim((string)($candidate['city'] ?? $candidate['town'] ?? $candidate['municipality'] ?? ''));
            $address['province'] = $address['province'] !== '' ? $address['province'] : trim((string)($candidate['province'] ?? $candidate['state'] ?? $candidate['region'] ?? ''));
            $address['zip'] = $address['zip'] !== '' ? $address['zip'] : trim((string)($candidate['zip'] ?? $candidate['zip_code'] ?? $candidate['postal_code'] ?? ''));
        } elseif (is_string($candidate) && trim($candidate) !== '') {
            $address['street'] = $address['street'] !== '' ? $address['street'] : trim($candidate);
        }
    }

    $address['street'] = $address['street'] !== '' ? $address['street'] : trim((string)get_order_nested_value($order, ['street', 'address', 'street_address', 'address_line_1', 'address_line']));
    $address['city'] = $address['city'] !== '' ? $address['city'] : trim((string)get_order_nested_value($order, ['city', 'town', 'municipality']));
    $address['province'] = $address['province'] !== '' ? $address['province'] : trim((string)get_order_nested_value($order, ['province', 'state', 'region']));
    $address['zip'] = $address['zip'] !== '' ? $address['zip'] : trim((string)get_order_nested_value($order, ['zip', 'zip_code', 'postal_code']));

    return $address;
}

function format_order_address(array $order): string {
    $parts = array_filter(get_order_address_parts($order));
    return implode(', ', $parts) ?: '—';
}

function order_status_label(string $status): string {
    $status = strtolower(trim($status));
    $labels = [
        'pending' => 'Pending',
        'confirmed' => 'Approved',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function order_status_options(): array {
    // Must match orders.status enum exactly — no DB migration required.
    return [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'refunded',
    ];
}

function delivery_status_options(): array {
    // Must match order_deliveries.delivery_status enum exactly.
    return ['pending','out_for_delivery','delivered','cannot_find_customer','failed','damaged'];
}

function order_status_badge_class(string $status): string {
    $status = strtolower(trim($status));
    $map = [
        'pending' => 'pending',
        'confirmed' => 'success',
        'processing' => 'info',
        'shipped' => 'blue',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'danger',
    ];
    return $map[$status] ?? 'pending';
}

// ─── Product Image Helper ─────────────────────────────────────────────────────
// Returns the image URL to store:
//   - If a file was uploaded → saves it to assets/uploads/ and returns the web path
//   - If a URL was pasted   → returns the URL as-is
//   - If neither            → returns null (keep existing image or no image)
function resolve_product_image_url(string $url_input): ?string {
    $upload_dir  = __DIR__ . '/../assets/uploads/';
    $upload_web  = '/rg-trading-php/assets/uploads/';

    // Priority 1: uploaded file
    if (!empty($_FILES['product_image']['tmp_name'])
        && (int)($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {

        $file     = $_FILES['product_image'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file['type'], $allowed, true) || $file['size'] > $max_size) {
            return null; // caller will show error
        }

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = uniqid('product_', true) . '.' . strtolower($ext);
        $dest     = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $upload_web . $filename;
        }
        return null;
    }

    // Priority 2: pasted URL
    $url = trim($url_input);
    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    // Nothing provided
    return null;
}