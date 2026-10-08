<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../includes/auth.php';

require_role('admin');

$pageTitle = 'Edit Lesson Content';
$showNavbar = true;

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid content selected.');
    redirect('app/admin/lessons/index.php');
}

$stmt = $pdo->prepare(
    "SELECT lc.*, l.title AS lesson_title, s.name AS skill_name
     FROM lesson_contents lc
     INNER JOIN lessons l ON l.id = lc.lesson_id
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE lc.id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'The selected content could not be found.');
    redirect('app/admin/lessons/index.php');
}

$lessonId = (int) $item['lesson_id'];
$contentType = (string) $item['content_type'];
$title = trim((string) ($_POST['title'] ?? ($item['title'] ?? '')));
$content = trim((string) ($_POST['content'] ?? ($item['content'] ?? '')));
$contentOrder = (int) ($_POST['content_order'] ?? $item['content_order']);
$isActive = isset($_POST['is_active']) ? 1 : (int) $item['is_active'];
$errors = [];
$oldFilePath = $item['file_path'] ?? null;
$newFilePath = null;

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
           AND content_order = :content_order
           AND id <> :id"
    );
    $stmt->execute([
        'lesson_id' => $lessonId,
        'content_order' => $contentOrder,
        'id' => $id,
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        $errors[] = 'That content order is already used in this lesson.';
    }

    if ($contentType === 'text' && $content === '') {
        $errors[] = 'Text content is required for a text item.';
    }

    $uploadedImage = null;

    if ($contentType === 'image' && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
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
            } else {
                $uploadedImage = [
                    'tmp_name' => (string) $file['tmp_name'],
                    'mime' => $mime,
                    'extension' => $allowed[$mime],
                ];
            }
        }
    }

    if ($errors === [] && $uploadedImage !== null) {
        $uploadDir = BASE_PATH . '/uploads/images/lessons';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $errors[] = 'The lesson image upload directory could not be created.';
        } else {
            $filename = 'lesson_' . $lessonId . '_' . bin2hex(random_bytes(12)) . '.' . $uploadedImage['extension'];
            $destination = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($uploadedImage['tmp_name'], $destination)) {
                $errors[] = 'The new image could not be saved.';
            } else {
                $newFilePath = 'uploads/images/lessons/' . $filename;
            }
        }
    }

    if ($errors === []) {
        $filePath = $newFilePath ?? $oldFilePath;

        $stmt = $pdo->prepare(
            "UPDATE lesson_contents
             SET title = :title,
                 content = :content,
                 file_path = :file_path,
                 content_order = :content_order,
                 is_active = :is_active
             WHERE id = :id"
        );

        try {
            $stmt->execute([
                'title' => $title,
                'content' => $content !== '' ? $content : null,
                'file_path' => $filePath,
                'content_order' => $contentOrder,
                'is_active' => $isActive,
                'id' => $id,
            ]);
        } catch (Throwable $e) {
            if ($newFilePath !== null) {
                $newFile = BASE_PATH . '/' . $newFilePath;
                if (is_file($newFile)) {
                    unlink($newFile);
                }
            }
            throw $e;
        }

        if ($newFilePath !== null && $oldFilePath !== null && $oldFilePath !== '') {
            $oldFile = BASE_PATH . '/' . $oldFilePath;
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        flash('success', 'Lesson content updated successfully.');
        redirect('app/admin/lessons/content/index.php?lesson_id=' . $lessonId);
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<main class="admin-page lesson-content-management">
    <div class="container">

        <section class="module-heading">
            <div>
                <span class="section-label">EDIT LESSON CONTENT</span>
                <h1><?= e($item['lesson_title']) ?></h1>
                <p><?= e($item['skill_name']) ?></p>
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
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="form-group">
                    <label>Content Type</label>
                    <input type="text" value="<?= e(ucfirst($contentType)) ?>" disabled>
                    <small>Content type cannot be changed after creation. Create a new item if a different type is needed.</small>
                </div>

                <div class="form-group">
                    <label for="title">Content Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" value="<?= e($title) ?>" maxlength="200" required>
                </div>

                <?php if ($contentType === 'text'): ?>
                    <div class="form-group">
                        <label for="content">Text Content <span class="required">*</span></label>
                        <textarea id="content" name="content" rows="14" required><?= e($content) ?></textarea>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Current Image</label>
                        <?php if (!empty($item['file_path'])): ?>
                            <div class="current-image">
                                <img src="<?= e(url((string) $item['file_path'])) ?>" alt="<?= e($title) ?>">
                            </div>
                        <?php else: ?>
                            <p class="muted">No image is currently stored.</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="image">Replace Image</label>
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                        <small>Optional. Maximum 5 MB. Accepted formats: JPG, PNG, WEBP and GIF.</small>
                    </div>

                    <div class="form-group">
                        <label for="content">Image Caption / Description</label>
                        <textarea id="content" name="content" rows="5" maxlength="10000"><?= e($content) ?></textarea>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="content_order">Content Order <span class="required">*</span></label>
                    <input type="number" id="content_order" name="content_order" value="<?= $contentOrder ?>" min="1" step="1" required>
                </div>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>>
                        <span>Make this content active</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= e(url('app/admin/lessons/content/index.php?lesson_id=' . $lessonId)) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
