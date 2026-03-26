<?php
// Process actions before output
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_login();

$admin_page_title = 'Polls';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
        $poll_id = (int)($_POST['poll_id'] ?? 0);

        if ($poll_id <= 0) {
            $errors[] = 'Invalid poll.';
        } else {
            if ($action === 'block') {
                $reason = trim($_POST['reason'] ?? '');
                if ($reason === '') {
                    $errors[] = 'Block reason is required.';
                } else {
                    $stmt = $pdo->prepare("UPDATE polls SET is_blocked = 1, blocked_reason = ? WHERE id = ?");
                    $stmt->execute([$reason, $poll_id]);
                    set_flash('success', 'Poll blocked.');
                    header('Location: polls.php');
                    exit;
                }
            }

            if ($action === 'unblock') {
                $stmt = $pdo->prepare("UPDATE polls SET is_blocked = 0, blocked_reason = NULL WHERE id = ?");
                $stmt->execute([$poll_id]);
                set_flash('success', 'Poll unblocked.');
                header('Location: polls.php');
                exit;
            }

            if ($action === 'toggle_trending') {
                $val = (int)($_POST['is_trending'] ?? 0) === 1 ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE polls SET is_trending = ? WHERE id = ?");
                $stmt->execute([$val, $poll_id]);
                set_flash('success', $val ? 'Marked as trending.' : 'Removed from trending.');
                header('Location: polls.php');
                exit;
            }

            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
                $stmt->execute([$poll_id]);
                set_flash('success', 'Poll deleted.');
                header('Location: polls.php');
                exit;
            }
        }
    }
}

$polls = $pdo->query("
    SELECT p.*, u.name AS author_name,
           (SELECT COALESCE(SUM(po.votes),0) FROM poll_options po WHERE po.poll_id = p.id) AS total_votes
    FROM polls p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-poll-h me-2"></i>Polls</h3>
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

<div class="card p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Poll</th>
                    <th>Author</th>
                    <th>Votes</th>
                    <th>Trending</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($polls as $p): ?>
                <tr>
                    <?php $img = $p['image_path'] ?? ''; ?>
                    <td style="max-width: 420px;">
                        <div style="display:flex; gap:12px; align-items:flex-start;">
                            <img
                                src="<?php echo h($img !== '' ? $img : 'https://via.placeholder.com/80x80/00AF91/FFFFFF?text=POLL'); ?>"
                                alt="Poll image"
                                style="width:80px; height:80px; border-radius:12px; object-fit:cover; flex-shrink:0; border:1px solid rgba(15,23,42,.08);"
                            />
                            <div>
                                <div class="fw-semibold"><?php echo admin_h($p['question']); ?></div>
                                <div class="text-muted small">
                                    <i class="fas fa-tag me-1"></i><?php echo admin_h($p['category']); ?>
                                    &bull; <i class="fas fa-calendar me-1"></i><?php echo admin_h(date('M d, Y', strtotime($p['created_at']))); ?>
                                </div>
                                <?php if ((int)$p['is_blocked'] === 1): ?>
                                    <div class="text-danger small mt-1"><i class="fas fa-ban me-1"></i><?php echo admin_h($p['blocked_reason'] ?? 'Blocked'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?php echo admin_h($p['author_name']); ?></td>
                    <td><?php echo (int)$p['total_votes']; ?></td>
                    <td>
                        <?php if ((int)$p['is_trending'] === 1): ?>
                            <span class="badge text-bg-warning"><i class="fas fa-fire me-1"></i>Hot</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$p['is_blocked'] === 1): ?>
                            <span class="badge text-bg-danger">Blocked</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="toggle_trending">
                            <input type="hidden" name="poll_id" value="<?php echo (int)$p['id']; ?>">
                            <input type="hidden" name="is_trending" value="<?php echo (int)$p['is_trending'] === 1 ? 0 : 1; ?>">
                            <button class="btn btn-sm btn-outline-warning" type="submit">
                                <i class="fas fa-fire me-1"></i><?php echo (int)$p['is_trending'] === 1 ? 'Unhot' : 'Hot'; ?>
                            </button>
                        </form>

                        <?php if ((int)$p['is_blocked'] === 1): ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="unblock">
                                <input type="hidden" name="poll_id" value="<?php echo (int)$p['id']; ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-unlock me-1"></i>Unblock</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger" type="button" onclick="openBlock(<?php echo (int)$p['id']; ?>)">
                                <i class="fas fa-ban me-1"></i>Block
                            </button>
                        <?php endif; ?>

                        <button
                            class="btn btn-sm btn-outline-danger delete-poll-btn"
                            type="button"
                            data-poll-id="<?php echo (int)$p['id']; ?>"
                            title="Delete poll">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i>Block poll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="poll_id" id="block_poll_id" value="">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" name="reason" required placeholder="Write reason to show to users..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" type="submit">Block</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openBlock(pollId) {
    document.getElementById('block_poll_id').value = pollId;
    const modal = new bootstrap.Modal(document.getElementById('blockModal'));
    modal.show();
}

const ADMIN_CSRF_TOKEN = <?php echo json_encode((string)($_SESSION['csrf_token'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
async function deletePoll(pollId) {
    const form = new URLSearchParams();
    form.append('poll_id', String(pollId));
    form.append('csrf_token', ADMIN_CSRF_TOKEN);

    const res = await fetch('api/delete_poll.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: form.toString()
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Delete failed');
    return data;
}

document.querySelectorAll('.delete-poll-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const pollId = btn.dataset.pollId;
        if (!pollId) return;
        if (!confirm('Delete this poll and its votes/options?')) return;
        try {
            await deletePoll(pollId);
            btn.closest('tr').remove();
        } catch (e) {
            alert(e.message || 'Delete failed');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

