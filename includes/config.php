<?php
// ─── API Configuration ────────────────────────────────────────────────────────
define('API_BASE', 'http://localhost:3000/api');
define('APP_NAME', 'R&G Trading');
define('BASE_URL', '/rg-trading-php');
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

// ─── Auth Helpers ─────────────────────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['access_token']) && isset($_SESSION['user']);
}

function is_admin(): bool {
    return is_logged_in() && in_array($_SESSION['user']['role'] ?? '', ['admin', 'superadmin']);
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

function current_user(): array {
    return $_SESSION['user'] ?? [];
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

// ─── Utility ──────────────────────────────────────────────────────────────────
function format_price(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
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
?>
