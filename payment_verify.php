<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');

if (!is_logged_in()) {
    set_flash('error', 'Please login again.');
    redirect('auth/login.php');
}

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

$paymentId = (string)($_POST['razorpay_payment_id'] ?? '');
$orderId = (string)($_POST['razorpay_order_id'] ?? '');
$signature = (string)($_POST['razorpay_signature'] ?? '');

$pending = $_SESSION['pending_payment'] ?? null;
if (!is_array($pending) || empty($pending['plan']) || empty($pending['order_id'])) {
    set_flash('error', 'Payment session expired. Please try again.');
    redirect('billing.php');
}

if ($pending['order_id'] !== $orderId) {
    set_flash('error', 'Invalid payment order.');
    redirect('billing.php');
}

// Load razorpay secret key based on mode used during order creation
$mode = ($pending['mode'] ?? 'test') === 'live' ? 'live' : 'test';
if ($mode === 'live') {
    $secretKey = get_setting($pdo, 'razorpay_secret_key_live');
} else {
    $secretKey = get_setting($pdo, 'razorpay_secret_key_test');
}

if ($paymentId === '' || $orderId === '' || $signature === '' || $secretKey === '') {
    set_flash('error', 'Payment verification failed.');
    redirect('billing.php');
}

$expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secretKey);
if (!hash_equals($expected, $signature)) {
    set_flash('error', 'Payment signature mismatch. Please try again.');
    redirect('billing.php');
}

$addPolls = (int)($pending['add_polls'] ?? 0);
$amount = (float)($pending['price'] ?? 0);
$planName = (string)($pending['plan_name'] ?? '');

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE users SET paid_polls_balance = paid_polls_balance + ?, is_paid = 1 WHERE id = ?");
    $stmt->execute([$addPolls, $_SESSION['user_id']]);

    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, payment_status, plan_polls) VALUES (?, ?, 'success', ?)");
    $stmt->execute([$_SESSION['user_id'], $amount, $addPolls]);

    $pdo->commit();

    // Refresh session quota values
    $stmt = $pdo->prepare("SELECT polls_created, paid_polls_balance FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    $_SESSION['polls_created'] = (int)($row['polls_created'] ?? 0);
    $_SESSION['paid_polls_balance'] = (int)($row['paid_polls_balance'] ?? 0);
    $_SESSION['is_paid'] = 1;

    set_flash('success', 'Payment successful. Credits unlocked!');
    unset($_SESSION['pending_payment']);
    redirect('pages/my_polls.php');
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('error', 'Payment could not be completed. Please try again.');
    redirect('billing.php');
}

