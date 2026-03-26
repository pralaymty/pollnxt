<?php
// Backward-compatible route: redirect to Google OAuth settings
require_once __DIR__ . '/includes/bootstrap.php';
admin_require_super();

header('Location: google_oauth.php');
exit;

