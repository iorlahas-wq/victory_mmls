<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/includes/auth.php';

$pageTitle = 'Login';
$showNavbar = false;

$error = '';

/*
 * ------------------------------------------------------------
 * Redirect already logged-in users
 * ------------------------------------------------------------
 */

if (is_logged_in()) {

    $user = current_user();

    if ($user !== null) {

        switch ($user['role']) {

            case 'admin':
                redirect('app/admin/index.php');
                break;

            case 'instructor':
                redirect('app/instructor/index.php');
                break;

            case 'learner':
                redirect('app/learner/index.php');
                break;

        }
    }
}


/*
 * ------------------------------------------------------------
 * Process Login
 * ------------------------------------------------------------
 */

if (is_post()) {

    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    /*
     * Basic validation
     */
    if ($email === '') {

        $error = 'Please enter your email address.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif ($password === '') {

        $error = 'Please enter your password.';

    } else {

        /*
         * Find the user.
         */
        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                full_name,
                email,
                password_hash,
                role,
                is_active
            FROM users
            WHERE email = :email
            LIMIT 1
            "
        );

        $stmt->execute([
            ':email' => $email
        ]);

        $user = $stmt->fetch();


        /*
         * Verify account and password.
         */
        if (
            !$user ||
            !(bool) $user['is_active'] ||
            !password_verify($password, $user['password_hash'])
        ) {

            $error = 'Invalid email address or password.';

        } else {

            /*
             * Regenerate the session ID after successful login.
             */
            session_regenerate_id(true);

            /*
             * Store authenticated user.
             */
            login_user($user);

            /*
             * Redirect according to role.
             */
            switch ($user['role']) {

                case 'admin':
                    redirect('app/admin/index.php');
                    break;

                case 'instructor':
                    redirect('app/instructor/index.php');
                    break;

                case 'learner':
                    redirect('app/learner/index.php');
                    break;

                default:

                    logout_user();

                    $error = 'Your account has an invalid role.';
                    break;
            }
        }
    }
}


/*
 * ------------------------------------------------------------
 * Page
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/app/includes/header.php';

?>

<main class="login-page">

    <div class="login-wrapper">

        <section class="login-card">

            <!-- LOGO -->

            <div class="login-logo">

                <img
                    src="<?= e(url('assets/images/mmls-logo.jpg')) ?>"
                    alt="Ebenezer's Kitchen Logo"
                >

            </div>


            <!-- HEADING -->

            <div class="login-heading">

                <span class="section-label">
                    LEARNING SYSTEM
                </span>

                <h1>
                    Welcome Back
                </h1>

                <p>
                    Sign in to access your learning account.
                </p>

            </div>


            <!-- ERROR -->

            <?php if ($error !== ''): ?>

                <div class="login-error">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <!-- LOGIN FORM -->

            <form
                method="post"
                action="<?= e(url('login.php')) ?>"
                class="login-form"
                novalidate
            >

                <?= csrf_field() ?>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e(old('email')) ?>"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        required
                        autofocus
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="password-field">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="login-submit"
                >
                    Sign In
                </button>

            </form>


            <!-- RETURN -->

            <div class="login-footer">

                <a href="<?= e(url('index.php')) ?>">
                    ← Return to home
                </a>

            </div>

        </section>


        <!-- INFORMATION SIDE -->

        <aside class="login-info">

            <div class="login-info-content">

                <span class="hero-label">
                    EBENEZER'S KITCHEN
                </span>

                <h2>
                    Learn practical skills through multimedia.
                </h2>

                <p>
                    Access structured vocational lessons,
                    instructional images, practical videos
                    and online assessments.
                </p>

                <div class="login-info-points">

                    <div>
                        <strong>01</strong>
                        <span>Structured lessons</span>
                    </div>

                    <div>
                        <strong>02</strong>
                        <span>Practical demonstrations</span>
                    </div>

                    <div>
                        <strong>03</strong>
                        <span>Online assessment</span>
                    </div>

                </div>

            </div>

        </aside>

    </div>

</main>

<?php

require_once __DIR__ . '/app/includes/footer.php';

?>