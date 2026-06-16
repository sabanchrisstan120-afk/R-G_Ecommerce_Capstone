<?php
require_once __DIR__ . '/../../includes/config.php';
require_rider();

header('Content-Type: application/json');

$orderId = trim((string)($_POST['order_id'] ?? $_GET['order_id'] ?? ''));
if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Missing order_id']);
    exit;
}

$orderPayload = [];
$deliveryPayload = [];

// Accept delivery_status and note
if (isset($_POST['delivery_status'])) {
    $deliveryPayload['delivery_status'] = $_POST['delivery_status'] ?: null;
}
if (isset($_POST['delivery_note'])) {
    $deliveryPayload['delivery_note'] = $_POST['delivery_note'] !== '' ? $_POST['delivery_note'] : null;
}

// Accept order status updates (allow rider to update order status when permitted)
if (isset($_POST['order_status'])) {
    $orderPayload['status'] = $_POST['order_status'] !== '' ? $_POST['order_status'] : null;
}

// If a file was uploaded, convert to base64 and include as delivery_proof_base64
if (!empty($_FILES['delivery_proof']) && (int)($_FILES['delivery_proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $file = $_FILES['delivery_proof'];
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpeg', 'image/webp' => 'webp'];
    if (!isset($allowed[$file['type']])) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid file type']);
        exit;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['message' => 'File too large']);
        exit;
    }
    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        http_response_code(500);
        echo json_encode(['message' => 'Unable to read uploaded file']);
        exit;
    }
    $base64 = 'data:' . $file['type'] . ';base64,' . base64_encode($data);
    $deliveryPayload['delivery_proof_base64'] = $base64;
}

// Support special remove actions
if (!empty($_POST['remove_delivery_proof'])) {
    $deliveryPayload['remove_delivery_proof'] = true;
}

// Prepare logging
$log_dir = __DIR__ . '/../../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/rider-delivery.log';
function ld_log($msg) {
    global $log_file;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

$results = [];

if (!empty($orderPayload)) {
    ld_log("RIDER_DELIVERY: user=" . ($_SESSION['user']['id'] ?? 'unknown') . " order_id={$orderId} endpoint=/orders/{$orderId}");
    ld_log('ORDER_PAYLOAD: ' . json_encode($orderPayload));
    $results[] = api_request('PATCH', '/orders/' . urlencode($orderId), $orderPayload, true);
}

if (!empty($deliveryPayload)) {
    ld_log("RIDER_DELIVERY: user=" . ($_SESSION['user']['id'] ?? 'unknown') . " order_id={$orderId} endpoint=/orders/{$orderId}/delivery");
    ld_log('DELIVERY_PAYLOAD: ' . json_encode($deliveryPayload));
    $results[] = api_request('PATCH', '/orders/' . urlencode($orderId) . '/delivery', $deliveryPayload, true);
}

if (empty($results)) {
    http_response_code(400);
    echo json_encode(['message' => 'No update payload provided']);
    exit;
}

$final = end($results);
foreach ($results as $index => $res) {
    ld_log('API_RESPONSE_STATUS_' . $index . ': ' . ($res['status'] ?? ''));
    ld_log('API_RESPONSE_BODY_' . $index . ': ' . json_encode($res['body'] ?? $res));
}

http_response_code($final['status'] ?: 500);
echo json_encode($final['body'] ?? ['status' => $final['status']]);
exit;
