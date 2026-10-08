<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$pageTitle = 'Create Lesson';
$showNavbar = true;

$user = current_user();
$instructorId = (int) $user['id'];

$skillStmt = $pdo->query(
    "SELECT id, name
     FROM skills
     WHERE is_active = 1
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

    $description = trim(
        (string) ($_POST['description'] ?? '')
    );

    $requestedOrder = filter_var(
        $_POST['lesson_order'] ?? '',
        FILTER_VALIDATE_INT
    );


    /*
     * ============================================================
     * VALIDATION
     * ============================================================
     */

    if (!$skillId || $skillId < 1) {

        $errors[] = 'Please select a vocational skill.';

    }


    if ($title === '') {

        $errors[] = 'Lesson title is required.';

    } elseif (mb_strlen($title) > 200) {

        $errors[] = 'Lesson title must not exceed 200 characters.';

    }


    if (mb_strlen($description) > 5000) {

        $errors[] =
            'Lesson description must not exceed 5,000 characters.';

    }


    if (!$requestedOrder || $requestedOrder < 1) {

        $requestedOrder = 1;

    }


    /*
     * ============================================================
     * VERIFY SELECTED SKILL
     * ============================================================
     */

    if (!$errors) {

        $skillCheck = $pdo->prepare(
            "SELECT id
             FROM skills
             WHERE id = :id
               AND is_active = 1
             LIMIT 1"
        );

        $skillCheck->execute([
            'id' => $skillId
        ]);

        if (!$skillCheck->fetch()) {

            $errors[] =
                'The selected vocational skill is not available.';

        }

    }


    /*
     * ============================================================
     * DETERMINE LESSON ORDER
     *
     * The instructor may enter any order.
     *
     * If that order is already occupied for the selected skill,
     * automatically continue after the highest existing order.
     *
     * Example:
     *
     * Existing: 1, 2, 3, 4
     * Requested: 1
     * Result:    5
     * ============================================================
     */

    $finalLessonOrder = $requestedOrder;
    $orderAdjusted = false;
    $highestExistingOrder = 0;


    if (!$errors) {

        /*
         * Find the highest lesson order already used
         * for this particular skill.
         */
        $maxOrderStmt = $pdo->prepare(
            "SELECT COALESCE(MAX(lesson_order), 0)
             FROM lessons
             WHERE skill_id = :skill_id"
        );

        $maxOrderStmt->execute([
            'skill_id' => $skillId
        ]);

        $highestExistingOrder =
            (int) $maxOrderStmt->fetchColumn();


        /*
         * Check whether the requested order is already occupied.
         */
        $orderCheck = $pdo->prepare(
            "SELECT id
             FROM lessons
             WHERE skill_id = :skill_id
               AND lesson_order = :lesson_order
             LIMIT 1"
        );

        $orderCheck->execute([
            'skill_id' => $skillId,
            'lesson_order' => $requestedOrder
        ]);


        if ($orderCheck->fetch()) {

            /*
             * Requested position is already occupied.
             *
             * Continue from the end of the existing sequence.
             */
            $finalLessonOrder = $highestExistingOrder + 1;

            $orderAdjusted = true;

        }

    }


    /*
     * ============================================================
     * SAFETY CHECK
     *
     * Ensure the automatically selected order is also available.
     * ============================================================
     */

    if (!$errors) {

        while (true) {

            $finalOrderCheck = $pdo->prepare(
                "SELECT id
                 FROM lessons
                 WHERE skill_id = :skill_id
                   AND lesson_order = :lesson_order
                 LIMIT 1"
            );

            $finalOrderCheck->execute([
                'skill_id' => $skillId,
                'lesson_order' => $finalLessonOrder
            ]);

            if (!$finalOrderCheck->fetch()) {
                break;
            }

            $finalLessonOrder++;
            $orderAdjusted = true;
        }

    }


    /*
     * ============================================================
     * CREATE LESSON
     * ============================================================
     */

    if (!$errors) {

        $insert = $pdo->prepare(
            "INSERT INTO lessons
                (
                    skill_id,
                    title,
                    description,
                    lesson_order,
                    is_published,
                    created_by
                )
             VALUES
                (
                    :skill_id,
                    :title,
                    :description,
                    :lesson_order,
                    0,
                    :created_by
                )"
        );

        $insert->execute([
            'skill_id' => $skillId,

            'title' => $title,

            'description' =>
                $description !== ''
                    ? $description
                    : null,

            'lesson_order' => $finalLessonOrder,

            'created_by' => $instructorId
        ]);


        /*
         * ========================================================
         * SUCCESS MESSAGE
         * ========================================================
         */

        if ($orderAdjusted) {

            flash(
                'success',
                'Lesson created successfully. ' .
                'The requested lesson order ' .
                $requestedOrder .
                ' was already in use, so the system automatically ' .
                'assigned lesson order ' .
                $finalLessonOrder .
                '.'
            );

        } else {

            flash(
                'success',
                'Lesson created successfully with lesson order ' .
                $finalLessonOrder .
                '.'
            );

        }


        redirect(
            'app/instructor/lessons/index.php'
        );

    }
}


