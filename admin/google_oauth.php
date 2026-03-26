<?php
// Guard before any HTML output
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_super();

$admin_page_title = 'Google OAuth';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$success = null;

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $client_id = trim($_POST['google_client_id'] ?? '');
        $client_secret = trim($_POST['google_client_secret'] ?? '');

        if ($client_id === '' || $client_secret === '') {
            $errors[] = 'Google Client ID and Client Secret are required.';
        } else {
            set_setting($pdo, 'google_client_id', $client_id);
            set_setting($pdo, 'google_client_secret', $client_secret);
            $success = 'Google OAuth settings saved.';
        }
    }
}

$client_id = get_setting($pdo, 'google_client_id');
$client_secret = get_setting($pdo, 'google_client_secret');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fab fa-google me-2"></i>Google OAuth</h3>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo admin_h($success); ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?php echo admin_h($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card p-3">
    <h5 class="mb-3">Main Website</h5>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">

        <div class="mb-2">
            <label class="form-label">Google Client ID</label>
            <input class="form-control" name="google_client_id" required value="<?php echo admin_h($client_id); ?>">
        </div>
        <div class="mb-2">
            <label class="form-label">Google Client Secret</label>
            <input class="form-control" name="google_client_secret" required value="<?php echo admin_h($client_secret); ?>">
            <div class="text-muted small mt-1">Stored in DB settings table.</div>
        </div>

        <button class="btn btn-primary mt-2" type="submit"><i class="fas fa-save me-2"></i>Save</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

