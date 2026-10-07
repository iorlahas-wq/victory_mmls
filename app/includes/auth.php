<?php
declare(strict_types=1);


/**
 * ============================================================
 * AUTHENTICATION HELPERS
 * ============================================================
 */


/**
 * Return the currently authenticated user.
 */
function current_user(): ?array
{
    if (
        empty($_SESSION['user']) ||
        !is_array($_SESSION['user'])
    ) {
        return null;
    }

    return $_SESSION['user'];
}


/**
 * Determine whether a user is logged in.
 */
function is_logged_in(): bool
{
    return current_user() !== null;
}


/**
 * Log a user into the application.
 */
function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}


/**
 * Log the current user out.
 */
function logout_user(): void
{
    unset($_SESSION['user']);

    session_regenerate_id(true);
}


/**
 * Require authentication.
 */
function require_login(): void
{
    if (!is_logged_in()) {

        flash(
            'error',
            'Please log in to continue.'
        );

        redirect('login.php');
    }
}


/**
 * Require a specific role.
 */
function require_role(string ...$roles): void
{
    require_login();

    $user = current_user();

    if ($user === null) {
        redirect('login.php');
    }

    if (!in_array($user['role'], $roles, true)) {

        http_response_code(403);

        exit(
            'Access denied. You do not have permission to access this page.'
        );
    }
}