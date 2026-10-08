<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'instructor');

$user = current_user();
$userId = (int) $user['id'];
$role = (string) $user['role'];

$lessonId = filter_var(
    $_GET['lesson_id'] ?? $_POST['lesson_id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Please select a lesson first.');
    redirect('app/assessment/index.php');
}

$lessonSql = "
    SELECT
        l.id,
        l.title,
        l.created_by,
        s.name AS skill_name
    FROM lessons l
    INNER JOIN skills s ON s.id = l.skill_id
    WHERE l.id = :lesson_id
";

$lessonParams = ['lesson_id' => (int) $lessonId];

if ($role === 'instructor') {
    $lessonSql .= ' AND l.created_by = :user_id';
    $lessonParams['user_id'] = $userId;
}

$lessonSql .= ' LIMIT 1';

$lessonStmt = $pdo->prepare($lessonSql);
$lessonStmt->execute($lessonParams);
$lesson = $lessonStmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Lesson not found or you do not have permission to manage it.');
}

$errors = [];

$questionText = trim((string) ($_POST['question_text'] ?? ''));
$marks = filter_var(
    $_POST['marks'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($marks === false || $marks < 1) {
    $marks = 1;
}

$questionOrder = filter_var(
    $_POST['question_order'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($questionOrder === false || $questionOrder < 1) {
    $questionOrder = 1;
}

$options = $_POST['options'] ?? [];
$correctOption = filter_var(
    $_POST['correct_option'] ?? '',
    FILTER_VALIDATE_INT
);

if (!is_array($options)) {
    $options = [];
}

$options = array_values(
    array_map(
        static fn ($value): string => trim((string) $value),
        $options
    )
);

if (is_post()) {
    verify_csrf();

    if ($questionText === '') {
        $errors[] = 'Question text is required.';
    }

    if (count($options) < 2) {
        $errors[] = 'At least two answer options are required.';
    }

    foreach ($options as $index => $option) {
        if ($option === '') {
            $errors[] = 'Every answer option must contain text.';
            break;
        }

        if (mb_strlen($option) > 500) {
            $errors[] = 'Each answer option must not exceed 500 characters.';
            break;
        }
    }

    if (
        $correctOption === false ||
        $correctOption === null ||
        !array_key_exists((int) $correctOption, $options)
    ) {
        $errors[] = 'Please select the correct answer.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $maxStmt = $pdo->prepare(
                "SELECT COALESCE(MAX(question_order), 0)
                 FROM questions
                 WHERE lesson_id = :lesson_id"
            );
            $maxStmt->execute([
                'lesson_id' => (int) $lessonId,
            ]);

            $maxOrder = (int) $maxStmt->fetchColumn();

            $questionOrder = max(
                1,
                min((int) $questionOrder, $maxOrder + 1)
            );

            $shiftStmt = $pdo->prepare(
                "UPDATE questions
                 SET question_order = question_order + 1
                 WHERE lesson_id = :lesson_id
                   AND question_order >= :question_order"
            );

            $shiftStmt->execute([
                'lesson_id' => (int) $lessonId,
                'question_order' => (int) $questionOrder,
            ]);

            $questionStmt = $pdo->prepare(
                "INSERT INTO questions
                    (
                        lesson_id,
                        question_text,
                        question_order,
                        marks,
                        is_active
                    )
                 VALUES
                    (
                        :lesson_id,
                        :question_text,
                        :question_order,
                        :marks,
                        1
                    )"
            );

            $questionStmt->execute([
                'lesson_id' => (int) $lessonId,
                'question_text' => $questionText,
                'question_order' => (int) $questionOrder,
                'marks' => (int) $marks,
            ]);

            $questionId = (int) $pdo->lastInsertId();

            $optionStmt = $pdo->prepare(
                "INSERT INTO question_options
                    (
                        question_id,
                        option_text,
                        option_order,
                        is_correct
                    )
                 VALUES
                    (
                        :question_id,
                        :option_text,
                        :option_order,
                        :is_correct
                    )"
            );

            foreach ($options as $index => $optionText) {
                $optionStmt->execute([
                    'question_id' => $questionId,
                    'option_text' => $optionText,
                    'option_order' => $index + 1,
                    'is_correct' => (
                        (int) $correctOption === $index
                    ) ? 1 : 0,
                ]);
            }

            $pdo->commit();

            flash(
                'success',
                'Assessment question added successfully.'
            );

            redirect(
                'app/assessment/index.php?lesson_id=' .
                (int) $lessonId
            );

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'The assessment question could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Add Assessment Question';
$showNavbar = true;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="assessment-management">
    <div class="container">

        <section class="module-heading">
            <div>
                <span class="section-label">ASSESSMENT</span>
                <h1>Add Question</h1>
                <p>
                    <?= e($lesson['title']) ?>
                    —
                    <?= e($lesson['skill_name']) ?>
                </p>
            </div>
        </section>

        <?php if ($errors): ?>
            <div class="form-errors">
                <strong>Please correct the following:</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-card">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="lesson_id"
                value="<?= (int) $lessonId ?>"
            >

            <div class="form-group">
                <label for="question_text">
                    Question
                    <span class="required">*</span>
                </label>

                <textarea
                    name="question_text"
                    id="question_text"
                    rows="5"
                    required
                ><?= e($questionText) ?></textarea>
            </div>

            <div class="form-group">
                <label for="marks">
                    Marks
                    <span class="required">*</span>
                </label>

                <input
                    type="number"
                    name="marks"
                    id="marks"
                    min="1"
                    value="<?= (int) $marks ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="question_order">
                    Question Order
                </label>

                <input
                    type="number"
                    name="question_order"
                    id="question_order"
                    min="1"
                    value="<?= (int) $questionOrder ?>"
                >
            </div>

            <div class="form-group">
                <label>
                    Answer Options
                    <span class="required">*</span>
                </label>

                <div id="optionsContainer">

                    <?php
                    $displayOptions = $options;

                    if (count($displayOptions) < 2) {
                        $displayOptions = ['', ''];
                    }
                    ?>

                    <?php foreach ($displayOptions as $index => $option): ?>

                        <div class="assessment-option-row">

                            <input
                                type="radio"
                                name="correct_option"
                                value="<?= (int) $index ?>"
                                <?= (
                                    (int) $correctOption === $index
                                ) ? 'checked' : '' ?>
                            >

                            <input
                                type="text"
                                name="options[]"
                                value="<?= e($option) ?>"
                                maxlength="500"
                                placeholder="Answer option <?= $index + 1 ?>"
                                required
                            >

                        </div>

                    <?php endforeach; ?>

                </div>

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="addOption"
                >
                    Add Option
                </button>

                <small>
                    Select the radio button beside the correct answer.
                </small>
            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Question
                </button>

                <a
                    href="<?= e(
                        url(
                            'app/assessment/index.php?lesson_id=' .
                            (int) $lessonId
                        )
                    ) ?>"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>
</main>

<script>
(function () {
    const container = document.getElementById('optionsContainer');
    const addButton = document.getElementById('addOption');

    addButton.addEventListener('click', function () {
        const index = container.querySelectorAll(
            '.assessment-option-row'
        ).length;

        const row = document.createElement('div');
        row.className = 'assessment-option-row';

        row.innerHTML =
            '<input type="radio" name="correct_option" value="' +
            index +
            '">' +
            '<input type="text" name="options[]" maxlength="500" ' +
            'placeholder="Answer option ' +
            (index + 1) +
            '" required>';

        container.appendChild(row);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
