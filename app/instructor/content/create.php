<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$user = current_user();
$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Resolve lesson
|--------------------------------------------------------------------------
| The lesson must belong to the currently logged-in instructor.
*/
$lessonId = filter_input(
    INPUT_GET,
    'lesson_id',
    FILTER_VALIDATE_INT
);

if (!$lessonId && isset($_POST['lesson_id'])) {
    $lessonId = filter_var(
        $_POST['lesson_id'],
        FILTER_VALIDATE_INT
    );
}

if (!$lessonId || $lessonId < 1) {
    flash('error', 'Please select a lesson first.');
    redirect('app/instructor/content/index.php');
}

/*
|--------------------------------------------------------------------------
| Load the instructor's lesson
|--------------------------------------------------------------------------
*/
$lessonStmt = $pdo->prepare(
    "SELECT
        l.id,
        l.title,
        l.is_published,
        s.name AS skill_name
     FROM lessons l
     INNER JOIN skills s
        ON s.id = l.skill_id
     WHERE l.id = :lesson_id
       AND l.created_by = :instructor_id
     LIMIT 1"
);

$lessonStmt->execute([
    'lesson_id'    => (int) $lessonId,
    'instructor_id' => $userId,
]);

$lesson = $lessonStmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Lesson not found or you do not have permission to manage it.');
}

/*
|--------------------------------------------------------------------------
| Form defaults
|--------------------------------------------------------------------------
*/
$errors = [];

