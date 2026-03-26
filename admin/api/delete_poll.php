<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

admin_require_login();

$csrf = $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || !validate_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$poll_id = (int)($_POST['poll_id'] ?? 0);
if ($poll_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid poll_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
    $stmt->execute([$poll_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Poll not found']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
}

