<?php
$page_title = 'Razorpay Payment';
require_once __DIR__ . '/config/db.php';

if (!is_logged_in()) {
    set_flash('error', 'Please login to access payment.');
    redirect('auth/login.php');
}

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

$errors = [];

$plan = trim((string)($_GET['plan'] ?? ''));

$basicPrice = (float)get_setting($pdo, 'pricing_basic_price', '10.00');
$basicAdd = (int)get_setting($pdo, 'pricing_basic_add_polls', '20');
$advancePrice = (float)get_setting($pdo, 'pricing_advance_price', '20.00');
$advanceAdd = (int)get_setting($pdo, 'pricing_advance_add_polls', '50');
$businessPrice = (float)get_setting($pdo, 'pricing_business_price', '30.00');
$businessAdd = (int)get_setting($pdo, 'pricing_business_add_polls', '100');

if ($basicAdd <= 0) $basicAdd = 20;
if ($advanceAdd <= 0) $advanceAdd = 50;
if ($businessAdd <= 0) $businessAdd = 100;

$planMap = [
    'basic' => ['name' => 'Basic', 'price' => $basicPrice, 'add_polls' => $basicAdd],
    'advance' => ['name' => 'Advance', 'price' => $advancePrice, 'add_polls' => $advanceAdd],
    'business' => ['name' => 'Business', 'price' => $businessPrice, 'add_polls' => $businessAdd],
];

if ($plan === '' || !isset($planMap[$plan])) {
    $errors[] = 'Invalid plan.';
}

if (!$errors) {
    // Read Razorpay configuration from admin settings
    $mode = get_setting($pdo, 'razorpay_mode', 'test');
    if ($mode !== 'live') $mode = 'test';

    if ($mode === 'live') {
        $apiKey = get_setting($pdo, 'razorpay_api_key_live');
        $secretKey = get_setting($pdo, 'razorpay_secret_key_live');
    } else {
        $apiKey = get_setting($pdo, 'razorpay_api_key_test');
        $secretKey = get_setting($pdo, 'razorpay_secret_key_test');
    }

    if ($apiKey === '' || $secretKey === '') {
        $errors[] = 'Razorpay is not configured.';
    }
}

$orderId = '';
$amountPaise = 0;
$checkoutOptions = null;

if (!$errors) {
    $planInfo = $planMap[$plan];
    $amountPaise = (int)round($planInfo['price'] * 100);

    // Create Razorpay order server-side
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':' . $secretKey);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'amount' => (string)$amountPaise,
        'currency' => 'INR',
        'receipt' => 'pollnxt_' . $_SESSION['user_id'] . '_' . time(),
        'payment_capture' => 1,
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = $resp ? json_decode($resp, true) : null;
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($json) || empty($json['id'])) {
        $errors[] = 'Could not create Razorpay order. Please try again.';
    } else {
        $orderId = (string)$json['id'];

        // Save pending payment details in session for verification step
        $_SESSION['pending_payment'] = [
            'plan' => $plan,
            'plan_name' => $planInfo['name'],
            'add_polls' => (int)$planInfo['add_polls'],
            'price' => (float)$planInfo['price'],
            'amount_paise' => $amountPaise,
            'mode' => $mode,
            'order_id' => $orderId,
        ];

        $prefillName = $_SESSION['user_name'] ?? '';
        $prefillEmail = $_SESSION['user_email'] ?? '';
        $checkoutOptions = [
            'key' => $apiKey,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'name' => 'POLLNXT',
            'description' => 'Unlock more poll credits',
            'order_id' => $orderId,
            'prefill' => ['name' => $prefillName, 'email' => $prefillEmail],
            'theme' => ['color' => '#00AF91'],
        ];
    }
}
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card p-4">
                <h4 class="mb-3"><i class="fas fa-credit-card me-2" style="color: var(--primary);"></i>Razorpay Checkout</h4>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo h($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p class="text-muted">
                        Opening secure payment... If it doesn't open automatically, click the button below.
                    </p>

                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <button id="rzpPayBtn" class="btn btn-primary">
                            <i class="fas fa-lock me-2"></i>Pay with Razorpay
                        </button>
                        <a href="pages/my_polls.php" class="btn btn-outline-primary">
                            <i class="fas fa-list-check me-2"></i>Back to My Polls
                        </a>
                    </div>

                    <form id="payment-form" action="payment_verify.php" method="POST" class="mt-3">
                        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" value="">
                        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="<?php echo h($orderId); ?>">
                        <input type="hidden" name="razorpay_signature" id="razorpay_signature" value="">
                    </form>

                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                        const options = <?php echo json_encode($checkoutOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
                        options.handler = function (response) {
                            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                            document.getElementById('razorpay_signature').value = response.razorpay_signature;
                            document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                            document.getElementById('payment-form').submit();
                        };
                        const rzp = new Razorpay(options);

                        const openCheckout = () => rzp.open();
                        document.getElementById('rzpPayBtn').addEventListener('click', openCheckout);
                        // Auto-open (some browsers may block; user can click button)
                        setTimeout(openCheckout, 600);

                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