$contentType = (string) ($_POST['content_type'] ?? 'text');
$title = trim((string) ($_POST['title'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));

$contentOrder = filter_var(
    $_POST['content_order'] ?? 1,
    FILTER_VALIDATE_INT
);

if (
    $contentOrder === false ||
    $contentOrder === null ||
    $contentOrder < 1
) {
    $contentOrder = 1;
}

/*
|--------------------------------------------------------------------------
| Handle submission
|--------------------------------------------------------------------------
*/
if (is_post()) {
    verify_csrf();

    /*
    |----------------------------------------------------------------------
    | Validate content type
    |----------------------------------------------------------------------
    */
    if (!in_array($contentType, ['text', 'image'], true)) {
        $errors[] = 'Please select either Text or Image content.';
    }

    /*
    |----------------------------------------------------------------------
    | Validate title
    |----------------------------------------------------------------------
    */
    if ($title === '') {
        $errors[] = 'Content title is required.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Content title must not exceed 200 characters.';
    }

    /*
    |----------------------------------------------------------------------
    | Validate text content
    |----------------------------------------------------------------------
    */
    if ($contentType === 'text' && $content === '') {
        $errors[] = 'Please enter the text content.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate image upload
    |--------------------------------------------------------------------------
    */
    $uploadedFilePath = null;
    $relativeFilePath = null;

    if ($contentType === 'image') {
        if (
            !isset($_FILES['content_file']) ||
            !is_array($_FILES['content_file'])
        ) {
            $errors[] = 'Please select an image.';
        } else {
            $file = $_FILES['content_file'];

            if (
                !isset($file['error']) ||
                $file['error'] !== UPLOAD_ERR_OK
            ) {
                $errors[] = 'Please select an image.';
            } else {
                $fileSize = (int) ($file['size'] ?? 0);
                $tmpName = (string) ($file['tmp_name'] ?? '');

                if ($fileSize <= 0) {
                    $errors[] = 'The selected image is empty.';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $errors[] = 'Image must not exceed 5 MB.';
                }

                if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                    $errors[] = 'The selected image could not be verified.';
                }

                if (!$errors) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpName);

                    $allowedMimeTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];

                    if (!isset($allowedMimeTypes[$mime])) {
                        $errors[] = 'Only JPG, PNG and WebP images are allowed.';
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save content
    |--------------------------------------------------------------------------
    */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /*
            |------------------------------------------------------------------
            | Determine the next safe position.
            |------------------------------------------------------------------
            | Videos are managed separately, so ordering here applies only
            | to text and image learning content.
            */
            $maxStmt = $pdo->prepare(
                "SELECT COALESCE(MAX(content_order), 0)
                 FROM lesson_contents
                 WHERE lesson_id = :lesson_id
                   AND content_type IN ('text', 'image')"
            );

            $maxStmt->execute([
                'lesson_id' => (int) $lessonId,
            ]);

            $maxOrder = (int) $maxStmt->fetchColumn();

            $contentOrder = max(
                1,
                min((int) $contentOrder, $maxOrder + 1)
            );

            /*
            |------------------------------------------------------------------
            | Prepare image destination only after all validation has passed.
            |------------------------------------------------------------------
            */
            if ($contentType === 'image') {
                $tmpName = (string) $_FILES['content_file']['tmp_name'];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpName);

                $extensionMap = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                $extension = $extensionMap[$mime];

                $uploadDir = UPLOAD_PATH . '/images';

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    throw new RuntimeException(
                        'The image upload directory could not be created.'
                    );
                }

                $filename =
                    'lesson_' .
                    (int) $lessonId .
                    '_' .
                    bin2hex(random_bytes(8)) .
                    '.' .
                    $extension;

                $uploadedFilePath = $uploadDir . '/' . $filename;
                $relativeFilePath = 'uploads/images/' . $filename;

                if (
                    !move_uploaded_file(
                        $tmpName,
                        $uploadedFilePath
                    )
                ) {
                    throw new RuntimeException(
                        'The image could not be uploaded.'
                    );
                }
            }

            /*
            |------------------------------------------------------------------
            | Shift existing content down.
            |------------------------------------------------------------------
            */
            $shiftStmt = $pdo->prepare(
                "UPDATE lesson_contents
                 SET content_order = content_order + 1
                 WHERE lesson_id = :lesson_id
                   AND content_type IN ('text', 'image')
                   AND content_order >= :content_order"
            );

            $shiftStmt->execute([
                'lesson_id'    => (int) $lessonId,
                'content_order' => (int) $contentOrder,
            ]);

            /*
            |------------------------------------------------------------------
            | Insert new learning content.
            |------------------------------------------------------------------
            | IMPORTANT:
            | lesson_contents has NO created_by column.
            | Instructor ownership is established through lessons.created_by.
            */
            $insertStmt = $pdo->prepare(
                "INSERT INTO lesson_contents
                    (
                        lesson_id,
                        content_type,
                        title,
                        content,
                        file_path,
                        content_order,
                        is_active
                    )
                 VALUES
                    (
                        :lesson_id,
                        :content_type,
                        :title,
                        :content,
                        :file_path,
                        :content_order,
                        1
                    )"
            );

            $insertStmt->execute([
                'lesson_id'     => (int) $lessonId,
                'content_type'  => $contentType,
                'title'         => $title,
                'content'       => $contentType === 'text'
                    ? $content
                    : null,
                'file_path'     => $relativeFilePath,
                'content_order' => (int) $contentOrder,
            ]);

            $pdo->commit();

            flash(
                'success',
                'Lesson content added successfully.'
            );

            redirect(
                'app/instructor/content/index.php?lesson_id=' .
                (int) $lessonId
            );

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
            | Remove the uploaded file if the database operation failed.
            */
            if (
                $uploadedFilePath !== null &&
                is_file($uploadedFilePath)
            ) {
                @unlink($uploadedFilePath);
            }

            $errors[] =
                'The content could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Add Lesson Content';
$showNavbar = true;

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="lesson-content-management">
    <div class="container">

        <section class="module-heading">
            <div>
                <span class="section-label">LESSON CONTENT</span>

                <h1>Add Content</h1>

                <p>
                    Adding content to:
                    <strong><?= e($lesson['title']) ?></strong>
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

        <form
            method="post"
            enctype="multipart/form-data"
            class="form-card"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="lesson_id"
                value="<?= (int) $lessonId ?>"
            >

            <div class="form-group">
                <label for="content_type">
                    Content Type
                    <span class="required">*</span>
                </label>

                <select
                    name="content_type"
                    id="content_type"
                    required
                >
                    <option
                        value="text"
                        <?= $contentType === 'text' ? 'selected' : '' ?>
                    >
                        Text
                    </option>

                    <option
                        value="image"
                        <?= $contentType === 'image' ? 'selected' : '' ?>
                    >
                        Image
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="title">
                    Content Title
                    <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="title"
                    id="title"
                    maxlength="200"
                    value="<?= e($title) ?>"
                    required
                >
            </div>

            <div
                class="form-group"
                id="textField"
            >
                <label for="content">
                    Text Content
                </label>

                <textarea
                    name="content"
                    id="content"
                    rows="10"
                ><?= e($content) ?></textarea>

                <small>
                    Use clear instructional text.
                    Keep paragraphs readable for learners.
                </small>
            </div>

            <div
                class="form-group"
                id="imageField"
            >
                <label for="content_file">
                    Image
                </label>

                <input
                    type="file"
                    name="content_file"
                    id="content_file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <small>
                    JPG, PNG or WebP. Maximum size: 5 MB.
                </small>
            </div>

            <div class="form-group">
                <label for="content_order">
                    Display Order
                </label>

                <input
                    type="number"
                    name="content_order"
                    id="content_order"
                    min="1"
                    value="<?= (int) $contentOrder ?>"
                >

                <small>
                    The system will adjust the position safely
                    if the selected number is already occupied.
                </small>
            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Content
                </button>

                <a
                    href="<?= e(
                        url(
                            'app/instructor/content/index.php?lesson_id=' .
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
    const contentType = document.getElementById('content_type');
    const textField = document.getElementById('textField');
    const imageField = document.getElementById('imageField');
    const textInput = document.getElementById('content');
    const imageInput = document.getElementById('content_file');

    function updateFields() {
        const isImage = contentType.value === 'image';

        textField.style.display = isImage ? 'none' : '';
        imageField.style.display = isImage ? '' : 'none';

        textInput.required = !isImage;
        imageInput.required = isImage;

        if (isImage) {
            textInput.value = '';
        } else {
            imageInput.value = '';
        }
    }

    contentType.addEventListener('change', updateFields);

    updateFields();
})();
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
