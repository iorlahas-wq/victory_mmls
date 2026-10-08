<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Admin Dashboard';
$showNavbar = true;


/*
 * ============================================================
 * DASHBOARD STATISTICS
 * ============================================================
 */


/*
 * Learners
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'learner'
      AND is_active = 1
    "
);

$learnerCount = (int) $stmt->fetchColumn();


/*
 * Instructors
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'instructor'
      AND is_active = 1
    "
);

$instructorCount = (int) $stmt->fetchColumn();


/*
 * Active skills
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM skills
    WHERE is_active = 1
    "
);

$skillCount = (int) $stmt->fetchColumn();


/*
 * Total lessons
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM lessons
    "
);

$lessonCount = (int) $stmt->fetchColumn();


/*
 * Published lessons
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM lessons
    WHERE is_published = 1
    "
);

$publishedLessonCount = (int) $stmt->fetchColumn();


/*
 * Assessment questions
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM questions
    WHERE is_active = 1
    "
);

$questionCount = (int) $stmt->fetchColumn();


/*
 * Active instructional videos
 *
 * Videos are stored in the existing lesson_contents table
 * using content_type = 'video'.
 */
$stmt = $pdo->query(
    "
    SELECT COUNT(*)
    FROM lesson_contents
    WHERE content_type = 'video'
      AND is_active = 1
    "
);

$videoCount = (int) $stmt->fetchColumn();


/*
 * Current administrator
 */
$user = current_user();


require_once __DIR__ . '/../includes/header.php';

?>

