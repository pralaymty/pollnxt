<?php
$page_title = 'Create Poll';
require_once __DIR__ . '/../includes/header.php';

// Must be logged in
if (!is_logged_in()) {
    set_flash('error', 'Please login to create a poll.');
    redirect('../auth/login.php');
}

// Check poll limit — refresh from DB
// paid_polls_balance might not exist in older DB imports yet.
$userId = (int)($_SESSION['user_id'] ?? 0);
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
    $stmt = $pdo->prepare("SELECT polls_created, paid_polls_balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT polls_created FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

$_SESSION['polls_created'] = (int)($user['polls_created'] ?? 0);
$_SESSION['paid_polls_balance'] = $hasPaidBalance ? (int)($user['paid_polls_balance'] ?? 0) : 0;

// Pricing: free quota (default 10)
$freePolls = 10;
try {
    $stmtFree = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmtFree->execute(['pricing_free_polls']);
    $freePolls = (int)($stmtFree->fetchColumn() ?? 10);
    if ($freePolls <= 0) $freePolls = 10;
} catch (Exception $e) {
    $freePolls = 10;
}

$basicAdd = 20;
$advanceAdd = 50;
$businessAdd = 100;
try {
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

// If limit reached and not paid, redirect to payment
// Don't redirect here (headers may be sent by header.php). Instead show a popup on submit.
$limitReached = ((int)($_SESSION['polls_created'] ?? 0) >= $freePolls && (int)($_SESSION['paid_polls_balance'] ?? 0) <= 0);

$categories = ['Technology', 'Sports', 'Politics', 'Education', 'Entertainment', 'Other'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side guard: free quota exceeded and no paid credits
    if ($limitReached) {
        $errors[] = 'You have reached the free poll limit (' . (int)$freePolls . '). Please choose a billing plan to create more polls.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    }

    $category   = trim($_POST['category'] ?? '');
    $question   = trim($_POST['question'] ?? '');
    $options    = $_POST['options'] ?? [];
    $image_file = $_FILES['poll_image'] ?? null;
    $end_date   = trim($_POST['end_date'] ?? '');

    // Validate end_date: if provided, must not be in the past
    if ($end_date !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) || $end_date < date('Y-m-d')) {
            $errors[] = 'End date must be today or a future date.';
        }
    } else {
        $end_date = null;
    }

    // Validate
    if (empty($category) || !in_array($category, $categories)) {
        $errors[] = 'Please select a valid category.';
    }
    if (empty($question) || strlen($question) < 5) {
        $errors[] = 'Poll question must be at least 5 characters.';
    }

    // Filter empty options
    $options = array_values(array_filter(array_map('trim', $options), function ($o) {
        return $o !== '';
    }));

    if (count($options) < 2) {
        $errors[] = 'Please add at least 2 options.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $decrementPaid = ((int)$user['polls_created'] >= $freePolls);

            // Save poll image (upload or auto-generated)
            $image_path = save_poll_image(is_array($image_file) ? $image_file : null, $question, $category);
            if ($image_path === null) {
                throw new Exception('Invalid image upload');
            }

            // Insert poll
            $stmt = $pdo->prepare("INSERT INTO polls (user_id, category, question, image_path, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $category, $question, $image_path, $end_date]);
            $poll_id = $pdo->lastInsertId();

            // Insert options
            $optStmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
            foreach ($options as $i => $opt_text) {
                $optStmt->execute([$poll_id, $opt_text]);
            }

            // Increment polls_created
            $stmt = $pdo->prepare("UPDATE users SET polls_created = polls_created + 1 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['polls_created']++;

            // If user is beyond free quota, consume one paid poll credit
            if ($decrementPaid) {
                $stmt = $pdo->prepare("UPDATE users SET paid_polls_balance = paid_polls_balance - 1 WHERE id = ? AND paid_polls_balance > 0");
                $stmt->execute([$_SESSION['user_id']]);
                if ($stmt->rowCount() <= 0) {
                    $pdo->rollBack();
                    throw new Exception('Insufficient paid poll balance.');
                }
                $_SESSION['paid_polls_balance'] = (int)($_SESSION['paid_polls_balance'] ?? 0) - 1;
            }

            $pdo->commit();

            set_flash('success', 'Poll created successfully!');
            redirect('all_polls.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error creating poll. Please try again.';
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0"><i class="fas fa-plus-circle me-2" style="color: var(--primary);"></i>Create New Poll</h4>
                    <?php
                        $pollsCreated = (int)($_SESSION['polls_created'] ?? 0);
                        $paidLeft = (int)($_SESSION['paid_polls_balance'] ?? 0);
                        $totalQuota = $freePolls + $paidLeft;
                        $remaining = max(0, $totalQuota - $pollsCreated);

                        $planLabel = 'Free';
                        if ($paidLeft >= $businessAdd) {
                            $planLabel = 'Business';
                        } elseif ($paidLeft >= $advanceAdd) {
                            $planLabel = 'Advance';
                        } elseif ($paidLeft >= $basicAdd) {
                            $planLabel = 'Basic';
                        }
                    ?>
                    <small class="text-muted">
                        Polls created: <?php echo $pollsCreated; ?>/<?php echo $totalQuota; ?>
                        <span class="text-muted">| Remaining: <?php echo $remaining; ?></span>
                        <span class="text-muted">| Plan: <?php echo h($planLabel); ?></span>
                    </small>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?>
                                    <li><?php echo h($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="pollForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                        <!-- Category -->
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo h($cat); ?>" <?php echo (isset($category) && $category === $cat) ? 'selected' : ''; ?>>
                                        <?php echo h($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Question -->
                        <div class="mb-3">
                            <label class="form-label">Poll Question</label>
                            <textarea name="question" class="form-control" rows="3" required placeholder="Enter your poll question..."><?php echo h($question ?? ''); ?></textarea>
                        </div>

                        <!-- Image -->
                        <div class="mb-3">
                            <label class="form-label">Poll Image <small class="text-muted">(optional, max 1 MB)</small></label>
                            <input type="file" name="poll_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                            <div class="text-muted small mt-1">If not uploaded, an auto-generated image will be attached.</div>
                        </div>

                        <!-- End Date -->
                        <div class="mb-3">
                            <label class="form-label">Poll End Date <small class="text-muted">(optional)</small></label>
                            <input type="date" name="end_date" id="pollEndDate" class="form-control"
                                   value="<?php echo h($end_date ?? ''); ?>"
                                   placeholder="Select end date">
                            <div class="text-muted small mt-1">Leave blank for no expiry. Past dates are not allowed.</div>
                        </div>

                        <!-- Options -->
                        <div class="mb-3">
                            <label class="form-label">Options <small class="text-muted">(minimum 2)</small></label>
                            <div id="optionsContainer">
                                <div class="option-row">
                                    <input type="text" name="options[]" class="form-control" placeholder="Option 1" required>
                                    <button type="button" class="btn-remove" onclick="removeOption(this)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="option-row">
                                    <input type="text" name="options[]" class="form-control" placeholder="Option 2" required>
                                    <button type="button" class="btn-remove" onclick="removeOption(this)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addOption()">
                                <i class="fas fa-plus me-1"></i>Add Option
                            </button>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary py-2">
                                <i class="fas fa-paper-plane me-2"></i>Create Poll
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set min date to today on the end-date picker
document.addEventListener('DOMContentLoaded', function () {
    var picker = document.getElementById('pollEndDate');
    if (picker) {
        picker.min = new Date().toISOString().split('T')[0];
    }
});

let optionIndex = 2;

function addOption() {
    const container = document.getElementById('optionsContainer');
    const row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML = `
        <input type="text" name="options[]" class="form-control" placeholder="Option ${optionIndex + 1}" required>
        <button type="button" class="btn-remove" onclick="removeOption(this)" title="Remove">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(row);
    optionIndex++;
}

function removeOption(btn) {
    const container = document.getElementById('optionsContainer');
    if (container.children.length > 2) {
        btn.closest('.option-row').remove();
        optionIndex = container.children.length;
    } else {
        alert('Minimum 2 options required.');
    }
}

// Free quota popup
const LIMIT_REACHED = <?php echo $limitReached ? 'true' : 'false'; ?>;
const billingUrl = '../billing.php';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pollForm');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        if (!LIMIT_REACHED) return;
        e.preventDefault();

        const modalEl = document.getElementById('quotaModal');
        if (!modalEl) {
            if (confirm('You reached the free poll limit. Go to billing now?')) {
                window.location.href = billingUrl;
            }
            return;
        }
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    });

    const goBillingBtn = document.getElementById('quotaGoBilling');
    if (goBillingBtn) {
        goBillingBtn.addEventListener('click', () => {
            window.location.href = billingUrl;
        });
    }
});
</script>

<!-- Quota exceeded modal -->
<div class="modal fade" id="quotaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-crown me-2" style="color: var(--primary);"></i>Free quota limit reached</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
            You have reached the free poll limit (<?php echo (int)$freePolls; ?>). Please choose a billing plan to create more polls.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="quotaGoBilling">
                    Go to Billing
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
