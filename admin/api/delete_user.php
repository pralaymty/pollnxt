<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

admin_require_login();

$csrf = $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !validate_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
}

