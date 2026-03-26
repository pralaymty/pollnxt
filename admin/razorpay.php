<?php
// Guard before any HTML output
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_super();

$admin_page_title = 'Razorpay';
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

$defaults = [
    'razorpay_api_key_live' => 'rzp_live_SInJJaSi4hNy9m',
    'razorpay_secret_key_live' => 'eIXygoHZM6wdws7UXQvN685T',
    'razorpay_api_key_test' => 'rzp_test_RhhfTyVslyDnpo',
    'razorpay_secret_key_test' => 'UzLKpmGc47WxDalGktKk5eBP',
];

$mode = get_setting($pdo, 'razorpay_mode', 'test');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $mode = ($_POST['mode'] ?? 'test') === 'live' ? 'live' : 'test';
        $liveApi = trim($_POST['api_key_live'] ?? '');
        $liveSecret = trim($_POST['secret_key_live'] ?? '');
        $testApi = trim($_POST['api_key_test'] ?? '');
        $testSecret = trim($_POST['secret_key_test'] ?? '');

        if ($liveApi === '' || $liveSecret === '' || $testApi === '' || $testSecret === '') {
            $errors[] = 'All Razorpay keys (live + test) are required.';
        } else {
            set_setting($pdo, 'razorpay_mode', $mode);
            set_setting($pdo, 'razorpay_api_key_live', $liveApi);
            set_setting($pdo, 'razorpay_secret_key_live', $liveSecret);
            set_setting($pdo, 'razorpay_api_key_test', $testApi);
            set_setting($pdo, 'razorpay_secret_key_test', $testSecret);
            $success = 'Razorpay settings saved.';
        }
    }
}

$apiKeyLive = get_setting($pdo, 'razorpay_api_key_live', $defaults['razorpay_api_key_live']);
$secretKeyLive = get_setting($pdo, 'razorpay_secret_key_live', $defaults['razorpay_secret_key_live']);
$apiKeyTest = get_setting($pdo, 'razorpay_api_key_test', $defaults['razorpay_api_key_test']);
$secretKeyTest = get_setting($pdo, 'razorpay_secret_key_test', $defaults['razorpay_secret_key_test']);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-credit-card me-2"></i>Razorpay</h3>
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
    <h5 class="mb-3">Payment Mode</h5>
    <form method="POST" action="" class="mb-0">
        <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Live Mode</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="radio" name="mode" value="live" id="mode_live" <?php echo $mode === 'live' ? 'checked' : ''; ?>>
                    <label for="mode_live" class="mb-0">Use Live Keys</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Test Mode</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="radio" name="mode" value="test" id="mode_test" <?php echo $mode !== 'live' ? 'checked' : ''; ?>>
                    <label for="mode_test" class="mb-0">Use Test Keys</label>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">API Key and Secret Key</h5>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Live api_key (key_id)</label>
                <input class="form-control" name="api_key_live" value="<?php echo admin_h($apiKeyLive); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Live secret_key (secret)</label>
                <input class="form-control" name="secret_key_live" value="<?php echo admin_h($secretKeyLive); ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Test api_key (key_id)</label>
                <input class="form-control" name="api_key_test" value="<?php echo admin_h($apiKeyTest); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Test secret_key (secret)</label>
                <input class="form-control" name="secret_key_test" value="<?php echo admin_h($secretKeyTest); ?>" required>
            </div>
        </div>

        <button class="btn btn-primary mt-3" type="submit"><i class="fas fa-save me-2"></i>Save</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

