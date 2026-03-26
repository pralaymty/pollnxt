<?php
// Process actions before sending any HTML
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_super();

$admin_page_title = 'Admins';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = ($_POST['role'] ?? 'admin') === 'super_admin' ? 'super_admin' : 'admin';

            if ($name === '' || $email === '' || $password === '') {
                $errors[] = 'Name, email and password are required.';
            }

            if (!$errors) {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admin_users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hash, $role]);
                    set_flash('success', 'Admin user created.');
                    header('Location: admins.php');
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Could not create admin (duplicate email?).';
                }
            }
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = ($_POST['role'] ?? 'admin') === 'super_admin' ? 'super_admin' : 'admin';
            $new_password = trim($_POST['new_password'] ?? '');

            if ($id <= 0 || $name === '' || $email === '') {
                $errors[] = 'Invalid admin data.';
            }

            if (!$errors) {
                try {
                    if ($new_password !== '') {
                        $hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $hash, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $id]);
                    }
                    set_flash('success', 'Admin user updated.');
                    header('Location: admins.php');
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Could not update admin (duplicate email?).';
                }
            }
        }

        if ($action === 'block') {
            $id = (int)($_POST['id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($id <= 0 || $reason === '') {
                $errors[] = 'Reason is required.';
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET is_blocked = 1, blocked_reason = ? WHERE id = ?");
                $stmt->execute([$reason, $id]);
                set_flash('success', 'Admin blocked.');
                header('Location: admins.php');
                exit;
            }
        }

        if ($action === 'unblock') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid admin.';
            } else {
                $stmt = $pdo->prepare("UPDATE admin_users SET is_blocked = 0, blocked_reason = NULL WHERE id = ?");
                $stmt->execute([$id]);
                set_flash('success', 'Admin unblocked.');
                header('Location: admins.php');
                exit;
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid admin.';
            } else {
                // Prevent deleting yourself
                if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
                    $errors[] = 'You cannot delete your own account.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
                    $stmt->execute([$id]);
                    set_flash('success', 'Admin deleted.');
                    header('Location: admins.php');
                    exit;
                }
            }
        }
    }
}

$admins = $pdo->query("SELECT id, name, email, role, is_blocked, blocked_reason, created_at FROM admin_users ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-user-shield me-2"></i>Admins</h3>
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

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card p-3">
            <h5 class="mb-3">Add admin</h5>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="create">

                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input class="form-control" name="email" type="email" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Password</label>
                    <input class="form-control" name="password" type="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-plus me-2"></i>Create</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card p-3">
            <h5 class="mb-3">All admins</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo admin_h($a['name']); ?></div>
                                <div class="text-muted small"><?php echo admin_h($a['email']); ?> &bull; <?php echo admin_h(date('M d, Y', strtotime($a['created_at']))); ?></div>
                                <?php if ((int)$a['is_blocked'] === 1): ?>
                                    <div class="text-danger small mt-1"><i class="fas fa-ban me-1"></i><?php echo admin_h($a['blocked_reason'] ?? 'Blocked'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-light"><?php echo admin_h($a['role']); ?></span></td>
                            <td>
                                <?php if ((int)$a['is_blocked'] === 1): ?>
                                    <span class="badge text-bg-danger">Blocked</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button"
                                        onclick="openEdit(<?php echo (int)$a['id']; ?>, '<?php echo admin_h($a['name']); ?>', '<?php echo admin_h($a['email']); ?>', '<?php echo admin_h($a['role']); ?>')">
                                    <i class="fas fa-pen me-1"></i>Edit
                                </button>

                                <?php if ((int)$a['is_blocked'] === 1): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="unblock">
                                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                        <button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-unlock me-1"></i>Unblock</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="openBlock(<?php echo (int)$a['id']; ?>)">
                                        <i class="fas fa-ban me-1"></i>Block
                                    </button>
                                <?php endif; ?>

                                <button
                                    class="btn btn-sm btn-outline-danger delete-admin-btn"
                                    type="button"
                                    data-admin-id="<?php echo (int)$a['id']; ?>"
                                    title="Delete admin">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Edit admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id" value="">

                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input class="form-control" name="email" id="edit_email" type="email" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" id="edit_role">
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">New password (optional)</label>
                        <input class="form-control" name="new_password" type="password" placeholder="Leave blank to keep current">
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

<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i>Block admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="id" id="block_id" value="">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" name="reason" required placeholder="Reason shown on admin login"></textarea>
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
function openEdit(id, name, email, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role === 'super_admin' ? 'super_admin' : 'admin';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function openBlock(id) {
    document.getElementById('block_id').value = id;
    new bootstrap.Modal(document.getElementById('blockModal')).show();
}

const ADMIN_CSRF_TOKEN = <?php echo json_encode((string)($_SESSION['csrf_token'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
async function deleteAdmin(adminId) {
    const form = new URLSearchParams();
    form.append('admin_id', String(adminId));
    form.append('csrf_token', ADMIN_CSRF_TOKEN);

    const res = await fetch('api/delete_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: form.toString()
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Delete failed');
    return data;
}

document.querySelectorAll('.delete-admin-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const adminId = btn.dataset.adminId;
        if (!adminId) return;
        if (!confirm('Delete this admin?')) return;
        try {
            await deleteAdmin(adminId);
            btn.closest('tr').remove();
        } catch (e) {
            alert(e.message || 'Delete failed');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

