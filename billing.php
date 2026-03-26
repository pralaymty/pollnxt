<?php
$page_title = 'Billing Plan';
require_once __DIR__ . '/config/db.php';

if (!is_logged_in()) {
    set_flash('error', 'Please login to view billing plans.');
    redirect('auth/login.php');
}

$pollsCreated = (int)($_SESSION['polls_created'] ?? 0);
$paidBalance = (int)($_SESSION['paid_polls_balance'] ?? 0);

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

$freePolls = (int)get_setting($pdo, 'pricing_free_polls', '10');
if ($freePolls <= 0) $freePolls = 10;

$basicPrice = (float)get_setting($pdo, 'pricing_basic_price', '10.00');
$basicAdd = (int)get_setting($pdo, 'pricing_basic_add_polls', '20');
if ($basicAdd <= 0) $basicAdd = 20;

$advancePrice = (float)get_setting($pdo, 'pricing_advance_price', '20.00');
$advanceAdd = (int)get_setting($pdo, 'pricing_advance_add_polls', '50');
if ($advanceAdd <= 0) $advanceAdd = 50;

$businessPrice = (float)get_setting($pdo, 'pricing_business_price', '30.00');
$businessAdd = (int)get_setting($pdo, 'pricing_business_add_polls', '100');
if ($businessAdd <= 0) $businessAdd = 100;

$totalQuota = $freePolls + $paidBalance;
$remaining = max(0, $totalQuota - $pollsCreated);

function plan_label_from_balance(int $paidBalance, int $basicAdd, int $advanceAdd, int $businessAdd): string {
    if ($paidBalance >= $businessAdd) return 'Business';
    if ($paidBalance >= $advanceAdd) return 'Advance';
    if ($paidBalance >= $basicAdd) return 'Basic';
    return 'Free';
}

$currentPlan = plan_label_from_balance($paidBalance, $basicAdd, $advanceAdd, $businessAdd);

