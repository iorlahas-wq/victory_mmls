<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/includes/auth.php';

if (!is_post()) {
    redirect('index.php');
}

verify_csrf();

logout_user();

flash(
    'success',
    'You have been logged out successfully.'
);

redirect('login.php');