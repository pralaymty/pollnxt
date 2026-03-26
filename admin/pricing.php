<?php
// Master admin only
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_super();

$admin_page_title = 'Pricing';
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
    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([$key, $value]);
}

$defaults = [
    'pricing_free_polls' => '10',
    'pricing_basic_price' => '10.00',
    'pricing_basic_add_polls' => '20',
    'pricing_advance_price' => '20.00',
    'pricing_advance_add_polls' => '50',
    'pricing_business_price' => '30.00',
    'pricing_business_add_polls' => '100',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $freePolls = trim((string)($_POST['free_polls'] ?? ''));
        $basicPrice = trim((string)($_POST['basic_price'] ?? ''));
        $basicAdd = trim((string)($_POST['basic_add_polls'] ?? ''));
        $advancePrice = trim((string)($_POST['advance_price'] ?? ''));
        $advanceAdd = trim((string)($_POST['advance_add_polls'] ?? ''));
        $businessPrice = trim((string)($_POST['business_price'] ?? ''));
        $businessAdd = trim((string)($_POST['business_add_polls'] ?? ''));

        // Validate ints
        $freePollsInt = filter_var($freePolls, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $basicAddInt = filter_var($basicAdd, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $advanceAddInt = filter_var($advanceAdd, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $businessAddInt = filter_var($businessAdd, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        // Validate prices
        $basicPriceFloat = filter_var($basicPrice, FILTER_VALIDATE_FLOAT);
        $advancePriceFloat = filter_var($advancePrice, FILTER_VALIDATE_FLOAT);
        $businessPriceFloat = filter_var($businessPrice, FILTER_VALIDATE_FLOAT);

        if ($freePollsInt === false || $basicAddInt === false || $advanceAddInt === false || $businessAddInt === false) {
            $errors[] = 'Poll quota values must be valid non-negative integers.';
        }
        if ($basicPriceFloat === false || $advancePriceFloat === false || $businessPriceFloat === false) {
            $errors[] = 'Price values must be valid numbers.';
        }

        if (empty($errors)) {
            // Store prices with 2 decimals
            $basicPriceStore = number_format((float)$basicPriceFloat, 2, '.', '');
            $advancePriceStore = number_format((float)$advancePriceFloat, 2, '.', '');
            $businessPriceStore = number_format((float)$businessPriceFloat, 2, '.', '');

            set_setting($pdo, 'pricing_free_polls', (string)$freePollsInt);
            set_setting($pdo, 'pricing_basic_price', $basicPriceStore);
            set_setting($pdo, 'pricing_basic_add_polls', (string)$basicAddInt);
            set_setting($pdo, 'pricing_advance_price', $advancePriceStore);
            set_setting($pdo, 'pricing_advance_add_polls', (string)$advanceAddInt);
            set_setting($pdo, 'pricing_business_price', $businessPriceStore);
            set_setting($pdo, 'pricing_business_add_polls', (string)$businessAddInt);

            $success = 'Pricing settings saved.';
        }
    }
}

$freePolls = (int)get_setting($pdo, 'pricing_free_polls', $defaults['pricing_free_polls']);
$basicPrice = get_setting($pdo, 'pricing_basic_price', $defaults['pricing_basic_price']);
$basicAdd = (int)get_setting($pdo, 'pricing_basic_add_polls', $defaults['pricing_basic_add_polls']);
$advancePrice = get_setting($pdo, 'pricing_advance_price', $defaults['pricing_advance_price']);
$advanceAdd = (int)get_setting($pdo, 'pricing_advance_add_polls', $defaults['pricing_advance_add_polls']);
$businessPrice = get_setting($pdo, 'pricing_business_price', $defaults['pricing_business_price']);
$businessAdd = (int)get_setting($pdo, 'pricing_business_add_polls', $defaults['pricing_business_add_polls']);

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-coins me-2"></i>Pricing</h3>
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
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">

        <style>
            .pricing-plan-card {
                border-radius: 1rem;
                border: 1px solid rgba(15,23,42,.10);
                padding: 1rem;
                height: 100%;
                background: #ffffff;
                box-shadow: 0 10px 25px rgba(15,23,42,.05);
            }
            .pricing-plan-head {
                border-radius: .85rem;
                padding: .55rem .75rem;
                font-weight: 800;
                color: #0b1220;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .5rem;
                margin-bottom: .9rem;
            }
            .pricing-plan-body label { font-weight: 600; color: rgba(15,23,42,.85); }
            .pricing-free .pricing-plan-head { background: rgba(0,175,145,.16); border: 1px solid rgba(0,175,145,.25); }
            .pricing-basic .pricing-plan-head { background: rgba(59,130,246,.16); border: 1px solid rgba(59,130,246,.25); }
            .pricing-advance .pricing-plan-head { background: rgba(168,85,247,.16); border: 1px solid rgba(168,85,247,.25); }
            .pricing-business .pricing-plan-head { background: rgba(245,158,11,.18); border: 1px solid rgba(245,158,11,.28); }
            .pricing-free  { background: linear-gradient(180deg, rgba(0,175,145,.08), #ffffff); }
            .pricing-basic { background: linear-gradient(180deg, rgba(59,130,246,.08), #ffffff); }
            .pricing-advance { background: linear-gradient(180deg, rgba(168,85,247,.08), #ffffff); }
            .pricing-business { background: linear-gradient(180deg, rgba(245,158,11,.10), #ffffff); }
        </style>

        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <div class="pricing-plan-card pricing-free">
                    <div class="pricing-plan-head">
                        <span><i class="fas fa-leaf me-2" style="color:#00AF91;"></i>Free</span>
                    </div>
                    <div class="pricing-plan-body">
                        <label class="form-label">Free polls (included)</label>
                        <input class="form-control" type="number" min="0" name="free_polls" value="<?php echo (int)$freePolls; ?>" required>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="pricing-plan-card pricing-basic">
                    <div class="pricing-plan-head">
                        <span><i class="fas fa-cube me-2" style="color:#3b82f6;"></i>Basic</span>
                    </div>
                    <div class="pricing-plan-body">
                        <label class="form-label">Price</label>
                        <input class="form-control mb-2" type="text" name="basic_price" value="<?php echo admin_h((string)$basicPrice); ?>" required>
                        <label class="form-label">Add polls</label>
                        <input class="form-control" type="number" min="0" name="basic_add_polls" value="<?php echo (int)$basicAdd; ?>" required>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="pricing-plan-card pricing-advance">
                    <div class="pricing-plan-head">
                        <span><i class="fas fa-layer-group me-2" style="color:#a855f7;"></i>Advance</span>
                    </div>
                    <div class="pricing-plan-body">
                        <label class="form-label">Price</label>
                        <input class="form-control mb-2" type="text" name="advance_price" value="<?php echo admin_h((string)$advancePrice); ?>" required>
                        <label class="form-label">Add polls</label>
                        <input class="form-control" type="number" min="0" name="advance_add_polls" value="<?php echo (int)$advanceAdd; ?>" required>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="pricing-plan-card pricing-business">
                    <div class="pricing-plan-head">
                        <span><i class="fas fa-briefcase me-2" style="color:#f59e0b;"></i>Business</span>
                    </div>
                    <div class="pricing-plan-body">
                        <label class="form-label">Price</label>
                        <input class="form-control mb-2" type="text" name="business_price" value="<?php echo admin_h((string)$businessPrice); ?>" required>
                        <label class="form-label">Add polls</label>
                        <input class="form-control" type="number" min="0" name="business_add_polls" value="<?php echo (int)$businessAdd; ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">
                <i class="fas fa-save me-2"></i>Save Pricing
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

