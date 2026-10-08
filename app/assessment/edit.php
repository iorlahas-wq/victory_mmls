<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'instructor');

$user = current_user();
$userId = (int) $user['id'];
$role = (string) $user['role'];

$questionId = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$questionId || $questionId < 1) {
    flash('error', 'Invalid assessment question.');
    redirect('app/assessment/index.php');
}

$questionSql = "
    SELECT
        q.id,
        q.lesson_id,
        q.question_text,
        q.question_order,
        q.marks,
        q.is_active,
        l.title AS lesson_title,
        s.name AS skill_name
    FROM questions q
    INNER JOIN lessons l ON l.id = q.lesson_id
    INNER JOIN skills s ON s.id = l.skill_id
    WHERE q.id = :question_id
";

$params = ['question_id' => (int) $questionId];

if ($role === 'instructor') {
    $questionSql .= ' AND l.created_by = :user_id';
    $params['user_id'] = $userId;
}

$questionSql .= ' LIMIT 1';

$questionStmt = $pdo->prepare($questionSql);
$questionStmt->execute($params);
$question = $questionStmt->fetch();

if (!$question) {
    http_response_code(404);
    exit('Assessment question not found or access denied.');
}

$optionsStmt = $pdo->prepare(
    "SELECT id, option_text, option_order, is_correct
     FROM question_options
     WHERE question_id = :question_id
     ORDER BY option_order ASC"
);

$optionsStmt->execute([
    'question_id' => (int) $questionId,
]);

$existingOptions = $optionsStmt->fetchAll();

$errors = [];

$questionText = (string) $question['question_text'];
$marks = (int) $question['marks'];
$questionOrder = (int) $question['question_order'];

$options = array_map(
    static fn (array $option): string => (string) $option['option_text'],
    $existingOptions
);

$correctOption = 0;

foreach ($existingOptions as $index => $option) {
    if ((int) $option['is_correct'] === 1) {
        $correctOption = $index;
        break;
    }
}

if (is_post()) {
    verify_csrf();

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

    $postedOptions = $_POST['options'] ?? [];

    $correctOption = filter_var(
        $_POST['correct_option'] ?? '',
        FILTER_VALIDATE_INT
    );

    $options = is_array($postedOptions)
        ? array_values(
            array_map(
                static fn ($value): string => trim((string) $value),
                $postedOptions
            )
        )
        : [];

    if ($questionText === '') {
        $errors[] = 'Question text is required.';
    }

    if (count($options) < 2) {
        $errors[] = 'At least two answer options are required.';
    }

    foreach ($options as $option) {
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

            if (
                (int) $questionOrder !==
                (int) $question['question_order']
            ) {
                $oldOrder = (int) $question['question_order'];

                if ($questionOrder < $oldOrder) {
                    $shiftStmt = $pdo->prepare(
                        "UPDATE questions
                         SET question_order = question_order + 1
                         WHERE lesson_id = :lesson_id
                           AND id <> :question_id
                           AND question_order >= :new_order
                           AND question_order < :old_order"
                    );

                    $shiftStmt->execute([
                        'lesson_id' => (int) $question['lesson_id'],
                        'question_id' => (int) $questionId,
                        'new_order' => (int) $questionOrder,
                        'old_order' => $oldOrder,
                    ]);
                } else {
                    $shiftStmt = $pdo->prepare(
                        "UPDATE questions
                         SET question_order = question_order - 1
                         WHERE lesson_id = :lesson_id
                           AND id <> :question_id
                           AND question_order > :old_order
                           AND question_order <= :new_order"
                    );

                    $shiftStmt->execute([
                        'lesson_id' => (int) $question['lesson_id'],
                        'question_id' => (int) $questionId,
                        'old_order' => $oldOrder,
                        'new_order' => (int) $questionOrder,
                    ]);
                }
            }

            $updateStmt = $pdo->prepare(
                "UPDATE questions
                 SET question_text = :question_text,
                     question_order = :question_order,
                     marks = :marks
                 WHERE id = :question_id"
            );

            $updateStmt->execute([
                'question_text' => $questionText,
                'question_order' => (int) $questionOrder,
                'marks' => (int) $marks,
                'question_id' => (int) $questionId,
            ]);

            $deleteOptions = $pdo->prepare(
                "DELETE FROM question_options
                 WHERE question_id = :question_id"
            );

            $deleteOptions->execute([
                'question_id' => (int) $questionId,
            ]);

            $insertOption = $pdo->prepare(
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
                $insertOption->execute([
                    'question_id' => (int) $questionId,
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
                'Assessment question updated successfully.'
            );

            redirect(
                'app/assessment/index.php?lesson_id=' .
                (int) $question['lesson_id']
            );

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'The assessment question could not be updated. Please try again.';
        }
    }
}

$pageTitle = 'Edit Assessment Question';
$showNavbar = true;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="assessment-management">
    <div class="container">

        <section class="module-heading">
            <div>
                <span class="section-label">ASSESSMENT</span>
                <h1>Edit Question</h1>
                <p>
                    <?= e($question['lesson_title']) ?>
                    —
                    <?= e($question['skill_name']) ?>
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
                name="id"
                value="<?= (int) $questionId ?>"
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
                <label for="marks">Marks</label>

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
                <label for="question_order">Question Order</label>

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

                    <?php foreach ($options as $index => $option): ?>

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
                    Save Changes
                </button>

                <a
                    href="<?= e(
                        url(
                            'app/assessment/index.php?lesson_id=' .
                            (int) $question['lesson_id']
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
