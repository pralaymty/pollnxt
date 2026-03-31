<?php
$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';

// Stats
$total_polls = $pdo->query("SELECT COUNT(*) FROM polls")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_votes = $pdo->query("SELECT COALESCE(SUM(votes), 0) FROM poll_options")->fetchColumn();

// Latest polls (limit 6) — only active (not blocked)
$stmt = $pdo->query("SELECT p.*, u.name AS author_name, 
    (SELECT SUM(po.votes) FROM poll_options po WHERE po.poll_id = p.id) AS total_votes 
    FROM polls p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.is_blocked = 0
    ORDER BY p.created_at DESC 
    LIMIT 6");
$latest_polls = $stmt->fetchAll();

// Trending polls (marked by admin) — only active (not blocked)
$stmt = $pdo->query("SELECT p.*, u.name AS author_name,
    (SELECT SUM(po.votes) FROM poll_options po WHERE po.poll_id = p.id) AS total_votes
    FROM polls p
    JOIN users u ON p.user_id = u.id
    WHERE p.is_blocked = 0 AND p.is_trending = 1
    ORDER BY total_votes DESC
    LIMIT 12");
$trending_polls = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center">
        <h1>Create & Share Polls Instantlyy</h1>
        <p class="lead mb-4">Create polls in seconds, share with a simple link, and track live results as people vote.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <?php if (is_logged_in()): ?>
                <a href="pages/create_poll.php" class="btn btn-light btn-lg px-4">
                    <i class="fas fa-plus-circle me-2"></i>Create Poll
                </a>
            <?php else: ?>
                <a href="auth/register.php" class="btn btn-light btn-lg px-4">
                    <i class="fas fa-rocket me-2"></i>Get Started Free
                </a>
            <?php endif; ?>
            <a href="pages/all_polls.php" class="btn btn-outline-light btn-lg px-4">
                <i class="fas fa-list me-2"></i>Browse Polls
            </a>
        </div>
    </div>
</section>

<!-- Stats -->
<div class="container mb-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-number"><?php echo number_format($total_polls); ?></div>
                <div class="stat-label"><i class="fas fa-poll-h me-1"></i>Total Polls</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-number"><?php echo number_format($total_votes); ?></div>
                <div class="stat-label"><i class="fas fa-vote-yea me-1"></i>Total Votes</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-number"><?php echo number_format($total_users); ?></div>
                <div class="stat-label"><i class="fas fa-users me-1"></i>Registered Users</div>
            </div>
        </div>
    </div>
</div>

<!-- Slick Slider CSS (Trending Polls) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css">

<!-- Trending Polls -->
<?php if (!empty($trending_polls)): ?>
<div class="container mb-5">
    <h3 class="mb-4"><i class="fas fa-fire me-2" style="color: #d63031;"></i>Trending Polls</h3>

    <style>
        /* ── Equal-height cards ── */
        .trending-slick .slick-track {
            display: flex !important;
        }
        .trending-slick .slick-slide {
            height: auto !important;  /* let flex dictate height */
        }
        /* propagate 100% height through Slick's wrapper divs to our card */
        .trending-slick .slick-slide > div,
        .trending-slick .slick-slide > div > div {
            height: 100%;
        }

        /* ── Arrows: centered on the card area (not the dot-padding area) ── */
        /* Keep the dot space outside the slider with a wrapper — no pb on slider */
        .trending-slick-wrap {
            padding-bottom: 40px;   /* room for dots */
            position: relative;
        }
        .trending-slick .slick-prev,
        .trending-slick .slick-next {
            width: 32px;
            height: 32px;
            background: var(--primary, #00AF91);
            border-radius: 50%;
            z-index: 2;
            top: 50%;
            transform: translateY(-50%);
        }
        .trending-slick .slick-prev { left: -16px; }
        .trending-slick .slick-next { right: -16px; }
        .trending-slick .slick-prev:before,
        .trending-slick .slick-next:before {
            font-size: 16px;
            line-height: 32px;
            opacity: 1;
        }
        .trending-slick .slick-prev:hover,
        .trending-slick .slick-next:hover {
            background: var(--primary-dark, #008a73);
        }
        /* Slide padding so cards don't touch */
        .trending-slick .slick-slide {
            padding: 0 8px;
        }
        .trending-slick .slick-list {
            margin: 0 -8px;
        }
        /* Dots */
        .trending-slick .slick-dots {
            bottom: -32px;
        }
        .trending-slick .slick-dots li button:before {
            color: var(--primary, #00AF91);
            font-size: 10px;
        }
        .trending-slick .slick-dots li.slick-active button:before {
            color: var(--primary, #00AF91);
            opacity: 1;
        }
    </style>

    <div class="trending-slick-wrap">
    <div class="trending-slick">
        <?php foreach ($trending_polls as $poll):
            $cat_class = 'cat-' . strtolower($poll['category']);
            $total     = (int)$poll['total_votes'];
            $img       = $poll['image_path'] ?? '';
        ?>
            <div class="poll-card-wrap">
                <div class="card poll-card h-100 position-relative">
                    <span class="trending-badge"><i class="fas fa-fire me-1"></i>Hot</span>

                    <?php if (!empty($poll['end_date'])): ?>
                        <!-- Timer styled like the trending tag (placed inside the card, top-right) -->
                        <span class="trending-badge poll-countdown position-absolute top-0 end-0 m-2" data-end="<?php echo h($poll['end_date']); ?>">
                            <i class="fas fa-hourglass-half me-1"></i>
                            <span class="countdown-text"></span>
                        </span>
                    <?php endif; ?>

                    <div class="card-body d-flex flex-column">
                        <div class="poll-with-image mb-2">
                            <div class="poll-with-image-thumb">
                                <img src="<?php echo h($img !== '' ? $img : 'https://via.placeholder.com/300x300/00AF91/FFFFFF?text=POLL'); ?>" alt="Poll image">
                            </div>
                            <div class="poll-with-image-content">
                                <span class="category-badge <?php echo $cat_class; ?> mb-2 d-inline-block"><?php echo h($poll['category']); ?></span>
                                <h6 class="poll-question mb-1"><?php echo h($poll['question']); ?></h6>
                                <div class="poll-meta">
                                    <span><i class="fas fa-vote-yea"></i><?php echo $total; ?> votes</span>
                                    <span><i class="fas fa-clock"></i><?php echo date('M d, Y', strtotime($poll['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto d-flex gap-2">
                            <a href="pages/vote.php?id=<?php echo (int)$poll['id']; ?>" class="btn btn-primary btn-sm flex-fill">Vote</a>
                            <a href="pages/result.php?id=<?php echo (int)$poll['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">Results</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    </div><!-- /.trending-slick-wrap -->

    <!-- jQuery + Slick JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.trending-slick').slick({
                slidesToShow   : 4,
                slidesToScroll : 1,
                autoplay       : true,
                autoplaySpeed  : 3000,
                pauseOnHover   : true,
                infinite       : true,
                dots           : true,
                arrows         : true,
                responsive: [
                    {
                        breakpoint: 992,
                        settings  : { slidesToShow: 2, slidesToScroll: 1 }
                    },
                    {
                        breakpoint: 576,
                        settings  : { slidesToShow: 1, slidesToScroll: 1 }
                    }
                ]
            });
        });
    </script>
</div>
<?php endif; ?>

<!-- Latest Polls -->
<?php if (!empty($latest_polls)): ?>
<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-clock me-2" style="color: var(--primary);"></i>Latest Polls</h3>
        <a href="pages/all_polls.php" class="btn btn-outline-primary btn-sm">View All &rarr;</a>
    </div>
    <div class="row g-4">
        <?php foreach ($latest_polls as $poll):
            $cat_class = 'cat-' . strtolower($poll['category']);
            $total = (int)$poll['total_votes'];
            $img = $poll['image_path'] ?? '';
        ?>
            <div class="col-md-6 col-lg-4 poll-card-wrap">
                <?php if (!empty($poll['end_date'])): ?>
                    <div class="poll-countdown" data-end="<?php echo h($poll['end_date']); ?>">
                        <i class="fas fa-hourglass-half"></i>
                        <span class="countdown-text"></span>
                    </div>
                <?php endif; ?>
                <div class="card poll-card h-100 position-relative">
                    <div class="card-body d-flex flex-column">
                        <div class="poll-with-image mb-2">
                            <div class="poll-with-image-thumb">
                                <img src="<?php echo h($img !== '' ? $img : 'https://via.placeholder.com/300x300/00AF91/FFFFFF?text=POLL'); ?>" alt="Poll image">
                            </div>
                            <div class="poll-with-image-content">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="category-badge <?php echo $cat_class; ?>"><?php echo h($poll['category']); ?></span>
                                    <small class="text-muted"><?php echo date('M d', strtotime($poll['created_at'])); ?></small>
                                </div>
                                <h6 class="poll-question mb-1"><?php echo h($poll['question']); ?></h6>
                                <div class="poll-meta">
                                    <span><i class="fas fa-user"></i><?php echo h(mask_name($poll['author_name'])); ?></span>
                                    <span><i class="fas fa-vote-yea"></i><?php echo $total; ?> votes</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto d-flex gap-2">
                            <a href="pages/vote.php?id=<?php echo $poll['id']; ?>" class="btn btn-primary btn-sm flex-fill">
                                <i class="fas fa-vote-yea me-1"></i>Vote
                            </a>
                            <a href="pages/result.php?id=<?php echo $poll['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="fas fa-chart-bar me-1"></i>Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
