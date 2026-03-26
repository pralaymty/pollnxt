<?php
$page_title = 'Vote';
require_once __DIR__ . '/../includes/header.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($poll_id <= 0) {
    set_flash('error', 'Invalid poll.');
    redirect('all_polls.php');
}

// Fetch poll
$stmt = $pdo->prepare("SELECT p.*, u.name AS author_name FROM polls p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch();

if (!$poll) {
    set_flash('error', 'Poll not found.');
    redirect('all_polls.php');
}

$is_creator = is_logged_in() && ((int)($_SESSION['user_id'] ?? 0) === (int)$poll['user_id']);
$vote_disabled = ((int)$poll['is_blocked'] === 1) && !$is_creator;

// Fetch options
$stmt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY id");
$stmt->execute([$poll_id]);
$options = $stmt->fetchAll();

// Check if already voted (by IP + session)
$user_ip = get_user_ip();
$stmt = $pdo->prepare("SELECT id FROM votes WHERE poll_id = ? AND user_ip = ?");
$stmt->execute([$poll_id, $user_ip]);
$already_voted = $stmt->fetch() ? true : false;

// Also check session
if (isset($_SESSION['voted_polls']) && in_array($poll_id, $_SESSION['voted_polls'])) {
    $already_voted = true;
}

$errors = [];
$vote_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_voted && !$vote_disabled) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    }

    $option_id = isset($_POST['option_id']) ? (int)$_POST['option_id'] : 0;

    // Validate option belongs to this poll
    $valid = false;
    foreach ($options as $opt) {
        if ((int)$opt['id'] === $option_id) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        $errors[] = 'Please select a valid option.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert vote
            $stmt = $pdo->prepare("INSERT INTO votes (poll_id, option_id, user_ip) VALUES (?, ?, ?)");
            $stmt->execute([$poll_id, $option_id, $user_ip]);

            // Update vote count
            $stmt = $pdo->prepare("UPDATE poll_options SET votes = votes + 1 WHERE id = ?");
            $stmt->execute([$option_id]);

            $pdo->commit();

            // Mark in session
            if (!isset($_SESSION['voted_polls'])) {
                $_SESSION['voted_polls'] = [];
            }
            $_SESSION['voted_polls'][] = $poll_id;

            $already_voted = true;
            $vote_success = true;

            // Refresh options
            $stmt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY id");
            $stmt->execute([$poll_id]);
            $options = $stmt->fetchAll();
        } catch (PDOException $e) {
            $pdo->rollBack();
            // If UNIQUE(poll_id,user_ip) is hit, show a friendly message
            if ($e->getCode() === '23000') {
                $already_voted = true;
                $errors[] = 'You have already voted on this poll from this IP address.';
            } else {
                $errors[] = 'Error recording vote. Please try again.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error recording vote. Please try again.';
        }
    }
}

// Category CSS class
$cat_class      = 'cat-' . strtolower($poll['category']);
$blocked_reason = (string)($poll['blocked_reason'] ?? '');
$img            = $poll['image_path'] ?? '';
$img_src        = $img !== ''
    ? (strncmp($img, 'http', 4) === 0 ? $img : $base . $img)
    : 'https://via.placeholder.com/300x300/00AF91/FFFFFF?text=POLL';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <?php if ($vote_success): ?>
                <div class="alert alert-success flash-message">
                    <i class="fas fa-check-circle me-2"></i>Your vote has been recorded successfully!
                    <a href="result.php?id=<?php echo $poll_id; ?>" class="alert-link ms-2">View Results &rarr;</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo h($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="poll-card-wrap">
                <?php if (!empty($poll['end_date'])): ?>
                    <div class="poll-countdown" data-end="<?php echo h($poll['end_date']); ?>">
                        <i class="fas fa-hourglass-half"></i>
                        <span class="countdown-text"></span>
                    </div>
                <?php endif; ?>
                <div class="card">
                <div class="card-body p-4">
                    <div class="poll-with-image mb-3">
                        <div class="poll-with-image-thumb">
                            <img src="<?php echo h($img_src); ?>" alt="Poll image">
                        </div>
                        <div class="poll-with-image-content">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="category-badge <?php echo $cat_class; ?>"><?php echo h($poll['category']); ?></span>
                                <small class="text-muted"><i class="fas fa-user me-1"></i><?php echo h($poll['author_name']); ?></small>
                            </div>
                            <h4 class="mb-0"><?php echo h($poll['question']); ?></h4>
                            <?php if ((int)$poll['is_blocked'] === 1 && !empty($poll['blocked_reason'])): ?>
                                <div class="alert alert-warning small py-1 px-2 mt-2 mb-0">
                                    <i class="fas fa-ban me-1"></i>This poll is blocked by admin. Reason: <?php echo h($poll['blocked_reason']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($vote_disabled): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-ban me-2"></i>This poll is blocked by admin.
                            <?php if ($blocked_reason !== ''): ?>
                                <div class="small mt-1">Reason: <?php echo h($blocked_reason); ?></div>
                            <?php endif; ?>
                            <a href="result.php?id=<?php echo $poll_id; ?>" class="alert-link ms-2">View Results &rarr;</a>
                        </div>
                    <?php elseif ($already_voted && !$vote_success): ?>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>You have already voted on this poll.
                            <a href="result.php?id=<?php echo $poll_id; ?>" class="alert-link ms-2">View Results &rarr;</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!$already_voted && !$vote_disabled): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                            <?php foreach ($options as $opt): ?>
                                <div class="vote-option" onclick="selectOption(this, <?php echo $opt['id']; ?>)">
                                    <input type="radio" name="option_id" value="<?php echo $opt['id']; ?>" id="opt_<?php echo $opt['id']; ?>" required>
                                    <label for="opt_<?php echo $opt['id']; ?>"><?php echo h($opt['option_text']); ?></label>
                                </div>
                            <?php endforeach; ?>

                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-primary py-2">
                                    <i class="fas fa-vote-yea me-2"></i>Submit Vote
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Show mini results -->
                        <?php
                        $total_votes = array_sum(array_column($options, 'votes'));
                        foreach ($options as $opt):
                            $pct = $total_votes > 0 ? round(($opt['votes'] / $total_votes) * 100) : 0;
                        ?>
                            <div class="result-option">
                                <div class="option-header">
                                    <span class="option-text"><?php echo h($opt['option_text']); ?></span>
                                    <span class="option-votes"><?php echo $opt['votes']; ?> votes (<?php echo $pct; ?>%)</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $pct; ?>%"><?php echo $pct; ?>%</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <p class="text-muted text-center mt-3">Total votes: <?php echo $total_votes; ?></p>
                    <?php endif; ?>
                </div>
                </div><!-- /.card -->
            </div><!-- /.poll-card-wrap -->

            <div class="text-center mt-3">
                <a href="all_polls.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Back to Polls</a>
                <a href="result.php?id=<?php echo $poll_id; ?>" class="btn btn-outline-primary"><i class="fas fa-chart-bar me-2"></i>Full Results</a>
            </div>
        </div>
    </div>
</div>

<script>
function selectOption(el, id) {
    document.querySelectorAll('.vote-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type="radio"]').checked = true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
