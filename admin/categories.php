<?php
$admin_page_title = 'Categories';
require_once __DIR__ . '/includes/header.php';
admin_require_login();

$errors = [];

// Create / Update / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '' || $slug === '') {
                $errors[] = 'Name and slug are required.';
            }

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, is_active) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $slug, $is_active]);
                    set_flash('success', 'Category created.');
                    header('Location: categories.php');
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Could not create category (duplicate name/slug?).';
                }
            }
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0 || $name === '' || $slug === '') {
                $errors[] = 'Invalid category data.';
            }

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $is_active, $id]);
                    set_flash('success', 'Category updated.');
                    header('Location: categories.php');
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Could not update category (duplicate name/slug?).';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid category.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                set_flash('success', 'Category deleted.');
                header('Location: categories.php');
                exit;
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-tags me-2"></i>Categories</h3>
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
            <h5 class="mb-3">Add category</h5>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="create">

                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" required placeholder="Technology">
                </div>
                <div class="mb-2">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" required placeholder="technology">
                    <div class="text-muted small mt-1">Lowercase, no spaces. Used in URLs/admin.</div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-plus me-2"></i>Create</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card p-3">
            <h5 class="mb-3">All categories</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?php echo admin_h($cat['name']); ?></td>
                                <td><code><?php echo admin_h($cat['slug']); ?></code></td>
                                <td>
                                    <?php if ((int)$cat['is_active'] === 1): ?>
                                        <span class="badge text-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                            onclick="fillEdit(<?php echo (int)$cat['id']; ?>, '<?php echo admin_h($cat['name']); ?>', '<?php echo admin_h($cat['slug']); ?>', <?php echo (int)$cat['is_active']; ?>)">
                                        <i class="fas fa-pen me-1"></i>Edit
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this category?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-3 mt-3" id="editCard" style="display:none;">
            <h5 class="mb-3">Edit category</h5>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo admin_h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id" value="">

                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" id="edit_name" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" id="edit_slug" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_active">
                    <label class="form-check-label" for="edit_active">Active</label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-2"></i>Save</button>
                    <button class="btn btn-outline-secondary" type="button" onclick="hideEdit()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillEdit(id, name, slug, active) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_slug').value = slug;
    document.getElementById('edit_active').checked = active === 1;
    document.getElementById('editCard').style.display = 'block';
    window.scrollTo({ top: document.getElementById('editCard').offsetTop - 20, behavior: 'smooth' });
}
function hideEdit() {
    document.getElementById('editCard').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

