<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('instructor');

$user = current_user();
$instructorId = (int) $user['id'];

$pageTitle = 'Instructor Dashboard';
$showNavbar = true;

/*
|--------------------------------------------------------------------------
| Lesson statistics
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_lessons,
        COALESCE(SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END), 0)
            AS published_lessons
     FROM lessons
     WHERE created_by = :instructor_id"
);
$stmt->execute(['instructor_id' => $instructorId]);
$lessonStats = $stmt->fetch() ?: [];

$totalLessons = (int) ($lessonStats['total_lessons'] ?? 0);
$publishedLessons = (int) ($lessonStats['published_lessons'] ?? 0);
$unpublishedLessons = max(0, $totalLessons - $publishedLessons);

$publishedPercent = $totalLessons > 0
    ? (int) round(($publishedLessons / $totalLessons) * 100)
    : 0;

$unpublishedPercent = $totalLessons > 0
    ? (int) round(($unpublishedLessons / $totalLessons) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| Active learning-content counts by type
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "SELECT
        lc.content_type,
        COUNT(*) AS item_count
     FROM lesson_contents lc
     INNER JOIN lessons l ON l.id = lc.lesson_id
     WHERE l.created_by = :instructor_id
       AND lc.is_active = 1
       AND lc.content_type IN ('text', 'image', 'video')
     GROUP BY lc.content_type"
);
$stmt->execute(['instructor_id' => $instructorId]);

$contentCounts = [
    'text' => 0,
    'image' => 0,
    'video' => 0,
];

foreach ($stmt->fetchAll() as $row) {
    $type = (string) $row['content_type'];

    if (array_key_exists($type, $contentCounts)) {
        $contentCounts[$type] = (int) $row['item_count'];
    }
}

$totalLearningItems = array_sum($contentCounts);
$maxContentCount = max(1, ...array_values($contentCounts));

/*
|--------------------------------------------------------------------------
| Assessment question counts
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_questions,
        COALESCE(SUM(CASE WHEN q.is_active = 1 THEN 1 ELSE 0 END), 0)
            AS active_questions
     FROM questions q
     INNER JOIN lessons l ON l.id = q.lesson_id
     WHERE l.created_by = :instructor_id"
);
$stmt->execute(['instructor_id' => $instructorId]);
$questionStats = $stmt->fetch() ?: [];

$totalQuestions = (int) ($questionStats['total_questions'] ?? 0);
$activeQuestions = (int) ($questionStats['active_questions'] ?? 0);
$inactiveQuestions = max(0, $totalQuestions - $activeQuestions);

$activeQuestionPercent = $totalQuestions > 0
    ? (int) round(($activeQuestions / $totalQuestions) * 100)
    : 0;

$inactiveQuestionPercent = $totalQuestions > 0
    ? (int) round(($inactiveQuestions / $totalQuestions) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| Donut chart values
|--------------------------------------------------------------------------
*/
$questionDonutStyle = $totalQuestions > 0
    ? 'conic-gradient(#2d7d4b 0% ' . $activeQuestionPercent .
      '%, #e5a642 ' . $activeQuestionPercent . '% 100%)'
    : 'conic-gradient(#e8ece9 0% 100%)';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Dashboard chart styles are local to this page. */
.instructor-analytics {
    margin: 30px 0 42px;
}

.instructor-analytics-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.instructor-chart-card {
    min-width: 0;
    padding: 24px;
    background: #fff;
    border: 1px solid #dce6df;
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(20, 50, 30, 0.04);
}

.instructor-chart-card h3 {
    margin: 0 0 7px;
    color: #163c29;
    font-size: 1.12rem;
}

.instructor-chart-description {
    margin: 0 0 22px;
    color: #65736a;
    font-size: 0.9rem;
    line-height: 1.5;
}

.instructor-chart-total {
    margin: 0 0 20px;
    color: #173d29;
    font-size: 2rem;
    font-weight: 750;
    line-height: 1.1;
}