<main class="admin-page">

    <div class="container">


        <!-- ==================================================
             PAGE HEADING
        =================================================== -->

        <section class="admin-welcome">

            <div>

                <span class="section-label">
                    ADMINISTRATION
                </span>

                <h1>
                    Welcome,
                    <?= e($user['full_name']) ?>
                </h1>

                <p>
                    Manage the learning system, vocational skills,
                    lessons, multimedia content, users and assessments.
                </p>

            </div>

        </section>


        <!-- ==================================================
             STATISTICS
        =================================================== -->

        <section class="dashboard-stats">


            <!-- LEARNERS -->

            <div class="stat-card">

                <div class="stat-card-top">

                    <span class="stat-label">
                        Active Learners
                    </span>

                    <span class="stat-number">
                        <?= $learnerCount ?>
                    </span>

                </div>

                <p>
                    Registered learners
                </p>

            </div>


            <!-- INSTRUCTORS -->

            <div class="stat-card">

                <div class="stat-card-top">

                    <span class="stat-label">
                        Instructors
                    </span>

                    <span class="stat-number">
                        <?= $instructorCount ?>
                    </span>

                </div>

                <p>
                    Active instructors
                </p>

            </div>


            <!-- SKILLS -->

            <div class="stat-card">

                <div class="stat-card-top">

                    <span class="stat-label">
                        Skills
                    </span>

                    <span class="stat-number">
                        <?= $skillCount ?>
                    </span>

                </div>

                <p>
                    Active vocational skills
                </p>

            </div>


            <!-- LESSONS -->

            <div class="stat-card">

                <div class="stat-card-top">

                    <span class="stat-label">
                        Lessons
                    </span>

                    <span class="stat-number">
                        <?= $lessonCount ?>
                    </span>

                </div>

                <p>
                    <?= $publishedLessonCount ?>
                    currently published
                </p>

            </div>


        </section>


        <!-- ==================================================
             MANAGEMENT AREA
        =================================================== -->

        <section class="admin-section">

            <div class="section-heading">

                <span class="section-label">
                    SYSTEM MANAGEMENT
                </span>

                <h2>
                    Manage the learning system
                </h2>

                <p>
                    Use the options below to configure learning
                    content and manage users.
                </p>

            </div>


            <div class="admin-actions">


                <!-- SKILLS -->

                <a
                    href="<?= e(
                        url('app/admin/skills/index.php')
                    ) ?>"
                    class="admin-action-card"
                >

                    <span class="admin-action-number">
                        01
                    </span>

                    <h3>
                        Vocational Skills
                    </h3>

                    <p>
                        Create and manage the vocational skills
                        taught through the learning system.
                    </p>

                    <span class="admin-action-link">
                        Manage Skills →
                    </span>

                </a>


                <!-- LESSONS -->

                <a
                    href="<?= e(
                        url('app/admin/lessons/index.php')
                    ) ?>"
                    class="admin-action-card"
                >

                    <span class="admin-action-number">
                        02
                    </span>

                    <h3>
                        Lessons
                    </h3>

                    <p>
                        Create lessons and organise learning
                        materials for each vocational skill.
                    </p>

                    <span class="admin-action-link">
                        Manage Lessons →
                    </span>

                </a>


                <!-- USERS -->

                <a
                    href="<?= e(
                        url('app/admin/users/index.php')
                    ) ?>"
                    class="admin-action-card"
                >

                    <span class="admin-action-number">
                        03
                    </span>

                    <h3>
                        Users
                    </h3>

                    <p>
                        Manage administrators, instructors
                        and learners registered on the system.
                    </p>

                    <span class="admin-action-link">
                        Manage Users →
                    </span>

                </a>


                <!-- VIDEOS -->

                <a
                    href="<?= e(
                        url('app/admin/videos/index.php')
                    ) ?>"
                    class="admin-action-card"
                >

                    <span class="admin-action-number">
                        04
                    </span>

                    <h3>
                        Videos
                    </h3>

                    <p>
                        Upload and manage the single instructional
                        video assigned to each lesson.
                    </p>

                    <span class="admin-action-link">
                        Manage Videos →
                    </span>

                </a>


                <!-- ASSESSMENTS -->

                <div class="admin-action-card admin-action-disabled">

                    <span class="admin-action-number">
                        05
                    </span>

                    <h3>
                        Assessments
                    </h3>

                    <p>
                        Manage lesson questions and assessment
                        results.
                    </p>

                    <span class="admin-action-status">
                        Coming next
                    </span>

                </div>


            </div>

        </section>


        <!-- ==================================================
             SYSTEM OVERVIEW
        =================================================== -->

        <section class="admin-overview">


            <!-- CONTENT OVERVIEW -->

            <div class="overview-card">

                <div class="overview-heading">

                    <span class="section-label">
                        CONTENT OVERVIEW
                    </span>

                    <h2>
                        Learning System Status
                    </h2>

                </div>


                <div class="overview-list">


                    <!-- TOTAL LESSONS -->

                    <div class="overview-row">

                        <span>
                            Total lessons
                        </span>

                        <strong>
                            <?= $lessonCount ?>
                        </strong>

                    </div>


                    <!-- PUBLISHED LESSONS -->

                    <div class="overview-row">

                        <span>
                            Published lessons
                        </span>

                        <strong>
                            <?= $publishedLessonCount ?>
                        </strong>

                    </div>


                    <!-- ACTIVE SKILLS -->

                    <div class="overview-row">

                        <span>
                            Active skills
                        </span>

                        <strong>
                            <?= $skillCount ?>
                        </strong>

                    </div>


                    <!-- ACTIVE VIDEOS -->

                    <div class="overview-row">

                        <span>
                            Active instructional videos
                        </span>

                        <strong>
                            <?= $videoCount ?>
                        </strong>

                    </div>


                    <!-- ASSESSMENT QUESTIONS -->

                    <div class="overview-row">

                        <span>
                            Assessment questions
                        </span>

                        <strong>
                            <?= $questionCount ?>
                        </strong>

                    </div>


                </div>

            </div>


            <!-- QUICK NOTE -->

            <div class="overview-card overview-message">

                <span class="section-label">
                    QUICK NOTE
                </span>

                <h2>
                    Build the learning content
                </h2>

                <p>
                    Start by creating the vocational skills and
                    lessons that learners will access through the
                    multimedia learning system.
                </p>

                <a
                    href="<?= e(
                        url('app/admin/skills/index.php')
                    ) ?>"
                    class="btn btn-primary"
                >
                    Start with Skills
                </a>

            </div>


        </section>


    </div>

</main>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>