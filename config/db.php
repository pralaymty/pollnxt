<?php
/**
 * Database Configuration - AI Poll Creator
 * PDO connection with exception mode
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'poll_system2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token
 */
function validate_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output for XSS protection
 */
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function mask_name(string $name): string {
    $name = trim($name);
    if ($name === '') return '';
    $len = mb_strlen($name);
    if ($len <= 2) {
        return mb_substr($name, 0, 1) . '*';
    }
    return mb_substr($name, 0, 1) . str_repeat('*', max(2, $len - 2)) . mb_substr($name, -1);
}

/**
 * Get user IP address
 */
function get_user_ip(): string {
    // Trust only REMOTE_ADDR by default.
    // Forwarded headers can be spoofed unless you are behind a trusted proxy.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect helper
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'other';
}

function generate_poll_svg(string $question, string $category): string {
    $q = trim($question);
    $c = trim($category);
    $title = mb_substr($q !== '' ? $q : 'Poll', 0, 60);
    $cat = mb_substr($c !== '' ? $c : 'General', 0, 24);
    $h1 = h($title);
    $h2 = h($cat);
    $seed = crc32($q . '|' . $c);
    $a = sprintf('#%06X', ($seed & 0xFFFFFF));
    $b = sprintf('#%06X', ((int)(($seed * 2654435761) & 0xFFFFFF)));

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="$a"/>
      <stop offset="1" stop-color="$b"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#g)"/>
  <rect x="70" y="70" width="1060" height="490" rx="28" fill="rgba(255,255,255,0.14)"/>
  <text x="120" y="200" fill="#ffffff" font-size="34" font-family="Segoe UI, Arial, sans-serif" opacity="0.9">$h2</text>
  <text x="120" y="270" fill="#ffffff" font-size="56" font-family="Segoe UI, Arial, sans-serif" font-weight="700">$h1</text>
  <text x="120" y="520" fill="#ffffff" font-size="28" font-family="Segoe UI, Arial, sans-serif" opacity="0.85">POLLNXT</text>
</svg>
SVG;
}

function save_poll_image(?array $file, string $question, string $category): ?string {
    $baseDir = __DIR__ . '/../uploads/polls';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }

    // If file uploaded, validate and store (<= 1MB)
    if ($file && isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int)$file['size'] > 1024 * 1024) {
            return null;
        }

        $tmp = $file['tmp_name'] ?? '';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $tmp !== '' ? $finfo->file($tmp) : '';
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => '',
        };
        if ($ext === '') {
            return null;
        }

        $name = 'poll_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $baseDir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return null;
        }
        return 'uploads/polls/' . $name;
    }

    // Otherwise generate an SVG placeholder
    $svg = generate_poll_svg($question, $category);
    $name = 'poll_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.svg';
    $dest = $baseDir . '/' . $name;
    file_put_contents($dest, $svg);
    return 'uploads/polls/' . $name;
}

// ── Migration: add end_date column to polls if not yet present ──
try {
    $chk = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'polls'
           AND column_name = 'end_date'"
    );
    if ((int)$chk->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE polls ADD COLUMN end_date DATE DEFAULT NULL");
    }
} catch (Exception $e) { /* silently skip if already exists */ }

/**
 * Set flash message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