.instructor-chart-total small {
    display: block;
    margin-top: 6px;
    color: #6c776f;
    font-size: 0.82rem;
    font-weight: 500;
}

.instructor-chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 16px;
    margin: 0 0 20px;
    color: #4f5d53;
    font-size: 0.82rem;
}

.instructor-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.instructor-legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    flex: 0 0 10px;
    border-radius: 50%;
}

.instructor-dot-published,
.instructor-bar-published,
.instructor-dot-active {
    background: #2d7d4b;
}

.instructor-dot-unpublished,
.instructor-bar-unpublished,
.instructor-dot-inactive {
    background: #e5a642;
}

.instructor-dot-text,
.instructor-bar-text {
    background: #2d7d4b;
}

.instructor-dot-image,
.instructor-bar-image {
    background: #d4a33d;
}

.instructor-dot-video,
.instructor-bar-video {
    background: #477bb5;
}

.instructor-chart-row {
    margin-top: 18px;
}

.instructor-chart-row-heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
    color: #34443a;
    font-size: 0.9rem;
}

.instructor-chart-row-heading strong {
    color: #173d29;
    font-variant-numeric: tabular-nums;
}

.instructor-chart-track {
    overflow: hidden;
    height: 11px;
    background: #edf1ee;
    border-radius: 999px;
}

.instructor-chart-bar {
    height: 100%;
    min-width: 0;
    border-radius: 999px;
    transition: width 0.25s ease;
}

.instructor-chart-empty {
    margin-top: 14px;
    color: #758078;
    font-size: 0.85rem;
}

.instructor-question-chart {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 5px 0 12px;
}

.instructor-question-donut {
    display: grid;
    width: 150px;
    height: 150px;
    flex: 0 0 150px;
    place-items: center;
    border-radius: 50%;
    background: <?= $questionDonutStyle ?>;
}

.instructor-question-donut-hole {
    display: flex;
    width: 105px;
    height: 105px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff;
}

.instructor-question-donut-hole strong {
    color: #173d29;
    font-size: 1.8rem;
    line-height: 1.1;
}

.instructor-question-donut-hole span {
    margin-top: 4px;
    color: #6c776f;
    font-size: 0.76rem;
}

.instructor-question-legend {
    display: grid;
    gap: 14px;
}

.instructor-question-legend .legend-value {
    display: block;
    margin-top: 3px;
    color: #173d29;
    font-size: 1.2rem;
    font-weight: 700;
}

.instructor-analytics-note {
    margin-top: 16px;
    color: #758078;
    font-size: 0.8rem;
    line-height: 1.5;
}

@media (max-width: 1050px) {
    .instructor-analytics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .instructor-analytics-grid {
        grid-template-columns: 1fr;
    }

    .instructor-chart-card {
        padding: 20px;
    }
}
</style>

