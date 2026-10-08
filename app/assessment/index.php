<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'instructor');

$user = current_user();
$userId = (int) $user['id'];
$role = (string) $user['role'];

$pageTitle = 'Assessments';
$showNavbar = true;

/*
|--------------------------------------------------------------------------
| Selected lesson and search filters
|--------------------------------------------------------------------------
*/
$lessonId = filter_var(
    $_GET['lesson_id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($lessonId === false || $lessonId < 1) {
    $lessonId = null;
}

$search = trim((string) ($_GET['search'] ?? ''));

/*
|--------------------------------------------------------------------------
| Load lessons available to this user
|--------------------------------------------------------------------------
*/
$lessonWhere = [];
$lessonParams = [];

if ($role === 'instructor') {
    $lessonWhere[] = 'l.created_by = :instructor_id';
    $lessonParams['instructor_id'] = $userId;
}

$lessonSql = "
    SELECT
        l.id,
        l.title,
        l.lesson_order,
        s.name AS skill_name
    FROM lessons l
    INNER JOIN skills s
        ON s.id = l.skill_id
";

if ($lessonWhere) {
    $lessonSql .= ' WHERE ' . implode(' AND ', $lessonWhere);
}

$lessonSql .= "
    ORDER BY
        s.name ASC,
        l.lesson_order ASC,
        l.title ASC
";

$lessonStmt = $pdo->prepare($lessonSql);
$lessonStmt->execute($lessonParams);

$lessons = $lessonStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| If a lesson was selected, verify that it belongs to the user's
| permitted lesson scope.
|--------------------------------------------------------------------------
*/
$selectedLesson = null;

if ($lessonId !== null) {
    foreach ($lessons as $lesson) {
        if ((int) $lesson['id'] === $lessonId) {
            $selectedLesson = $lesson;
            break;
        }
    }

    if ($selectedLesson === null) {
        $lessonId = null;
        flash(
            'error',
            'The selected lesson could not be found or you do not have permission to manage it.'
        );

        redirect('app/assessment/index.php');
    }
}

/*
|--------------------------------------------------------------------------
| Load questions
|--------------------------------------------------------------------------
*/
$where = [];
$params = [];

if ($role === 'instructor') {
    $where[] = 'l.created_by = :instructor_id';
    $params['instructor_id'] = $userId;
}

if ($lessonId !== null) {
    $where[] = 'q.lesson_id = :lesson_id';
    $params['lesson_id'] = $lessonId;
}

if ($search !== '') {
    $where[] = 'q.question_text LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$sql = "
    SELECT
        q.id,
        q.lesson_id,
        q.question_text,
        q.question_order,
        q.marks,
        q.is_active,
        l.title AS lesson_title,
        s.name AS skill_name,

        (
            SELECT COUNT(*)
            FROM question_options qo
            WHERE qo.question_id = q.id
        ) AS option_count

    FROM questions q

    INNER JOIN lessons l
        ON l.id = q.lesson_id

    INNER JOIN skills s
        ON s.id = l.skill_id
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        s.name ASC,
        l.lesson_order ASC,
        q.question_order ASC,
        q.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$questions = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Build Add Question URL
|--------------------------------------------------------------------------
*/
$addQuestionUrl = null;

if ($selectedLesson !== null) {
    $addQuestionUrl = url(
        'app/assessment/create.php?lesson_id=' .
        (int) $selectedLesson['id']
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="assessment-management">

    <div class="container">

        <!-- ======================================================
             PAGE HEADER
        ======================================================= -->

        <section class="module-heading">

            <div>

                <span class="section-label">
                    ASSESSMENT MANAGEMENT
                </span>

                <h1>
                    Lesson Assessments
                </h1>

                <p>
                    Create and manage assessment questions for vocational lessons.
                </p>

            </div>

            <?php if ($addQuestionUrl !== null): ?>

                <a
                    href="<?= e($addQuestionUrl) ?>"
                    class="btn btn-primary"
                >
                    Add Question
                </a>

            <?php else: ?>

                <span
                    class="btn btn-secondary"
                    aria-disabled="true"
                    title="Select a lesson first"
                >
                    Select a Lesson First
                </span>

            <?php endif; ?>

        </section>


        <!-- ======================================================
             FILTER
        ======================================================= -->

        <section class="module-card">

            <form
                method="get"
                action="<?= e(url('app/assessment/index.php')) ?>"
                class="instructor-filter-form"
            >

                <div class="form-group">

                    <label for="lesson_id">
                        Lesson
                    </label>

                    <select
                        name="lesson_id"
                        id="lesson_id"
                    >

                        <option value="">
                            All lessons
                        </option>

                        <?php foreach ($lessons as $lesson): ?>

                            <option
                                value="<?= (int) $lesson['id'] ?>"
                                <?= (
                                    $lessonId !== null &&
                                    (int) $lesson['id'] === $lessonId
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($lesson['title']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="search">
                        Search Question
                    </label>

                    <input
                        type="search"
                        name="search"
                        id="search"
                        value="<?= e($search) ?>"
                        placeholder="Search assessment questions..."
                    >

                </div>


                <div class="filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Filter
                    </button>

                    <a
                        href="<?= e(url('app/assessment/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <!-- ======================================================
             SELECTED LESSON INFORMATION
        ======================================================= -->

        <?php if ($selectedLesson !== null): ?>

            <section class="module-card">

                <div class="table-header">

                    <div>

                        <span class="section-label">
                            SELECTED LESSON
                        </span>

                        <h2>
                            <?= e($selectedLesson['title']) ?>
                        </h2>

                        <p>
                            Skill:
                            <strong>
                                <?= e($selectedLesson['skill_name']) ?>
                            </strong>
                        </p>

                    </div>

                    <a
                        href="<?= e($addQuestionUrl) ?>"
                        class="btn btn-primary"
                    >
                        Add Question
                    </a>

                </div>

            </section>

        <?php endif; ?>


        <!-- ======================================================
             QUESTION BANK
        ======================================================= -->

        <section class="module-card">

            <div class="table-header">

                <div>

                    <span class="section-label">
                        QUESTION BANK
                    </span>

                    <h2>
                        <?= count($questions) ?>
                        question<?= count($questions) === 1 ? '' : 's' ?>
                    </h2>

                </div>

            </div>


            <?php if (!$questions): ?>

                <div class="empty-state">

                    <?php if ($selectedLesson !== null): ?>

                        <h3>
                            No questions for this lesson yet
                        </h3>

                        <p>
                            Add the first assessment question for
                            <strong>
                                <?= e($selectedLesson['title']) ?>
                            </strong>.
                        </p>

                        <a
                            href="<?= e($addQuestionUrl) ?>"
                            class="btn btn-primary"
                        >
                            Add First Question
                        </a>

                    <?php else: ?>

                        <h3>
                            No assessment questions found
                        </h3>

                        <p>
                            Select a lesson above to manage its assessment questions.
                        </p>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="table-wrap">

                    <table class="data-table">

                        <thead>

                            <tr>
                                <th>Lesson</th>
                                <th>Question</th>
                                <th>Options</th>
                                <th>Marks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($questions as $question): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e($question['lesson_title']) ?>
                                        </strong>

                                        <small class="table-description">
                                            <?= e($question['skill_name']) ?>
                                        </small>

                                    </td>


                                    <td>

                                        <strong>
                                            <?= (int) $question['question_order'] ?>.
                                        </strong>

                                        <?= e($question['question_text']) ?>

                                    </td>


                                    <td>
                                        <?= (int) $question['option_count'] ?>
                                    </td>


                                    <td>
                                        <?= (int) $question['marks'] ?>
                                    </td>


                                    <td>

                                        <?php if ((int) $question['is_active'] === 1): ?>

                                            <span class="status-badge status-active">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-inactive">
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td class="actions-cell">

                                        <a
                                            href="<?= e(
                                                url(
                                                    'app/assessment/edit.php?id=' .
                                                    (int) $question['id']
                                                )
                                            ) ?>"
                                            class="action-link"
                                        >
                                            Edit
                                        </a>


                                        <form
                                            method="post"
                                            action="<?= e(
                                                url('app/assessment/toggle.php')
                                            ) ?>"
                                            class="inline-form"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $question['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-button"
                                            >
                                                <?= (
                                                    (int) $question['is_active'] === 1
                                                )
                                                    ? 'Deactivate'
                                                    : 'Activate' ?>
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
