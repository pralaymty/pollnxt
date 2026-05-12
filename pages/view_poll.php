<?php
require_once __DIR__ . '/../config/db.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vote_flag = isset($_GET['vote']) ? (int)$_GET['vote'] : 0;

if ($poll_id <= 0) {
    set_flash('error', 'Invalid poll.');
    redirect('all_polls.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, u.name AS author_name,
           (SELECT COALESCE(SUM(po.votes), 0) FROM poll_options po WHERE po.poll_id = p.id) AS total_votes
    FROM polls p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch();

if (!$poll) {
    set_flash('error', 'Poll not found.');
    redirect('all_polls.php');
}

$absBaseUrl = app_base_url();
$pollShareUrl = $absBaseUrl . '/pages/view_poll.php?id=' . (int)$poll_id;
$canShareOnFacebook = is_public_share_url($pollShareUrl);

$page_title = mb_substr((string)$poll['question'], 0, 70);
$metaTitle = 'Vote: ' . (string)$poll['question'];
$metaDescription = 'Place your vote on POLLNXT, then use View Details to open the full poll page and results.';
$metaImage = (string)($poll['image_path'] ?? '');
if ($metaImage !== '' && strncmp($metaImage, 'http', 4) !== 0) {
    $metaImage = $absBaseUrl . '/' . ltrim($metaImage, '/');
}
if ($metaImage === '') {
    $metaImage = 'https://tentideconsultingservices.com/pollnxt/beta-v3/images/logo.png';
}
$extra_head = "\n"
    . '    <meta property="og:type" content="website">' . "\n"
    . '    <meta property="og:site_name" content="POLLNXT">' . "\n"
    . '    <meta property="og:title" content="' . h($metaTitle) . '">' . "\n"
    . '    <meta property="og:description" content="' . h($metaDescription) . '">' . "\n"
    . '    <meta property="og:url" content="' . h($pollShareUrl) . '">' . "\n"
    . '    <meta property="og:image" content="' . h($metaImage) . '">' . "\n"
    . '    <meta property="og:image:alt" content="' . h((string)$poll['question']) . '">' . "\n"
    . '    <meta name="twitter:card" content="summary_large_image">' . "\n"
    . '    <meta name="twitter:title" content="' . h($metaTitle) . '">' . "\n"
    . '    <meta name="twitter:description" content="' . h($metaDescription) . '">' . "\n"
    . '    <meta name="twitter:image" content="' . h($metaImage) . '">' . "\n";

$can_vote = true;
if ((int)$poll['is_blocked'] === 1) {
    $is_creator = is_logged_in() && ((int)($_SESSION['user_id'] ?? 0) === (int)$poll['user_id']);
    if (!$is_creator) {
        $can_vote = false;
    }
}

// Social “direct vote” link
if ($vote_flag === 1 && $can_vote) {
    redirect('vote.php?id=' . (int)$poll_id);
}

$stmt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY votes DESC");
$stmt->execute([$poll_id]);
$options = $stmt->fetchAll();

$total_votes = (int)($poll['total_votes'] ?? 0);
$cat_class = 'cat-' . strtolower($poll['category']);
$img = $poll['image_path'] ?? '';
$img_src = $img !== ''
    ? (strncmp($img, 'http', 4) === 0 ? $img : '../' . ltrim($img, '/'))
    : 'https://via.placeholder.com/300x300/00AF91/FFFFFF?text=POLL';

$displayName = (string)$poll['author_name'];
if (!is_logged_in() || ((int)($_SESSION['user_id'] ?? 0) !== (int)$poll['user_id'])) {
    $displayName = mask_name($displayName);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-4">
                    <div class="poll-with-image mb-3">
                        <div class="poll-with-image-thumb">
                            <img src="<?php echo h($img_src); ?>"
                                 alt="Poll image">
                        </div>
                        <div class="poll-with-image-content">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="category-badge <?php echo $cat_class; ?>"><?php echo h($poll['category']); ?></span>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i><?php echo h($displayName); ?> &bull;
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($poll['created_at'])); ?>
                                </small>
                            </div>

                            <h4 class="mb-2"><?php echo h($poll['question']); ?></h4>
                            <p class="text-muted mb-3">
                                <i class="fas fa-chart-pie me-1"></i>Total Votes: <strong><?php echo $total_votes; ?></strong>
                            </p>

                            <?php if ((int)$poll['is_blocked'] === 1): ?>
                                <div class="alert alert-warning small py-1 px-2 mb-2">
                                    <i class="fas fa-ban me-1"></i>This poll is blocked by admin.
                                    <?php if (!empty($poll['blocked_reason'])): ?>
                                        Reason: <?php echo h($poll['blocked_reason']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <?php if ($can_vote): ?>
                            <a href="vote.php?id=<?php echo (int)$poll_id; ?>" class="btn btn-primary">
                                <i class="fas fa-vote-yea me-2"></i>Vote
                            </a>
                        <?php endif; ?>

                        <a href="result.php?id=<?php echo (int)$poll_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-circle-info me-2"></i>View Details
                        </a>

                        <?php if ($canShareOnFacebook): ?>
                            <a class="btn btn-outline-secondary"
                               href="<?php echo h(facebook_share_url($pollShareUrl)); ?>"
                               target="_blank" rel="noopener">
                                <i class="fab fa-facebook-f me-2"></i>Share
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary copy-share-link"
                                    type="button"
                                    data-share-url="<?php echo h($pollShareUrl); ?>"
                                    title="Facebook cannot share localhost URLs. Copy this link after setting a public site URL.">
                                <i class="fab fa-facebook-f me-2"></i>Copy Link
                            </button>
                        <?php endif; ?>

                        <?php if (!is_logged_in()): ?>
                            <a href="../auth/register.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user-plus me-2"></i>Register to create your own poll
                            </a>
                        <?php endif; ?>

                        <a href="all_polls.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Polls
                        </a>
                    </div>

                    <div>
                        <?php
                        $optTotal = $total_votes;
                        foreach ($options as $opt):
                            $pct = $optTotal > 0 ? round(((int)$opt['votes'] / $optTotal) * 100) : 0;
                        ?>
                            <div class="result-option">
                                <div class="option-header">
                                    <span class="option-text"><?php echo h($opt['option_text']); ?></span>
                                    <span class="option-votes">
                                        <?php echo (int)$opt['votes']; ?> votes &mdash; <?php echo (int)$pct; ?>%
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: <?php echo (int)$pct; ?>%"><?php echo (int)$pct; ?>%</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

