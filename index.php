<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . BASE_URL . (is_logged_in() ? '/dashboard.php' : '/login.php'));
exit;
