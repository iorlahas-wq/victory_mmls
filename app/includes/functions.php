<?php
declare(strict_types=1);

/**
 * ============================================================
 * GENERAL APPLICATION HELPERS
 * ============================================================
 */


/**
 * Escape output safely for HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/**
 * Generate an application URL.
 *
 * Examples:
 *
 * url()
 * url('login.php')
 * url('assets/css/style.css')
 * url('app/admin/index.php')
 */
function url(string $path = ''): string
{
    $baseUrl = rtrim(APP_URL, '/');
    $path = ltrim($path, '/');

    if ($path === '') {
        return $baseUrl . '/';
    }

    return $baseUrl . '/' . $path;
}


/**
 * Redirect to an application URL.
 *
 * Example:
 *
 * redirect('login.php');
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}


/**
 * Determine whether the current request is POST.
 */
function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}


/**
 * Retrieve previously submitted form data.
 *
 * Example:
 *
 * value="<?= e(old('full_name')) ?>"
 */
function old(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}


/**
 * Store a flash message in the session.
 *
 * Supported types may include:
 *
 * success
 * error
 * info
 * warning
 */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}


/**
 * Consume flash messages.
 *
 * If a type is supplied, only messages of that type
 * are returned.
 *
 * Example:
 *
 * consume_flash('success');
 * consume_flash('error');
 *
 * If no type is supplied, all flash messages are returned.
 */
function consume_flash(?string $type = null): array|string|null
{
    $messages = $_SESSION['_flash'] ?? [];

    if (!is_array($messages)) {
        unset($_SESSION['_flash']);

        return $type === null ? [] : null;
    }

    /*
     * Return and remove all messages.
     */
    if ($type === null) {
        unset($_SESSION['_flash']);

        return $messages;
    }


    /*
     * Find the requested message type.
     */
    $matched = null;
    $remaining = [];

    foreach ($messages as $message) {

        if (
            is_array($message) &&
            ($message['type'] ?? '') === $type
        ) {
            /*
             * Keep the first matching message.
             */
            if ($matched === null) {
                $matched = $message['message'] ?? '';
            }

            continue;
        }

        $remaining[] = $message;
    }


    /*
     * Preserve messages that were not consumed.
     */
    if (!empty($remaining)) {
        $_SESSION['_flash'] = $remaining;
    } else {
        unset($_SESSION['_flash']);
    }


    return $matched;
}


/**
 * Generate or retrieve the current CSRF token.
 */
function csrf_token(): string
{
    if (
        !isset($_SESSION['_csrf_token']) ||
        !is_string($_SESSION['_csrf_token']) ||
        $_SESSION['_csrf_token'] === ''
    ) {
        $_SESSION['_csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['_csrf_token'];
}


/**
 * Generate a hidden CSRF form field.
 *
 * Example:
 *
 * <form method="post">
 *     <?= csrf_field() ?>
 * </form>
 */
function csrf_field(): string
{
    return sprintf(
        '<input type="hidden" name="_csrf" value="%s">',
        e(csrf_token())
    );
}


/**
 * Verify the submitted CSRF token.
 *
 * Stops the request if the token is invalid.
 */
function verify_csrf(): void
{
    $submittedToken = $_POST['_csrf'] ?? '';

    if (
        !is_string($submittedToken) ||
        $submittedToken === '' ||
        !hash_equals(
            csrf_token(),
            $submittedToken
        )
    ) {
        http_response_code(419);

        exit('Invalid request token.');
    }
}