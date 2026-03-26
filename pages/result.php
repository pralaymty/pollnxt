<?php
$page_title = 'Poll Results';
require_once __DIR__ . '/../includes/header.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Absolute base URL for social-share links
$scheme        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host          = $_SERVER['HTTP_HOST'] ?? 'localhost';
$pollSystemRoot = preg_replace('#/pages/result\.php$#', '', $_SERVER['SCRIPT_NAME'] ?? '');
$absBaseUrl    = $scheme . '://' . $host . $pollSystemRoot;

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

// Fetch options
$stmt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY votes DESC");
$stmt->execute([$poll_id]);
$options = $stmt->fetchAll();

$total_votes = array_sum(array_column($options, 'votes'));
$cat_class  = 'cat-' . strtolower($poll['category']);
$is_creator = is_logged_in() && (int)($_SESSION['user_id'] ?? 0) === (int)$poll['user_id'];
$img        = $poll['image_path'] ?? '';
$img_src    = $img !== ''
    ? (strncmp($img, 'http', 4) === 0 ? $img : $base . $img)
    : 'https://via.placeholder.com/300x300/00AF91/FFFFFF?text=POLL';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7 poll-card-wrap">
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
                                <small class="text-muted">
                                    <?php
                                    $displayName = $poll['author_name'];
                                    if (!is_logged_in() || (int)($_SESSION['user_id'] ?? 0) !== (int)$poll['user_id']) {
                                        $displayName = mask_name($displayName);
                                    }
                                    ?>
                                    <i class="fas fa-user me-1"></i><?php echo h($displayName); ?> &bull;
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($poll['created_at'])); ?>
                                </small>
                            </div>

                            <h4 class="mb-2"><?php echo h($poll['question']); ?></h4>
                            <p class="text-muted mb-0">
                                <i class="fas fa-chart-pie me-1"></i>Total Votes: <strong><?php echo $total_votes; ?></strong>
                            </p>
                            <?php if ((int)$poll['is_blocked'] === 1 && !empty($poll['blocked_reason'])): ?>
                                <div class="alert alert-warning small py-1 px-2 mt-2 mb-0">
                                    <i class="fas fa-ban me-1"></i>This poll is blocked by admin. Reason: <?php echo h($poll['blocked_reason']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                    $labels = [];
                    $data = [];
                    $colors = ['#00AF91','#ff7675','#74b9ff','#fdcb6e','#6c5ce7','#0984e3','#e17055','#00cec9'];
                    foreach ($options as $idx => $opt) {
                        $labels[] = $opt['option_text'];
                        $data[] = (int)$opt['votes'];
                    }
                    ?>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <?php foreach ($options as $opt):
                                $pct = $total_votes > 0 ? round(($opt['votes'] / $total_votes) * 100) : 0;
                            ?>
                                <div class="result-option">
                                    <div class="option-header">
                                        <span class="option-text"><?php echo h($opt['option_text']); ?></span>
                                        <span class="option-votes"><?php echo $opt['votes']; ?> votes &mdash; <?php echo $pct; ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" 
                                             role="progressbar" 
                                             style="width: <?php echo $pct; ?>%"
                                             aria-valuenow="<?php echo $pct; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo $pct; ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-6">
                            <canvas id="resultsPie"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="vote.php?id=<?php echo $poll_id; ?>" class="btn btn-primary"><i class="fas fa-vote-yea me-2"></i>Vote</a>
                <a href="all_polls.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>All Polls</a>
            </div>

            <?php if ($is_creator): ?>
            <?php
                $resultUrl    = $absBaseUrl . '/pages/result.php?id=' . $poll_id;
                $shareTextEnc = urlencode($poll['question'] . ' — Poll Results on POLLNXT');
            ?>
            <div class="mt-3 d-flex gap-2 flex-wrap justify-content-center">
                <small class="w-100 text-center text-muted mb-1"><i class="fas fa-share-alt me-1"></i>Share your poll results</small>
                <a class="btn btn-outline-secondary btn-sm"
                   href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($resultUrl); ?>"
                   target="_blank" rel="noopener">
                    <i class="fab fa-facebook-f me-1"></i>Facebook
                </a>
                <a class="btn btn-outline-secondary btn-sm"
                   href="https://twitter.com/intent/tweet?url=<?php echo urlencode($resultUrl); ?>&text=<?php echo $shareTextEnc; ?>"
                   target="_blank" rel="noopener">
                    <i class="fab fa-x-twitter me-1"></i>Twitter/X
                </a>
                <a class="btn btn-outline-secondary btn-sm"
                   href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($resultUrl); ?>"
                   target="_blank" rel="noopener">
                    <i class="fab fa-linkedin-in me-1"></i>LinkedIn
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    const ctx = document.getElementById('resultsPie');
    if (!ctx) return;
    const labels = <?php echo json_encode($labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const data = <?php echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const baseColors = <?php echo json_encode($colors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const bg = labels.map((_, i) => baseColors[i % baseColors.length]);

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: bg,
                borderColor: '#ffffff',
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 16 }
                }
            },
            cutout: '45%',
            layout: { padding: 10 }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
