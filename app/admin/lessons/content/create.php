<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../includes/auth.php';

require_role('admin');

$pageTitle = 'Add Lesson Content';
$showNavbar = true;

$lessonId = (int) ($_GET['lesson_id'] ?? $_POST['lesson_id'] ?? 0);

if ($lessonId <= 0) {
    flash('error', 'Invalid lesson selected.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT l.id, l.title, s.name AS skill_name
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    flash('error', 'The selected lesson could not be found.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT COALESCE(MAX(content_order), 0) + 1
     FROM lesson_contents
     WHERE lesson_id = :lesson_id"
);
$stmt->execute(['lesson_id' => $lessonId]);
$nextOrder = (int) $stmt->fetchColumn();

$contentType = (string) ($_POST['content_type'] ?? 'text');
$title = trim((string) ($_POST['title'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));
$imageCaption = trim((string) ($_POST['image_caption'] ?? ''));
$contentOrder = (int) ($_POST['content_order'] ?? $nextOrder);
$isActive = isset($_POST['is_active']) ? 1 : 0;
$errors = [];

if (!in_array($contentType, ['text', 'image'], true)) {
    $contentType = 'text';
}

if (is_post()) {
    verify_csrf();

    if ($title === '') {
        $errors[] = 'Content title is required.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Content title must not exceed 200 characters.';
    }

    if ($contentOrder < 1) {
        $errors[] = 'Content order must be at least 1.';
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM lesson_contents
         WHERE lesson_id = :lesson_id
           AND content_order = :content_order"
    );
    $stmt->execute([
        'lesson_id' => $lessonId,
        'content_order' => $contentOrder,
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        $errors[] = 'That content order is already used in this lesson.';
    }

    $filePath = null;

    if ($contentType === 'text') {
        if ($content === '') {
            $errors[] = 'Text content is required for a text item.';
        }
    }

    if ($contentType === 'image') {
        $content = $imageCaption;

        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please select an image to upload.';
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The image upload failed. Please try again.';
        } else {
            $file = $_FILES['image'];
            $maxSize = 5 * 1024 * 1024;

            if ((int) $file['size'] > $maxSize) {
                $errors[] = 'Image size must not exceed 5 MB.';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            if (!isset($allowed[$mime])) {
                $errors[] = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
            }
        }
    }

    if ($errors === [] && $contentType === 'image') {
        $uploadDir = BASE_PATH . '/uploads/images/lessons';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $errors[] = 'The lesson image upload directory could not be created.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $_FILES['image']['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            $filename = 'lesson_' . $lessonId . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
            $destination = $uploadDir . '/' . $filename;

            if (!move_uploaded_file((string) $_FILES['image']['tmp_name'], $destination)) {
                $errors[] = 'The image could not be saved.';
            } else {
                $filePath = 'uploads/images/lessons/' . $filename;
            }
        }
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            "INSERT INTO lesson_contents
                (lesson_id, content_type, title, content, file_path, content_order, is_active)
             VALUES
                (:lesson_id, :content_type, :title, :content, :file_path, :content_order, :is_active)"
        );

        try {
            $stmt->execute([
                'lesson_id' => $lessonId,
                'content_type' => $contentType,
                'title' => $title,
                'content' => $content !== '' ? $content : null,
                'file_path' => $filePath,
                'content_order' => $contentOrder,
                'is_active' => $isActive,
            ]);
        } catch (Throwable $e) {
            if ($filePath !== null) {
                $savedFile = BASE_PATH . '/' . $filePath;
                if (is_file($savedFile)) {
                    unlink($savedFile);
                }
            }
            throw $e;
        }

        flash('success', 'Lesson content added successfully.');
        redirect('app/admin/lessons/content/index.php?lesson_id=' . $lessonId);
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<main class="admin-page lesson-content-management">
    <div class="container">

        <section class="module-heading">
            <div>
                <span class="section-label">ADD LESSON CONTENT</span>
                <h1><?= e($lesson['title']) ?></h1>
                <p><?= e($lesson['skill_name']) ?></p>
            </div>

            <div class="module-heading-actions">
                <a href="<?= e(url('app/admin/lessons/content/index.php?lesson_id=' . $lessonId)) ?>" class="btn btn-secondary">
                    ← Back to Content
                </a>
            </div>
        </section>

        <?php if ($errors !== []): ?>
            <div class="form-errors">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="form-card content-form-card">
            <form method="post" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="lesson_id" value="<?= $lessonId ?>">

                <div class="form-group">
                    <label for="content_type">Content Type <span class="required">*</span></label>
                    <select id="content_type" name="content_type" required>
                        <option value="text" <?= $contentType === 'text' ? 'selected' : '' ?>>Text</option>
                        <option value="image" <?= $contentType === 'image' ? 'selected' : '' ?>>Image</option>
                    </select>
                    <small>Live video will be added in a later development stage.</small>
                </div>

                <div class="form-group">
                    <label for="title">Content Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="200" required placeholder="e.g. Introduction">
                </div>

                <div class="form-group text-content-field">
                    <label for="content">Text Content</label>
                    <textarea id="content" name="content" rows="12" placeholder="Enter the lesson text learners should read..."><?= e($content) ?></textarea>
                    <small>Use this field for explanations, instructions, procedures and other learning text.</small>
                </div>

                <div class="form-group image-content-field">
                    <label for="image">Lesson Image</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small>Maximum 5 MB. Accepted formats: JPG, PNG, WEBP and GIF.</small>
                </div>

                <div class="form-group image-content-field">
                    <label for="content_caption">Image Caption / Description</label>
                    <textarea id="content_caption" name="image_caption" rows="4" maxlength="10000" placeholder="Describe what the image shows..."><?= $contentType === 'image' ? e($imageCaption) : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label for="content_order">Content Order <span class="required">*</span></label>
                    <input type="number" id="content_order" name="content_order" value="<?= $contentOrder ?>" min="1" step="1" required>
                    <small>Controls the order in which content items appear in the lesson.</small>
                </div>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>>
                        <span>Make this content active</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Content</button>
                    <a href="<?= e(url('app/admin/lessons/content/index.php?lesson_id=' . $lessonId)) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('content_type');
    const textFields = document.querySelectorAll('.text-content-field');
    const imageFields = document.querySelectorAll('.image-content-field');

    function refreshFields() {
        const isImage = type.value === 'image';
        textFields.forEach(function (el) {
            el.style.display = isImage ? 'none' : '';
        });
        imageFields.forEach(function (el) {
            el.style.display = isImage ? '' : 'none';
        });
    }

    type.addEventListener('change', refreshFields);
    refreshFields();
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
