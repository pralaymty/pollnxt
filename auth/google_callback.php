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

function http_post_form(string $url, array $data): array {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 15,
        ],
    ];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    $json = $resp ? json_decode($resp, true) : null;
    return is_array($json) ? $json : [];
}

function http_get_json(string $url, string $bearer): array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$bearer}\r\n",
            'timeout' => 15,
        ],
    ];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    $json = $resp ? json_decode($resp, true) : null;
    return is_array($json) ? $json : [];
}

if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], (string)$_GET['state'])) {
    set_flash('error', 'Google login failed (invalid state).');
    redirect('../auth/login.php');
}
unset($_SESSION['google_oauth_state']);

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    set_flash('error', 'Google login failed.');
    redirect('../auth/login.php');
}

$client_id = get_setting($pdo, 'google_client_id');
$client_secret = get_setting($pdo, 'google_client_secret');
if ($client_id === '' || $client_secret === '') {
    set_flash('error', 'Google login is not configured.');
    redirect('../auth/login.php');
}

$redirect_uri = base_url() . '/Pollnxt2/poll-system/auth/google_callback.php';

$token = http_post_form('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code',
]);

$access_token = (string)($token['access_token'] ?? '');
if ($access_token === '') {
    set_flash('error', 'Google login failed (token).');
    redirect('../auth/login.php');
}

$profile = http_get_json('https://www.googleapis.com/oauth2/v2/userinfo', $access_token);
$google_id = (string)($profile['id'] ?? '');
$email = (string)($profile['email'] ?? '');
$name = (string)($profile['name'] ?? 'Google User');

if ($google_id === '' || $email === '') {
    set_flash('error', 'Google login failed (profile).');
    redirect('../auth/login.php');
}

// Existing by google_id?
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
$stmt->execute([$google_id]);
$user = $stmt->fetch();

if (!$user) {
    // Existing by email? Link account.
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'google' WHERE id = ?");
        $stmt->execute([$google_id, $user['id']]);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
    } else {
        // Create new user (no local password required)
        $random_password = bin2hex(random_bytes(16));
        $hash = password_hash($random_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, google_id, auth_provider) VALUES (?, ?, ?, ?, 'google')");
        $stmt->execute([$name, $email, $hash, $google_id]);
        $id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }
}

if (!$user) {
    set_flash('error', 'Google login failed.');
    redirect('../auth/login.php');
}

if ((int)($user['is_blocked'] ?? 0) === 1) {
    $reason = trim((string)($user['blocked_reason'] ?? ''));
    set_flash('error', $reason !== '' ? $reason : 'Your account is blocked.');
    redirect('../auth/login.php');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['polls_created'] = $user['polls_created'];
$_SESSION['is_paid'] = $user['is_paid'];
$_SESSION['paid_polls_balance'] = (int)($user['paid_polls_balance'] ?? 0);

set_flash('success', 'Logged in with Google.');
redirect('../index.php');

