<?php
require_once __DIR__ . '/includes/bootstrap.php';
admin_logout();
set_flash('success', 'Logged out.');
header('Location: login.php');
exit;

