<?php

declare(strict_types=1);

/**
 * ============================================================
 * COMMON APPLICATION HEADER
 * ============================================================
 *
 * Shared navigation for:
 * - Public visitors
 * - Administrators
 * - Instructors
 * - Learners
 *
 * Assessment management is shared between Admin and Instructor
 * through:
 *
 *     app/assessment/index.php
 *
 * Learners will later use:
 *
 *     app/learner/assessments/index.php
 *
 * to take assessments.
 */

$pageTitle = $pageTitle ?? APP_NAME;
$showNavbar = $showNavbar ?? true;

/*
 * Load authentication helpers so the header can determine
 * whether the current visitor is authenticated.
 */
require_once __DIR__ . '/auth.php';

$user = current_user();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Ebenezer's Kitchen Web-Based Multimedia Learning System for Vocational Skills Acquisition."
    >

    <title>
        <?= e($pageTitle) ?> | <?= e(APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

</head>

<body>

<?php if ($showNavbar): ?>

<nav class="main-navbar">

    <div class="container navbar-inner">

        <!-- ==================================================
             BRAND
        =================================================== -->

        <a
            href="<?= e(
                url(
                    $user !== null
                        ? 'app/' . $user['role'] . '/index.php'
                        : 'index.php'
                )
            ) ?>"
            class="navbar-brand"
        >

            <img
                src="<?= e(url('assets/images/mmls-logo.jpg')) ?>"
                alt="Ebenezer's Kitchen Logo"
                class="navbar-logo"
            >

            <span class="navbar-brand-text">

                <strong>
                    Ebenezer's Kitchen
                </strong>

                <small>
                    Multimedia Learning System
                </small>

            </span>

        </a>


        <!-- ==================================================
             PUBLIC NAVIGATION
        =================================================== -->

        <?php if ($user === null): ?>

            <div class="navbar-links">

                <a
                    href="<?= e(url('index.php')) ?>"
                    class="nav-link"
                >
                    Home
                </a>

                <a
                    href="<?= e(url('index.php#learning')) ?>"
                    class="nav-link"
                >
                    Learning
                </a>

                <a
                    href="<?= e(url('index.php#about')) ?>"
                    class="nav-link"
                >
                    About
                </a>

                <a
                    href="<?= e(url('login.php')) ?>"
                    class="nav-login"
                >
                    Login
                </a>

            </div>


        <!-- ==================================================
             ADMIN NAVIGATION
        =================================================== -->

        <?php elseif ($user['role'] === 'admin'): ?>

            <div class="navbar-links">

                <a
                    href="<?= e(url('app/admin/index.php')) ?>"
                    class="nav-link"
                >
                    Dashboard
                </a>

                <a
                    href="<?= e(url('app/admin/videos/index.php')) ?>"
                    class="nav-link"
                >
                    Videos
                </a>

                <a
                    href="<?= e(url('app/admin/skills/index.php')) ?>"
                    class="nav-link"
                >
                    Skills
                </a>

                <a
                    href="<?= e(url('app/admin/lessons/index.php')) ?>"
                    class="nav-link"
                >
                    Lessons
                </a>

                <a
                    href="<?= e(url('app/assessment/index.php')) ?>"
                    class="nav-link"
                >
                    Assessments
                </a>

                <a
                    href="<?= e(url('app/admin/users/index.php')) ?>"
                    class="nav-link"
                >
                    Users
                </a>

                <span class="nav-user">
                    <?= e($user['full_name']) ?>
                </span>

                <form
                    method="post"
                    action="<?= e(url('logout.php')) ?>"
                    class="logout-form"
                >

                    <?= csrf_field() ?>

                    <button
                        type="submit"
                        class="nav-logout"
                    >
                        Logout
                    </button>

                </form>

            </div>


        <!-- ==================================================
             INSTRUCTOR NAVIGATION
        =================================================== -->

        <?php elseif ($user['role'] === 'instructor'): ?>

            <div class="navbar-links">

                <a
                    href="<?= e(url('app/instructor/index.php')) ?>"
                    class="nav-link"
                >
                    Dashboard
                </a>

                <a
                    href="<?= e(url('app/instructor/videos/index.php')) ?>"
                    class="nav-link"
                >
                    Videos
                </a>

                <a
                    href="<?= e(url('app/instructor/lessons/index.php')) ?>"
                    class="nav-link"
                >
                    My Lessons
                </a>

                <a
                    href="<?= e(url('app/assessment/index.php')) ?>"
                    class="nav-link"
                >
                    Assessments
                </a>

                <span class="nav-user">
                    <?= e($user['full_name']) ?>
                </span>

                <form
                    method="post"
                    action="<?= e(url('logout.php')) ?>"
                    class="logout-form"
                >

                    <?= csrf_field() ?>

                    <button
                        type="submit"
                        class="nav-logout"
                    >
                        Logout
                    </button>

                </form>

            </div>


        <!-- ==================================================
             LEARNER NAVIGATION
        =================================================== -->

        <?php elseif ($user['role'] === 'learner'): ?>

            <div class="navbar-links">

                <a
                    href="<?= e(url('app/learner/index.php')) ?>"
                    class="nav-link"
                >
                    Dashboard
                </a>

                <a
                    href="<?= e(url('app/learner/lessons/index.php')) ?>"
                    class="nav-link"
                >
                    Learning
                </a>

                <!--
                 * Learner assessment interface.
                 *
                 * This route will be implemented in the learner
                 * assessment module. The link is intentionally
                 * placed here now so the navigation structure is
                 * ready for the assessment-taking feature.
                 -->
                <a
                    href="<?= e(url('app/learner/assessments/index.php')) ?>"
                    class="nav-link"
                >
                    Assessments
                </a>

                <span class="nav-user">
                    <?= e($user['full_name']) ?>
                </span>

                <form
                    method="post"
                    action="<?= e(url('logout.php')) ?>"
                    class="logout-form"
                >

                    <?= csrf_field() ?>

                    <button
                        type="submit"
                        class="nav-logout"
                    >
                        Logout
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>

</nav>

<?php endif; ?>


<?php
/*
 * ============================================================
 * FLASH MESSAGES
 * ============================================================
 *
 * consume_flash() returns all queued flash messages at once.
 */

$flashMessages = consume_flash();
?>

<?php if (!empty($flashMessages)): ?>

    <div class="container flash-container">

        <?php foreach ($flashMessages as $flash): ?>

            <?php
            $flashType = (string) ($flash['type'] ?? 'info');
            $flashMessage = (string) ($flash['message'] ?? '');
            ?>

            <?php if ($flashMessage !== ''): ?>

                <div class="alert alert-<?= e($flashType) ?>">
                    <?= e($flashMessage) ?>
                </div>

            <?php endif; ?>

        <?php endforeach; ?>

    </div>

<?php endif; ?>
