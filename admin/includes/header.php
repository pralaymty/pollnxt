<?php
require_once __DIR__ . '/bootstrap.php';
$admin_page_title = $admin_page_title ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_h($admin_page_title); ?> | POLLNXT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../style.css" rel="stylesheet">
    <style>
        /* Professional admin theme: light main + dark sidebar */
        body { background: #f1f5f9; color: #0f172a; }
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 240px;
            background: radial-gradient(circle at top left, #1f2937, #0b1220);
            color: #e5e7eb;
            padding: 1.25rem 0.75rem;
        }
        .admin-sidebar .brand {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #f9fafb;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .admin-sidebar .brand i {
            color: #00AF91;
        }
        .admin-menu {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .admin-menu .nav-link {
            color: rgba(229,231,235,.9);
            border-radius: .5rem;
            padding: .55rem .75rem;
            font-size: .92rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .admin-menu .nav-link i {
            font-size: .95rem;
        }
        .admin-menu .nav-link:hover,
        .admin-menu .nav-link.active {
            background: rgba(0,175,145,0.16);
            color: #ffffff;
        }
        .admin-main {
            flex: 1;
            background: #f8fafc;
            padding: 1.5rem 2rem;
        }

        /* Top bar inside main */
        .admin-topbar {
            background: #0b1220;
            border: 1px solid rgba(15,23,42,.12);
            border-radius: 14px;
            padding: .85rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .admin-topbar .topbar-muted {
            color: rgba(255,255,255,.72);
            font-size: .85rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .admin-topbar .topbar-name {
            color: #ffffff;
            font-weight: 650;
            font-size: 1rem;
        }
        .admin-topbar .btn-outline-secondary {
            border-color: rgba(255,255,255,.35);
            color: rgba(255,255,255,.9);
        }
        .admin-topbar .btn-outline-secondary:hover {
            background: rgba(255,255,255,.08);
            border-color: rgba(255,255,255,.45);
        }

        /* Content surfaces */
        .admin-card {
            border-radius: 1rem;
            border: 1px solid rgba(15,23,42,.08);
            background: #ffffff;
            box-shadow: 0 10px 25px rgba(15,23,42,.08);
        }
        .admin-main .card {
            background: #ffffff;
            border-radius: 1rem;
            border: 1px solid rgba(15,23,42,.08);
            box-shadow: 0 10px 25px rgba(15,23,42,.06);
        }

        /* Typography + tables/forms */
        .admin-main .text-muted { color: #64748b !important; }
        .admin-main .table { color: #0f172a; }
        .admin-main .table thead th { color: #334155; border-bottom-color: rgba(15,23,42,.12); }
        .admin-main .table td, .admin-main .table th { border-top-color: rgba(15,23,42,.08); }

        .admin-main .form-control,
        .admin-main .form-select {
            background: #ffffff;
            color: #0f172a;
            border: 1px solid rgba(15,23,42,.12);
        }
        .admin-main .form-control::placeholder { color: #94a3b8; }
        .admin-main .form-control:focus,
        .admin-main .form-select:focus {
            border-color: rgba(0,175,145,.6);
            box-shadow: 0 0 0 .2rem rgba(0,175,145,.15);
        }

        .admin-main .modal-content { border-radius: 1rem; border: 1px solid rgba(15,23,42,.12); }

        /* Settings submenu */
        details.settings-details {
            width: 100%;
        }
        details.settings-details summary {
            list-style: none;
            outline: none;
            padding-left: .75rem;
            padding-right: .75rem;
            border-radius: .5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .settings-chevron {
            color: rgba(229,231,235,.9);
            transition: transform .2s ease;
            margin-left: .75rem;
        }
        details.settings-details[open] .settings-chevron {
            transform: rotate(180deg);
        }
        .settings-submenu {
            padding-left: 1rem;
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }
        .settings-submenu .nav-link {
            background: transparent;
            padding-left: 1.0rem;
            padding-right: .75rem;
            color: rgba(229,231,235,.9);
            border-radius: .5rem;
        }
        .settings-submenu .nav-link:hover {
            color: #ffffff;
            background: rgba(0,175,145,0.16);
        }

        /* Mobile: sidebar hidden already; add breathing room */
        @media (max-width: 768px) {
            .admin-main { padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar d-none d-md-block">
        <div class="brand">
            <i class="fas fa-square-poll-vertical"></i>POLLNXT ADMIN
        </div>
        <ul class="admin-menu nav flex-column">
            <?php if (admin_is_logged_in()): ?>
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-gauge"></i><span>Dashboard</span></a></li>
                <li class="nav-item"><a class="nav-link" href="categories.php"><i class="fas fa-tags"></i><span>Categories</span></a></li>
                <li class="nav-item"><a class="nav-link" href="polls.php"><i class="fas fa-poll-h"></i><span>Polls</span></a></li>
                <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users"></i><span>Users</span></a></li>
                <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="admins.php"><i class="fas fa-user-shield"></i><span>Admins</span></a></li>
                    <li class="nav-item mt-2">
                        <details class="settings-details">
                            <summary class="nav-link" style="cursor:pointer;">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="fas fa-gear"></i><span>Settings</span>
                                </span>
                                <i class="fas fa-chevron-down settings-chevron" aria-hidden="true"></i>
                            </summary>
                            <div class="settings-submenu">
                                <a class="nav-link" style="margin-top:.2rem;" href="google_oauth.php">
                                    <i class="fab fa-google me-2" style="width:1.2rem;"></i>Google OAuth
                                </a>
                                <a class="nav-link" style="margin-top:.2rem;" href="pricing.php">
                                    <i class="fas fa-coins me-2" style="width:1.2rem;"></i>Pricing
                                </a>
                                <a class="nav-link" style="margin-top:.2rem;" href="razorpay.php">
                                    <i class="fas fa-money-bill-wave me-2" style="width:1.2rem;"></i>Razorpay
                                </a>
                            </div>
                        </details>
                    </li>
                <?php endif; ?>
            <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt"></i><span>Login</span></a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="admin-main">
        <?php if (admin_is_logged_in()): ?>
            <div class="admin-topbar">
                <div>
                    <div class="topbar-muted">Signed in as</div>
                    <div class="topbar-name"><i class="fas fa-user-shield me-1"></i><?php echo admin_h($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                </div>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-right-from-bracket me-1"></i>Logout
                </a>
            </div>
        <?php endif; ?>

        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : admin_h($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo admin_h($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
