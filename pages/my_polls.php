<?php
$page_title = 'My Polls';
require_once __DIR__ . '/../includes/header.php';

if (!is_logged_in()) {
    set_flash('error', 'Please login to view your polls.');
    redirect('../auth/login.php');
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COALESCE(SUM(po.votes),0) FROM poll_options po WHERE po.poll_id = p.id) AS total_votes
    FROM polls p
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_id]);
$polls = $stmt->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-list-check me-2" style="color: var(--primary);"></i>My Polls</h3>
        <a href="create_poll.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Poll</a>
    </div>

    <?php if (empty($polls)): ?>
        <div class="text-center py-5">
            <i class="fas fa-poll-h fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">You haven't created any polls yet.</h5>
            <a href="create_poll.php" class="btn btn-primary mt-2"><i class="fas fa-plus me-2"></i>Create your first poll</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($polls as $poll):
                $cat_class  = 'cat-' . strtolower($poll['category']);
                $total      = (int)$poll['total_votes'];
                $vote_url   = $base . 'pages/vote.php?id=' . $poll['id'];
                $share_text = urlencode($poll['question'] . ' - Vote on this poll at POLLNXT');
                $share_url  = urlencode($vote_url);
            ?>
                <div class="col-md-6 col-lg-4 mb-4 poll-card-wrap">
                    <?php if (!empty($poll['end_date'])): ?>
                        <div class="poll-countdown" data-end="<?php echo h($poll['end_date']); ?>">
                            <i class="fas fa-hourglass-half"></i>
                            <span class="countdown-text"></span>
                        </div>
                    <?php endif; ?>
                    <div class="card poll-card h-100 position-relative">
                        <?php if ((int)$poll['is_trending'] === 1): ?>
                            <span class="trending-badge"><i class="fas fa-fire me-1"></i>Trending</span>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2 d-flex align-items-center gap-2">
                                <span class="category-badge <?php echo $cat_class; ?>"><?php echo h($poll['category']); ?></span>
                                <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo date('M d, Y', strtotime($poll['created_at'])); ?></small>
                            </div>
                            <h6 class="poll-question"><?php echo h($poll['question']); ?></h6>

                            <?php if ((int)$poll['is_blocked'] === 1): ?>
                                <div class="alert alert-warning small py-1 px-2 mt-2 mb-2">
                                    <i class="fas fa-ban me-1"></i>
                                    This poll is blocked by admin.
                                    <?php if (!empty($poll['blocked_reason'])): ?>
                                        Reason: <?php echo h($poll['blocked_reason']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="poll-meta mb-2">
                                <span><i class="fas fa-vote-yea"></i><?php echo $total; ?> votes</span>
                            </div>

                            <div class="mt-auto">
                                <div class="d-flex gap-2 mb-2">
                                    <a href="vote.php?id=<?php echo $poll['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-vote-yea me-1"></i>Vote
                                    </a>
                                    <a href="result.php?id=<?php echo $poll['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="fas fa-chart-bar me-1"></i>Results
                                    </a>
                                </div>
                                <div class="d-flex gap-2 share-links">
                                    <a class="btn btn-outline-secondary btn-sm flex-fill"
                                       href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>"
                                       target="_blank" rel="noopener">
                                        <i class="fab fa-facebook-f me-1"></i>Facebook
                                    </a>
                                    <a class="btn btn-outline-secondary btn-sm flex-fill"
                                       href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_text; ?>"
                                       target="_blank" rel="noopener">
                                        <i class="fab fa-x-twitter me-1"></i>Twitter
                                    </a>
                                    <a class="btn btn-outline-secondary btn-sm flex-fill"
                                       href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $share_url; ?>"
                                       target="_blank" rel="noopener">
                                        <i class="fab fa-linkedin-in me-1"></i>LinkedIn
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

