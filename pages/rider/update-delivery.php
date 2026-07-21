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
// Only include delivery_status when it is one of the allowed backend values.
$allowedDeliveryStatuses = ['pending','out_for_delivery','delivered','cannot_find_customer','failed','cancelled'];

// If rider is only removing proof, never forward delivery_status (prevents backend validation errors)
$removeProofRequested = !empty($_POST['remove_delivery_proof']);

// IMPORTANT: When removing proof, we MUST NOT send `delivery_status`.
if ($removeProofRequested) {
    // Hard ignore any possible hidden/stale delivery_status
    unset($deliveryPayload['delivery_status']);
} else {
    if (isset($_POST['delivery_status'])) {
        $candidate = trim((string)$_POST['delivery_status']);
        if ($candidate !== '' && in_array($candidate, $allowedDeliveryStatuses, true)) {
            $deliveryPayload['delivery_status'] = $candidate;
        }
    }
}






if (isset($_POST['delivery_note'])) {
    $deliveryPayload['delivery_note'] = $_POST['delivery_note'] !== '' ? $_POST['delivery_note'] : null;
}

// Allow rider to update delivery status and order status.
// Backend currently uses PATCH /api/orders/:id/status via updateOrderStatus? (mounted in admin).
// We will only forward order status when provided.
if (isset($_POST['order_status']) && $_POST['order_status'] !== '') {
    $orderPayload['status'] = $_POST['order_status'];
}

if (isset($_POST['payment_status']) && trim((string)$_POST['payment_status']) !== '') {
    $orderPayload['payment_status'] = trim((string)$_POST['payment_status']);
}

if (isset($_POST['expected_delivery_date'])) {
    $expectedDate = trim((string)$_POST['expected_delivery_date']);
    $orderPayload['expected_delivery_date'] = $expectedDate !== '' ? $expectedDate : null;
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

$payload = [];
if (!empty($orderPayload)) {
    $payload = array_merge($payload, $orderPayload);
}
if (!empty($deliveryPayload)) {
    $payload = array_merge($payload, $deliveryPayload);
}

if ($payload === []) {
    http_response_code(400);
    echo json_encode(['message' => 'No update payload provided']);
    exit;
}

remember_order_update($orderId, $payload);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Delivery update stored locally.',
    'local_only' => true,
    'stored' => $payload,
]);
exit;
