<?php
$admin_page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
admin_require_login();

$counts = [
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'polls' => (int)$pdo->query("SELECT COUNT(*) FROM polls")->fetchColumn(),
    'blocked_polls' => (int)$pdo->query("SELECT COUNT(*) FROM polls WHERE is_blocked = 1")->fetchColumn(),
    'admins' => (int)$pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn(),
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1"><i class="fas fa-gauge me-2"></i>Dashboard</h3>
        <div class="text-muted small">
            Logged in as <strong><?php echo admin_h($_SESSION['admin_name'] ?? ''); ?></strong>
            (<?php echo admin_h($_SESSION['admin_role'] ?? ''); ?>)
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card admin-card p-3">
            <div class="text-muted small">Users</div>
            <div class="fs-3 fw-bold"><?php echo $counts['users']; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card admin-card p-3">
            <div class="text-muted small">Polls</div>
            <div class="fs-3 fw-bold"><?php echo $counts['polls']; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card admin-card p-3">
            <div class="text-muted small">Blocked polls</div>
            <div class="fs-3 fw-bold"><?php echo $counts['blocked_polls']; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card admin-card p-3">
            <div class="text-muted small">Admin users</div>
            <div class="fs-3 fw-bold"><?php echo $counts['admins']; ?></div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <h5 class="mb-2">Next steps</h5>
        <div class="text-muted">
            I’m adding menu + management pages next: categories, polls (block/reason + trending), users (block), super-admin admin-user management, and settings (Google client id/secret).
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

