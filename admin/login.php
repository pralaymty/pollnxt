<?php
// Handle login BEFORE sending any HTML output
require_once __DIR__ . '/includes/bootstrap.php';

if (admin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$admin_page_title = 'Login';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please fill in all fields.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            if ((int)$admin['is_blocked'] === 1) {
                $reason = trim((string)($admin['blocked_reason'] ?? ''));
                $errors[] = $reason !== '' ? $reason : 'Your admin account is blocked.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                set_flash('success', 'Welcome, ' . $admin['name'] . '!');
                header('Location: index.php');
                exit;
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

// Now render layout
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card admin-card p-4">
            <div class="text-center mb-3">
                <div class="mb-2" style="font-size: 2.2rem;">
                    <i class="fas fa-shield-halved" style="color: var(--primary);"></i>
                </div>
                <h4 class="mb-0">Admin Console</h4>
                <small class="text-muted">Secure access to POLLNXT controls</small>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo admin_h($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required value="<?php echo admin_h($email); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">
                    <i class="fas fa-right-to-bracket me-2"></i>Login
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

