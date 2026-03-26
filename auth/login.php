<?php
$page_title = 'Login';
require_once __DIR__ . '/../includes/header.php';

if (is_logged_in()) {
    redirect('../index.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please fill in all fields.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ((int)($user['is_blocked'] ?? 0) === 1) {
                $reason = trim((string)($user['blocked_reason'] ?? ''));
                $errors[] = $reason !== '' ? $reason : 'Your account is blocked.';
            } else {
            // Regenerate session ID for security
            session_regenerate_id(true);

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['polls_created'] = $user['polls_created'];
            $_SESSION['paid_polls_balance'] = (int)($user['paid_polls_balance'] ?? 0);
            $_SESSION['is_paid']       = $user['is_paid'];

            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('../index.php');
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>

<div class="container">
    <div class="auth-container">
        <div class="card">
            <h2><i class="fas fa-sign-in-alt me-2"></i>Login</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo h($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo h($email); ?>" required placeholder="Enter your email">
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Enter your password">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>

                <div class="text-center my-3 text-muted small">or</div>

                <a class="btn btn-outline-primary w-100 py-2" href="google.php">
                    <i class="fab fa-google me-2"></i>Continue with Google
                </a>

                <p class="text-center mt-3 mb-0">
                    Don't have an account? <a href="register.php" class="text-decoration-none" style="color: var(--primary);">Register here</a>
                </p>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