require_once __DIR__ . '/../../includes/header.php';

?>

<main class="instructor-page">

    <div class="container">


        <section class="module-header instructor-module-header">

            <div>

                <span class="section-label">
                    MY LESSONS
                </span>

                <h1>
                    Create Lesson
                </h1>

                <p>
                    Create a vocational lesson. Text, images and the
                    instructional video will be managed as lesson content.
                </p>

            </div>


            <a
                href="<?= e(
                    url('app/instructor/lessons/index.php')
                ) ?>"
                class="btn btn-secondary"
            >
                Back to Lessons
            </a>

        </section>


        <?php if ($errors): ?>

            <div class="alert alert-error">

                <ul class="error-list">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <section class="module-card">


            <form
                method="post"
                class="instructor-form"
            >

                <?= csrf_field() ?>


                <!-- VOCATIONAL SKILL -->

                <div class="form-group">

                    <label for="skill_id">

                        Vocational Skill
                        <span class="required">*</span>

                    </label>


                    <select
                        id="skill_id"
                        name="skill_id"
                        required
                    >

                        <option value="">
                            Select a skill
                        </option>


                        <?php foreach ($skills as $skill): ?>

                            <option
                                value="<?= (int) $skill['id'] ?>"
                                <?= (
                                    (string) old('skill_id')
                                    ===
                                    (string) $skill['id']
                                )
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= e($skill['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <p class="field-help">

                        Instructors select from the skills
                        configured by the administrator.

                    </p>

                </div>


                <!-- LESSON TITLE -->

                <div class="form-group">

                    <label for="title">

                        Lesson Title
                        <span class="required">*</span>

                    </label>


                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="200"
                        required
                        value="<?= e(old('title')) ?>"
                        placeholder="e.g. Introduction to Cake Decoration"
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label for="description">

                        Lesson Description

                    </label>


                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        maxlength="5000"
                        placeholder="Describe what learners will learn in this lesson..."
                    ><?= e(
                        old('description')
                    ) ?></textarea>

                </div>


                <!-- LESSON ORDER -->

                <div class="form-group">

                    <label for="lesson_order">

                        Lesson Order
                        <span class="required">*</span>

                    </label>


                    <input
                        type="number"
                        id="lesson_order"
                        name="lesson_order"
                        min="1"
                        required
                        value="<?= e(
                            old(
                                'lesson_order',
                                '1'
                            )
                        ) ?>"
                    >


                    <p class="field-help">

                        Enter the preferred order.

                        If that order is already occupied for the
                        selected skill, the system will automatically
                        continue from the last existing lesson order.

                    </p>

                </div>


                <!-- INFORMATION -->

                <div class="instructor-form-note">

                    <strong>
                        Lesson order:
                    </strong>

                    The requested order does not have to be manually
                    checked. If it is already occupied, the system
                    automatically assigns the next available order
                    after the existing lessons for that skill.

                </div>


                <!-- FORM ACTIONS -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Create Lesson
                    </button>


                    <a
                        href="<?= e(
                            url('app/instructor/lessons/index.php')
                        ) ?>"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                </div>


            </form>

        </section>

    </div>

</main>


<?php

require_once __DIR__ . '/../../includes/footer.php';

?>