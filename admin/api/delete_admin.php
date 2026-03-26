<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

admin_require_super();

$csrf = $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !validate_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$admin_id = (int)($_POST['admin_id'] ?? 0);
if ($admin_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid admin_id']);
    exit;
}

// Prevent deleting yourself
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
if ($admin_id === $current_admin_id) {
    echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
    $stmt->execute([$admin_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Admin not found']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
}

