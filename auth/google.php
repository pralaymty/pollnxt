<?php
require_once __DIR__ . '/../config/db.php';

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$client_id = get_setting($pdo, 'google_client_id');
if ($client_id === '') {
    set_flash('error', 'Google login is not configured.');
    redirect('../auth/login.php');
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$redirect_uri = base_url() . '/pollnxt/beta-v3/auth/google_callback.php';

$params = [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $url);
exit;

