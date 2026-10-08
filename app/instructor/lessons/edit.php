<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$pageTitle = 'Edit Lesson';
$showNavbar = true;

$user = current_user();
$instructorId = (int) $user['id'];

$lessonId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Invalid lesson selected.');
    redirect('app/instructor/lessons/index.php');
}

$lessonStmt = $pdo->prepare(
    "SELECT *
     FROM lessons
     WHERE id = :id
       AND created_by = :created_by
     LIMIT 1"
);
$lessonStmt->execute([
    'id' => $lessonId,
    'created_by' => $instructorId,
]);

$lesson = $lessonStmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Lesson not found or you do not have permission to edit it.');
}

$skillStmt = $pdo->query(
    "SELECT id, name
     FROM skills
     WHERE is_active = 1
        OR id = " . (int) $lesson['skill_id'] . "
     ORDER BY name ASC"
);
$skills = $skillStmt->fetchAll();

$errors = [];

if (is_post()) {

    verify_csrf();

    $skillId = filter_var(
        $_POST['skill_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $lessonOrder = filter_var(
        $_POST['lesson_order'] ?? '',
        FILTER_VALIDATE_INT
    );

    if (!$skillId || $skillId < 1) {
        $errors[] = 'Please select a vocational skill.';
    }

    if ($title === '') {
        $errors[] = 'Lesson title is required.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Lesson title must not exceed 200 characters.';
    }

    if (mb_strlen($description) > 5000) {
        $errors[] = 'Lesson description must not exceed 5,000 characters.';
    }

    if (!$lessonOrder || $lessonOrder < 1) {
        $errors[] = 'Lesson order must be a positive number.';
    }

    if (!$errors) {

        $skillCheck = $pdo->prepare(
            "SELECT id
             FROM skills
             WHERE id = :id
               AND is_active = 1
             LIMIT 1"
        );
        $skillCheck->execute(['id' => $skillId]);

        if (!$skillCheck->fetch()) {
            $errors[] = 'The selected vocational skill is not available.';
        }
    }

    if (!$errors) {

        $orderCheck = $pdo->prepare(
            "SELECT id
             FROM lessons
             WHERE skill_id = :skill_id
               AND lesson_order = :lesson_order
               AND id <> :id
             LIMIT 1"
        );

        $orderCheck->execute([
            'skill_id' => $skillId,
            'lesson_order' => $lessonOrder,
            'id' => $lessonId,
        ]);

        if ($orderCheck->fetch()) {
            $errors[] = 'That lesson order is already used for the selected skill.';
        }
    }

    if (!$errors) {

        $update = $pdo->prepare(
            "UPDATE lessons
             SET
                skill_id = :skill_id,
                title = :title,
                description = :description,
                lesson_order = :lesson_order
             WHERE id = :id
               AND created_by = :created_by"
        );

        $update->execute([
            'skill_id' => $skillId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'lesson_order' => $lessonOrder,
            'id' => $lessonId,
            'created_by' => $instructorId,
        ]);

        flash('success', 'Lesson updated successfully.');

        redirect('app/instructor/lessons/index.php');
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="instructor-page">
    <div class="container">

        <section class="module-header instructor-module-header">
            <div>
                <span class="section-label">MY LESSONS</span>
                <h1>Edit Lesson</h1>
                <p>
                    Update the lesson information before preparing its learning content.
                </p>
            </div>

            <a
                href="<?= e(url('app/instructor/lessons/index.php')) ?>"
                class="btn btn-secondary"
            >
                Back to Lessons
            </a>
        </section>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="module-card">

            <form method="post" class="instructor-form">

                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="skill_id">
                        Vocational Skill <span class="required">*</span>
                    </label>

                    <select
                        id="skill_id"
                        name="skill_id"
                        required
                    >
                        <?php foreach ($skills as $skill): ?>
                            <option
                                value="<?= (int) $skill['id'] ?>"
                                <?= ((int) $lesson['skill_id'] === (int) $skill['id'])
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($skill['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">
                        Lesson Title <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="200"
                        required
                        value="<?= e(old('title', $lesson['title'])) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="description">
                        Lesson Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        maxlength="5000"
                    ><?= e(old('description', $lesson['description'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="lesson_order">
                        Lesson Order <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        id="lesson_order"
                        name="lesson_order"
                        min="1"
                        required
                        value="<?= e(old('lesson_order', (string) $lesson['lesson_order'])) ?>"
                    >

                    <p class="field-help">
                        The order is unique within the selected skill.
                    </p>
                </div>

                <div class="instructor-form-note">
                    <strong>Publication status:</strong>
                    <?= (int) $lesson['is_published'] === 1
                        ? 'This lesson is currently published.'
                        : 'This lesson is currently a draft.' ?>
                </div>

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Changes
                    </button>

                    <a
                        href="<?= e(url('app/instructor/lessons/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </section>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