$plans = [
    'free' => ['name' => 'Free', 'price' => 0.00, 'add_polls' => 0, 'total_includes' => $freePolls],
    'basic' => ['name' => 'Basic', 'price' => $basicPrice, 'add_polls' => $basicAdd],
    'advance' => ['name' => 'Advance', 'price' => $advancePrice, 'add_polls' => $advanceAdd],
    'business' => ['name' => 'Business', 'price' => $businessPrice, 'add_polls' => $businessAdd],
];
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .billing-plan-card {
        border-radius: 1rem;
        border: 1px solid rgba(15,23,42,.10);
        padding: 1rem;
        height: 100%;
        background: #ffffff;
        box-shadow: 0 10px 25px rgba(15,23,42,.05);
        position: relative;
    }
    .billing-plan-head {
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
    .billing-plan-body label { font-weight: 600; color: rgba(15,23,42,.85); }

    .billing-free .billing-plan-head { background: rgba(0,175,145,.16); border: 1px solid rgba(0,175,145,.25); }
    .billing-basic .billing-plan-head { background: rgba(59,130,246,.16); border: 1px solid rgba(59,130,246,.25); }
    .billing-advance .billing-plan-head { background: rgba(168,85,247,.16); border: 1px solid rgba(168,85,247,.25); }
    .billing-business .billing-plan-head { background: rgba(245,158,11,.18); border: 1px solid rgba(245,158,11,.28); }

    .billing-free { background: linear-gradient(180deg, rgba(0,175,145,.08), #ffffff); }
    .billing-basic { background: linear-gradient(180deg, rgba(59,130,246,.08), #ffffff); }
    .billing-advance { background: linear-gradient(180deg, rgba(168,85,247,.08), #ffffff); }
    .billing-business { background: linear-gradient(180deg, rgba(245,158,11,.10), #ffffff); }

    .billing-cta-free { background: #00AF91; border-color: #00AF91; }
    .billing-cta-basic { background: #3b82f6; border-color: #3b82f6; }
    .billing-cta-advance { background: #a855f7; border-color: #a855f7; }
    .billing-cta-business { background: #f59e0b; border-color: #f59e0b; }

    /* Highlight poll quota numbers (e.g., "20 more polls") */
    .billing-quota-highlight {
        display: inline-block;
        padding: .15rem .55rem;
        border-radius: 999px;
        font-weight: 900;
        color: #0b1220;
        background: rgba(0,175,145,.16);
        border: 1px solid rgba(0,175,145,.25);
    }
    .billing-basic .billing-quota-highlight {
        background: rgba(59,130,246,.16);
        border-color: rgba(59,130,246,.25);
    }
    .billing-advance .billing-quota-highlight {
        background: rgba(168,85,247,.16);
        border-color: rgba(168,85,247,.25);
    }
    .billing-business .billing-quota-highlight {
        background: rgba(245,158,11,.18);
        border-color: rgba(245,158,11,.30);
    }

    /* Animated "Most Popular" tag */
    .billing-popular-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #e17055, #d63031);
        color: #fff;
        padding: 0.2rem 0.7rem;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .02em;
        box-shadow: 0 10px 25px rgba(214,48,49,.25);
        z-index: 2;
    }
    @keyframes billingPulseHot {
        0%   { transform: translateY(0) scale(1); box-shadow: 0 10px 25px rgba(214,48,49,.25); }
        50%  { transform: translateY(-1px) scale(1.03); box-shadow: 0 14px 35px rgba(214,48,49,.35); }
        100% { transform: translateY(0) scale(1); box-shadow: 0 10px 25px rgba(214,48,49,.25); }
    }
    .billing-trending-animate {
        animation: billingPulseHot 1.25s infinite ease-in-out;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-crown me-2" style="color: var(--primary);"></i>Billing Plan</h3>
            <div class="text-muted small">
                Polls created: <?php echo $pollsCreated; ?> / <?php echo $totalQuota; ?>
                (Remaining: <?php echo $remaining; ?>)
                &bull; Current: <strong><?php echo h($currentPlan); ?></strong>
            </div>
        </div>
        <a href="pages/my_polls.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-list-check me-2"></i>My Polls
        </a>
    </div>

    <div class="row g-3">
        <?php foreach (['free','basic','advance','business'] as $code):
            $p = $plans[$code];
            $selected = ($code === 'free' && $paidBalance <= 0) ||
                        ($code === 'basic' && $paidBalance >= $basicAdd && $paidBalance < $advanceAdd) ||
                        ($code === 'advance' && $paidBalance >= $advanceAdd && $paidBalance < $businessAdd) ||
                        ($code === 'business' && $paidBalance >= $businessAdd);
            $payNow = $code !== 'free';
        ?>
            <div class="col-md-6 col-lg-3">
                <div class="billing-plan-card billing-<?php echo h($code); ?> h-100">
                    <?php if ($code === 'advance'): ?>
                        <div class="billing-popular-badge billing-trending-animate">
                            <i class="fas fa-fire me-1"></i>Most Popular
                        </div>
                    <?php endif; ?>
                    <div class="billing-plan-head">
                        <div class="fw-semibold">
                            <?php if ($code === 'free'): ?>
                                <i class="fas fa-leaf me-2" style="color:#00AF91;"></i>
                            <?php elseif ($code === 'basic'): ?>
                                <i class="fas fa-cube me-2" style="color:#3b82f6;"></i>
                            <?php elseif ($code === 'advance'): ?>
                                <i class="fas fa-layer-group me-2" style="color:#a855f7;"></i>
                            <?php else: ?>
                                <i class="fas fa-briefcase me-2" style="color:#f59e0b;"></i>
                            <?php endif; ?>
                            <?php echo h($p['name']); ?>
                        </div>
                        <?php if ($selected): ?>
                            <span class="badge text-bg-success">Selected</span>
                        <?php endif; ?>
                    </div>

                    <div class="billing-plan-body mt-2">
                        <div class="price" style="font-size: 2rem; color: #0b1220; font-weight: 900;">
                            <?php echo $code === 'free' ? '₹0' : '₹' . number_format((float)$p['price'], 2); ?>
                        </div>
                        <div class="text-muted small">
                            <?php if ($code === 'free'): ?>
                                Includes <?php echo (int)$freePolls; ?> free polls
                            <?php else: ?>
                                Adds <span class="billing-quota-highlight"><?php echo (int)$p['add_polls']; ?></span> more polls
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <ul class="list-unstyled text-start mb-3">
                            <?php if ($code === 'free'): ?>
                                <li><i class="fas fa-check-circle me-2" style="color: #00b894;"></i><?php echo (int)$freePolls; ?> free polls</li>
                            <?php else: ?>
                                <li><i class="fas fa-check-circle me-2" style="color: #00b894;"></i>Priority quota boost</li>
                                <li><i class="fas fa-check-circle me-2" style="color: #00b894;"></i>Valid as long as credits remain</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <?php if ($payNow): ?>
                        <a class="btn w-100 <?php echo $code === 'free' ? 'btn-primary' : 'btn'; ?> <?php echo $code === 'basic' ? 'billing-cta-basic' : ($code === 'advance' ? 'billing-cta-advance' : ($code === 'business' ? 'billing-cta-business' : 'billing-cta-free')); ?>"
                           href="payment.php?plan=<?php echo h($code); ?>">
                            <i class="fas fa-lock me-2"></i>Pay Now
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary w-100" disabled>
                            Current Plan
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

