<?php
$page_title = 'All Polls';
require_once __DIR__ . '/../includes/header.php';

$categories = ['Technology', 'Sports', 'Politics', 'Education', 'Entertainment', 'Other'];
$filter_cat = isset($_GET['category']) ? trim($_GET['category']) : '';

// Absolute base URL for social-share links
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$pollSystemRoot = preg_replace('#/pages/all_polls\\.php$#', '', $scriptName);
if ($pollSystemRoot === $scriptName) {
    $pollSystemRoot = preg_replace('#/pages/.*$#', '', $scriptName);
}
$absBaseUrl = $scheme . '://' . $host . $pollSystemRoot;

// Build query (only active polls)
$sql = "SELECT p.*, u.name AS author_name, 
        (SELECT SUM(po.votes) FROM poll_options po WHERE po.poll_id = p.id) AS total_votes 
        FROM polls p 
        JOIN users u ON p.user_id = u.id
        WHERE p.is_blocked = 0";
$params = [];

if (!empty($filter_cat) && in_array($filter_cat, $categories)) {
    $sql .= " AND p.category = ?";
    $params[] = $filter_cat;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$polls = $stmt->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-list me-2" style="color: var(--primary);"></i>All Polls</h3>
        <?php if (is_logged_in()): ?>
            <a href="create_poll.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Poll</a>
        <?php endif; ?>
    </div>

    <!-- Category Filter -->
    <div class="category-filter">
        <a href="all_polls.php" class="btn btn-outline-secondary <?php echo empty($filter_cat) ? 'active' : ''; ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="all_polls.php?category=<?php echo urlencode($cat); ?>" 
               class="btn btn-outline-secondary <?php echo $filter_cat === $cat ? 'active' : ''; ?>">
                <?php echo h($cat); ?>
            </a>
        <?php endforeach; ?>
    </div>

<?php if (empty($polls)): ?>
        <div class="text-center py-5">
            <i class="fas fa-poll-h fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No polls found</h5>
            <p class="text-muted">Be the first to create a poll!</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($polls as $poll):
                $cat_class  = 'cat-' . strtolower($poll['category']);
                $total      = (int)$poll['total_votes'];
                $is_trending = (int)($poll['is_trending'] ?? 0) === 1;
                $is_creator = is_logged_in() && (int)($_SESSION['user_id'] ?? 0) === (int)$poll['user_id'];
                $img        = $poll['image_path'] ?? '';
                // Fix path for images stored as relative paths (e.g. uploads/polls/...)
                $img_src    = $img !== ''
                    ? (strncmp($img, 'http', 4) === 0 ? $img : $base . $img)
                    : 'https://via.placeholder.com/300x300/00AF91/FFFFFF?text=POLL';
            ?>
                <div class="col-md-6 col-lg-4 mb-4 poll-card-wrap">
                    <?php if (!empty($poll['end_date'])): ?>
                        <div class="poll-countdown" data-end="<?php echo h($poll['end_date']); ?>">
                            <i class="fas fa-hourglass-half"></i>
                            <span class="countdown-text"></span>
                        </div>
                    <?php endif; ?>
                    <div class="card poll-card h-100 position-relative">
                        <?php if ($is_trending): ?>
                            <span class="trending-badge"><i class="fas fa-fire me-1"></i>Trending</span>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="poll-with-image mb-2">
                                <div class="poll-with-image-thumb">
                                    <img src="<?php echo h($img_src); ?>" alt="Poll image">
                                </div>
                                <div class="poll-with-image-content">
                                    <div class="mb-1">
                                        <span class="category-badge <?php echo $cat_class; ?>"><?php echo h($poll['category']); ?></span>
                                    </div>
                                    <h6 class="poll-question mb-1"><?php echo h($poll['question']); ?></h6>
                                    <div class="poll-meta">
                                        <span><i class="fas fa-user"></i><?php echo h(mask_name($poll['author_name'])); ?></span>
                                        <span><i class="fas fa-vote-yea"></i><?php echo $total; ?> votes</span>
                                        <span><i class="fas fa-clock"></i><?php echo date('M d, Y', strtotime($poll['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-auto d-flex gap-2">
                                <a href="vote.php?id=<?php echo (int)$poll['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                                    <i class="fas fa-vote-yea me-1"></i>Vote
                                </a>
                                <a href="result.php?id=<?php echo (int)$poll['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="fas fa-chart-bar me-1"></i>Results
                                </a>
                            </div>

                            <?php
                                $voteUrl      = $absBaseUrl . '/pages/vote.php?id='   . (int)$poll['id'];
                                $shareTextEnc = urlencode($poll['question']);
                            ?>
                            <?php if ($is_creator): ?>
                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                <a class="btn btn-outline-secondary btn-sm flex-fill"
                                   href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($voteUrl); ?>"
                                   target="_blank" rel="noopener">
                                    <i class="fab fa-facebook-f me-1"></i>Facebook
                                </a>
                                <a class="btn btn-outline-secondary btn-sm flex-fill"
                                   href="https://twitter.com/intent/tweet?url=<?php echo urlencode($voteUrl); ?>&text=<?php echo $shareTextEnc; ?>"
                                   target="_blank" rel="noopener">
                                    <i class="fab fa-x-twitter me-1"></i>Twitter/X
                                </a>
                                <a class="btn btn-outline-secondary btn-sm flex-fill"
                                   href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($voteUrl); ?>"
                                   target="_blank" rel="noopener">
                                    <i class="fab fa-linkedin-in me-1"></i>LinkedIn
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
