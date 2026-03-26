<?php
// Process actions before sending any HTML
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_login();

$admin_page_title = 'Users';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            $errors[] = 'Invalid user.';
        } else {
            if ($action === 'block') {
                $reason = trim($_POST['reason'] ?? '');
                if ($reason === '') {
                    $errors[] = 'Block reason is required.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET is_blocked = 1, blocked_reason = ? WHERE id = ?");
                    $stmt->execute([$reason, $user_id]);
                    set_flash('success', 'User blocked.');
                    header('Location: users.php');
                    exit;
                }
            }

            if ($action === 'unblock') {
                $stmt = $pdo->prepare("UPDATE users SET is_blocked = 0, blocked_reason = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                set_flash('success', 'User unblocked.');
                header('Location: users.php');
                exit;
            }

            if ($action === 'update_profession') {
                $profession = trim($_POST['profession'] ?? '');
                $color = trim($_POST['profession_color'] ?? '');
                $stmt = $pdo->prepare("UPDATE users SET profession = ?, profession_color = ? WHERE id = ?");
                $stmt->execute([$profession !== '' ? $profession : null, $color !== '' ? $color : null, $user_id]);
                set_flash('success', 'Profession updated.');
                header('Location: users.php');
                exit;
            }

            if ($action === 'delete') {
                // Optional: prevent deleting currently logged-in frontend user if mapped
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                set_flash('success', 'User deleted.');
                header('Location: users.php');
                exit;
            }
        }
    }
}

$users = $pdo->query("SELECT id, name, email, auth_provider, is_blocked, blocked_reason, profession, profession_color, created_at FROM users ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-users me-2"></i>Users</h3>
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
                    <th>User</th>
                    <th>Provider</th>
                    <th>Profession</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php echo admin_h($u['name']); ?></div>
                        <div class="text-muted small"><?php echo admin_h($u['email']); ?> &bull; <?php echo admin_h(date('M d, Y', strtotime($u['created_at']))); ?></div>
                        <?php if ((int)$u['is_blocked'] === 1): ?>
                            <div class="text-danger small mt-1"><i class="fas fa-ban me-1"></i><?php echo admin_h($u['blocked_reason'] ?? 'Blocked'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light"><?php echo admin_h($u['auth_provider']); ?></span></td>
                    <td>
                        <?php
                            $prof = (string)($u['profession'] ?? '');
                            $color = (string)($u['profession_color'] ?? '');
                        ?>
                        <?php if ($prof !== ''): ?>
                            <span class="badge" style="background: <?php echo admin_h($color !== '' ? $color : '#b2bec3'); ?>; color: #1F2D3F;">
                                <?php echo admin_h($prof); ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$u['is_blocked'] === 1): ?>
                            <span class="badge text-bg-danger">Blocked</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="button"
                                onclick="openProfession(<?php echo (int)$u['id']; ?>, '<?php echo admin_h($prof); ?>', '<?php echo admin_h($color); ?>')">
                            <i class="fas fa-palette me-1"></i>Profession
                        </button>

                        <?php if ((int)$u['is_blocked'] === 1): ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="unblock">
                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-unlock me-1"></i>Unblock</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger" type="button" onclick="openUserBlock(<?php echo (int)$u['id']; ?>)">
                                <i class="fas fa-ban me-1"></i>Block
                            </button>
                        <?php endif; ?>

                        <button
                            class="btn btn-sm btn-outline-danger delete-user-btn"
                            type="button"
                            data-user-id="<?php echo (int)$u['id']; ?>"
                            title="Delete user">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userBlockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i>Block user</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="user_id" id="block_user_id" value="">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" name="reason" required placeholder="Reason shown to the user"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" type="submit">Block</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="professionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-palette me-2"></i>User profession</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="update_profession">
                    <input type="hidden" name="user_id" id="prof_user_id" value="">

                    <div class="mb-2">
                        <label class="form-label">Profession</label>
                        <input class="form-control" name="profession" id="prof_value" placeholder="e.g. Designer">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Color</label>
                        <input class="form-control" name="profession_color" id="prof_color" placeholder="#81ecec">
                        <div class="text-muted small mt-1">Any valid CSS color (hex recommended).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserBlock(userId) {
    document.getElementById('block_user_id').value = userId;
    new bootstrap.Modal(document.getElementById('userBlockModal')).show();
}
function openProfession(userId, prof, color) {
    document.getElementById('prof_user_id').value = userId;
    document.getElementById('prof_value').value = prof || '';
    document.getElementById('prof_color').value = color || '';
    new bootstrap.Modal(document.getElementById('professionModal')).show();
}

const ADMIN_CSRF_TOKEN = <?php echo json_encode((string)($_SESSION['csrf_token'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
async function deleteUser(userId) {
    const form = new URLSearchParams();
    form.append('user_id', String(userId));
    form.append('csrf_token', ADMIN_CSRF_TOKEN);

    const res = await fetch('api/delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: form.toString()
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Delete failed');
    return data;
}

document.querySelectorAll('.delete-user-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const userId = btn.dataset.userId;
        if (!userId) return;
        if (!confirm('Delete this user and all related polls/votes?')) return;
        try {
            await deleteUser(userId);
            btn.closest('tr').remove();
        } catch (e) {
            alert(e.message || 'Delete failed');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

