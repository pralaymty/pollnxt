<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function json_ok(array $extra = []): void {
    echo json_encode(array_merge(['ok' => true], $extra));
    exit;
}

if (!admin_is_logged_in()) {
    json_fail('Unauthorized', 401);
}

$action = (string)($_POST['action'] ?? '');
$csrf = (string)($_POST['csrf_token'] ?? '');

if ($action === '') {
    json_fail('Missing action');
}

if ($csrf === '' || !validate_csrf_token($csrf)) {
    json_fail('Invalid CSRF token', 403);
}

try {
    if ($action === 'delete_poll') {
        $poll_id = (int)($_POST['poll_id'] ?? 0);
        if ($poll_id <= 0) json_fail('Invalid poll id');
        $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
        $stmt->execute([$poll_id]);
        json_ok(['deleted_id' => $poll_id]);
    }

    if ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) json_fail('Invalid user id');
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        json_ok(['deleted_id' => $user_id]);
    }

    if ($action === 'delete_admin') {
        // Only super admins can delete admin users
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            json_fail('Forbidden', 403);
        }
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        if ($admin_id <= 0) json_fail('Invalid admin id');
        if ($admin_id === (int)($_SESSION['admin_id'] ?? 0)) {
            json_fail('You cannot delete your own account');
        }
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$admin_id]);
        json_ok(['deleted_id' => $admin_id]);
    }

    json_fail('Unknown action');
} catch (Exception $e) {
    json_fail('Server error', 500);
}

