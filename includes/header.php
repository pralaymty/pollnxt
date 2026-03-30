<?php
require_once __DIR__ . '/../config/db.php';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>  
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' | ' : ''; ?>POLLNXT</title>
    <link rel="icon" href="https://tentideconsultingservices.com/pollnxt/beta/Backup2/assets/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <?php
    // Determine base path for style.css
    $base = '';
    if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false || strpos($_SERVER['PHP_SELF'], '/auth/') !== false) {
        $base = '../';
    }
    ?>
    <link href="<?php echo $base; ?>style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $base; ?>index.php">
            <img src="https://tentideconsultingservices.com/pollnxt/beta/Backup2/images/logo.png" alt="POLLNXT Logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="<?php echo $base; ?>index.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'all_polls.php' ? 'active' : ''; ?>" href="<?php echo $base; ?>pages/all_polls.php">
                        <i class="fas fa-list me-1"></i>All Polls
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'my_polls.php' ? 'active' : ''; ?>" href="<?php echo $base; ?>pages/my_polls.php">
                            <i class="fas fa-list-check me-1"></i>My Polls
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'create_poll.php' ? 'active' : ''; ?>" href="<?php echo $base; ?>pages/create_poll.php">
                            <i class="fas fa-plus-circle me-1"></i>Create Poll
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo h($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php
                                $userId = (int)($_SESSION['user_id'] ?? 0);
                                $pollsCreated = 0;
                                $paidLeft = 0;
                                $freePolls = 10;
                                $basicAdd = 20;
                                $advanceAdd = 50;
                                $businessAdd = 100;

                                // Pricing settings (with defaults)
                                try {
                                    $stmtFree = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                                    $stmtFree->execute(['pricing_free_polls']);
                                    $freePolls = (int)($stmtFree->fetchColumn() ?? 10);
                                    if ($freePolls <= 0) $freePolls = 10;

                                    $stmtBasic = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                                    $stmtBasic->execute(['pricing_basic_add_polls']);
                                    $basicAdd = (int)($stmtBasic->fetchColumn() ?? 20);
                                    if ($basicAdd <= 0) $basicAdd = 20;

                                    $stmtAdvance = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                                    $stmtAdvance->execute(['pricing_advance_add_polls']);
                                    $advanceAdd = (int)($stmtAdvance->fetchColumn() ?? 50);
                                    if ($advanceAdd <= 0) $advanceAdd = 50;

                                    $stmtBusiness = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                                    $stmtBusiness->execute(['pricing_business_add_polls']);
                                    $businessAdd = (int)($stmtBusiness->fetchColumn() ?? 100);
                                    if ($businessAdd <= 0) $businessAdd = 100;
                                } catch (Exception $e) {
                                    // Keep defaults
                                }

                                // Always refresh counts to avoid stale session values
                                if ($userId > 0) {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM polls WHERE user_id = ?");
                                    $stmt->execute([$userId]);
                                    $pollsCreated = (int)($stmt->fetchColumn() ?? 0);

                                    $hasPaidBalance = false;
                                    try {
                                        $colStmt = $pdo->query(
                                            "SELECT COUNT(*) FROM information_schema.columns
                                             WHERE table_schema = DATABASE()
                                               AND table_name = 'users'
                                               AND column_name = 'paid_polls_balance'"
                                        );
                                        $hasPaidBalance = ((int)($colStmt->fetchColumn() ?? 0) > 0);
                                    } catch (Exception $e) {
                                        $hasPaidBalance = false;
                                    }

                                    if ($hasPaidBalance) {
                                        $stmt2 = $pdo->prepare("SELECT paid_polls_balance FROM users WHERE id = ? LIMIT 1");
                                        $stmt2->execute([$userId]);
                                        $paidLeft = (int)($stmt2->fetchColumn() ?? 0);
                                        $_SESSION['paid_polls_balance'] = $paidLeft;
                                    }
                                    $_SESSION['polls_created'] = $pollsCreated;
                                }

                                $totalQuota = $freePolls + $paidLeft;
                                $planLabel = 'Free';
                                if ($paidLeft >= $businessAdd) {
                                    $planLabel = 'Business';
                                } elseif ($paidLeft >= $advanceAdd) {
                                    $planLabel = 'Advance';
                                } elseif ($paidLeft >= $basicAdd) {
                                    $planLabel = 'Basic';
                                }
                            ?>
                            <li>
                                <span class="dropdown-item-text text-muted small">
                                    Polls: <?php echo $pollsCreated; ?>/<?php echo $totalQuota; ?>
                                    <span class="text-muted"> (<?php echo h($planLabel); ?>)</span>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>" href="<?php echo $base; ?>auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'register.php' ? 'active' : ''; ?>" href="<?php echo $base; ?>auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
<div class="container mt-3">
    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : h($flash['type']); ?> alert-dismissible fade show flash-message" role="alert">
        <?php echo h($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- Layout gutters for ads -->
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-1 d-none d-lg-block ad-gutter"></div>
        <div class="col-lg-10 px-0">