<main class="instructor-page">

    <div class="container">

        <!-- ======================================================
             WELCOME
        ======================================================= -->

        <section class="instructor-welcome">

            <span class="section-label">
                INSTRUCTOR AREA
            </span>

            <h1>
                Welcome, <?= e($user['full_name']) ?>
            </h1>

            <p>
                Create and manage vocational lessons, prepare learning
                materials, and build assessments for the lessons you teach.
            </p>

        </section>


        <!-- ======================================================
             ANALYTICS CHARTS
        ======================================================= -->

        <section class="instructor-analytics">

            <div class="section-heading">
                <span class="section-label">YOUR ACTIVITY AT A GLANCE</span>
                <h2>Teaching Overview</h2>
                <p>
                    Visual summaries of your lessons, learning materials
                    and assessment questions.
                </p>
            </div>

            <div class="instructor-analytics-grid">

                <!-- Lesson publication chart -->

                <article class="instructor-chart-card">

                    <h3>Lesson Publication</h3>

                    <p class="instructor-chart-description">
                        Published compared with unpublished lessons.
                    </p>

                    <p class="instructor-chart-total">
                        <?= $totalLessons ?>
                        <small>Total lessons created</small>
                    </p>

                    <div class="instructor-chart-legend">

                        <span class="instructor-legend-item">
                            <span class="instructor-legend-dot instructor-dot-published"></span>
                            Published
                        </span>

                        <span class="instructor-legend-item">
                            <span class="instructor-legend-dot instructor-dot-unpublished"></span>
                            Unpublished
                        </span>

                    </div>

                    <div class="instructor-chart-row">

                        <div class="instructor-chart-row-heading">
                            <span>Published</span>
                            <strong>
                                <?= $publishedLessons ?>
                                (<?= $publishedPercent ?>%)
                            </strong>
                        </div>

                        <div
                            class="instructor-chart-track"
                            role="img"
                            aria-label="<?= $publishedLessons ?> of <?= $totalLessons ?> lessons published"
                        >
                            <div
                                class="instructor-chart-bar instructor-bar-published"
                                style="width: <?= $publishedPercent ?>%;"
                            ></div>
                        </div>

                    </div>

                    <div class="instructor-chart-row">

                        <div class="instructor-chart-row-heading">
                            <span>Unpublished</span>
                            <strong>
                                <?= $unpublishedLessons ?>
                                (<?= $unpublishedPercent ?>%)
                            </strong>
                        </div>

                        <div
                            class="instructor-chart-track"
                            role="img"
                            aria-label="<?= $unpublishedLessons ?> of <?= $totalLessons ?> lessons unpublished"
                        >
                            <div
                                class="instructor-chart-bar instructor-bar-unpublished"
                                style="width: <?= $unpublishedPercent ?>%;"
                            ></div>
                        </div>

                    </div>

                    <?php if ($totalLessons === 0): ?>
                        <p class="instructor-chart-empty">
                            Create your first lesson to see publication statistics.
                        </p>
                    <?php endif; ?>

                </article>


                <!-- Learning materials chart -->

                <article class="instructor-chart-card">

                    <h3>Learning Materials</h3>

                    <p class="instructor-chart-description">
                        Active text, image and video items across your lessons.
                    </p>

                    <p class="instructor-chart-total">
                        <?= $totalLearningItems ?>
                        <small>Total active learning items</small>
                    </p>

                    <div class="instructor-chart-legend">

                        <span class="instructor-legend-item">
                            <span class="instructor-legend-dot instructor-dot-text"></span>
                            Text
                        </span>

                        <span class="instructor-legend-item">
                            <span class="instructor-legend-dot instructor-dot-image"></span>
                            Images
                        </span>

                        <span class="instructor-legend-item">
                            <span class="instructor-legend-dot instructor-dot-video"></span>
                            Videos
                        </span>

                    </div>

                    <?php
                    $materialRows = [
                        'text' => [
                            'label' => 'Text',
                            'class' => 'instructor-bar-text',
                        ],
                        'image' => [
                            'label' => 'Images',
                            'class' => 'instructor-bar-image',
                        ],
                        'video' => [
                            'label' => 'Videos',
                            'class' => 'instructor-bar-video',
                        ],
                    ];
                    ?>

                    <?php foreach ($materialRows as $type => $material): ?>

                        <?php
                        $count = $contentCounts[$type];
                        $percent = $maxContentCount > 0
                            ? (int) round(($count / $maxContentCount) * 100)
                            : 0;
                        ?>

                        <div class="instructor-chart-row">

                            <div class="instructor-chart-row-heading">
                                <span><?= e($material['label']) ?></span>
                                <strong><?= $count ?></strong>
                            </div>

                            <div
                                class="instructor-chart-track"
                                role="img"
                                aria-label="<?= e($material['label']) ?>: <?= $count ?> active items"
                            >
                                <div
                                    class="instructor-chart-bar <?= e($material['class']) ?>"
                                    style="width: <?= $percent ?>%;"
                                ></div>
                            </div>

                        </div>

                    <?php endforeach; ?>

                    <?php if ($totalLearningItems === 0): ?>
                        <p class="instructor-chart-empty">
                            Add active text, image or video content to see your material breakdown.
                        </p>
                    <?php endif; ?>

                </article>


                <!-- Assessment question chart -->

                <article class="instructor-chart-card">

                    <h3>Assessment Questions</h3>

                    <p class="instructor-chart-description">
                        Active questions compared with inactive questions.
                    </p>

                    <p class="instructor-chart-total">
                        <?= $totalQuestions ?>
                        <small>Total questions in your lessons</small>
                    </p>

                    <div class="instructor-question-chart">

                        <div
                            class="instructor-question-donut"
                            role="img"
                            aria-label="<?= $activeQuestions ?> active and <?= $inactiveQuestions ?> inactive questions"
                        >

                            <div class="instructor-question-donut-hole">
                                <strong><?= $activeQuestionPercent ?>%</strong>
                                <span>Active</span>
                            </div>

                        </div>

                        <div class="instructor-question-legend">

                            <div>

                                <span class="instructor-legend-item">
                                    <span class="instructor-legend-dot instructor-dot-active"></span>
                                    Active
                                </span>

                                <span class="legend-value">
                                    <?= $activeQuestions ?>
                                </span>

                            </div>

                            <div>

                                <span class="instructor-legend-item">
                                    <span class="instructor-legend-dot instructor-dot-inactive"></span>
                                    Inactive
                                </span>

                                <span class="legend-value">
                                    <?= $inactiveQuestions ?>
                                </span>

                            </div>

                        </div>

                    </div>

                    <?php if ($totalQuestions === 0): ?>
                        <p class="instructor-chart-empty">
                            Add assessment questions to see their activity status.
                        </p>
                    <?php endif; ?>

                    <p class="instructor-analytics-note">
                        Only questions attached to lessons you created are included.
                    </p>

                </article>

            </div>

        </section>


        <!-- ======================================================
             MANAGEMENT CARDS
        ======================================================= -->

        <section class="instructor-section">

            <div class="section-heading">

                <span class="section-label">
                    INSTRUCTOR WORKSPACE
                </span>

                <h2>Manage your learning activities</h2>

                <p>
                    Manage lessons, learning materials, instructional videos,
                    and assessment questions from one place.
                </p>

            </div>

            <div class="instructor-actions">

                <a
                    href="<?= e(url('app/instructor/lessons/index.php')) ?>"
                    class="instructor-action-card"
                >
                    <span class="instructor-action-number">01</span>
                    <h3>My Lessons</h3>
                    <p>
                        Create, view, edit and publish vocational lessons
                        assigned to your instructor account.
                    </p>
                    <span class="instructor-action-link">
                        Manage Lessons →
                    </span>
                </a>

                <a
                    href="<?= e(url('app/instructor/content/index.php')) ?>"
                    class="instructor-action-card"
                >
                    <span class="instructor-action-number">02</span>
                    <h3>Lesson Content</h3>
                    <p>
                        Add and arrange text and image materials for the
                        lessons you have created.
                    </p>
                    <span class="instructor-action-link">
                        Manage Content →
                    </span>
                </a>

                <a
                    href="<?= e(url('app/instructor/videos/index.php')) ?>"
                    class="instructor-action-card"
                >
                    <span class="instructor-action-number">03</span>
                    <h3>Instructional Videos</h3>
                    <p>
                        Upload, replace and manage instructional videos
                        associated with your lessons.
                    </p>
                    <span class="instructor-action-link">
                        Manage Videos →
                    </span>
                </a>

                <a
                    href="<?= e(url('app/assessment/index.php')) ?>"
                    class="instructor-action-card"
                >
                    <span class="instructor-action-number">04</span>
                    <h3>Assessments</h3>
                    <p>
                        Create and manage multiple-choice questions,
                        answer options, marks and question order for
                        your own lessons.
                    </p>
                    <span class="instructor-action-link">
                        Manage Assessments →
                    </span>
                </a>

            </div>

        </section>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
