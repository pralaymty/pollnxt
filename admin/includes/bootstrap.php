<?php
require_once __DIR__ . '/../../config/db.php';

function admin_is_logged_in(): bool {
    return isset($_SESSION['admin_id']);
}

function admin_require_login(): void {
    if (!admin_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_require_super(): void {
    admin_require_login();
    if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
        set_flash('error', 'Access denied.');
        header('Location: index.php');
        exit;
    }
}

function admin_logout(): void {
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_role']);
}

function admin_h(string $str): string {
    return h($str);
}

