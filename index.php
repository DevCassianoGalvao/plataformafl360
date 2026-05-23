<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    redirect('login.php');
}

if (($_SESSION['role'] ?? '') === 'admin') {
    redirect('admin/dashboard.php');
}

redirect('pages/dashboard.php');
