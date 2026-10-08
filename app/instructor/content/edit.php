<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$user = current_user();
$userId = (int) $user['id'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id && isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if (!$id) {
    http_response_code(400);
    exit('Invalid content ID.');
}

$stmt = $pdo->prepare(
    "SELECT lc.*
     FROM lesson_contents lc
     INNER JOIN lessons l ON l.id = lc.lesson_id
     WHERE lc.id = :id AND l.created_by = :created_by
     LIMIT 1"
);
$stmt->execute([
    'id' => $id,
    'created_by' => $userId,
]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit('Content item not found.');
}

$lessonId = (int) $item['lesson_id'];
$errors = [];

$contentType = (string) $item['content_type'];
$title = (string) $item['title'];
$content = (string) ($item['content'] ?? '');
$displayOrder = (int) $item['content_order'];
$isActive = (int) $item['is_active'];

if (is_post()) {
    verify_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $displayOrder = filter_var($_POST['content_order'] ?? null, FILTER_VALIDATE_INT);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($displayOrder === false || $displayOrder < 1) {
        $errors[] = 'Display order must be at least 1.';
        $displayOrder = (int) $item['content_order'];
    }

    if ($title === '') {
        $errors[] = 'Content title is required.';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Content title must not exceed 200 characters.';
    }

    $filePath = $item['file_path'];

    if ($contentType === 'text' && $content === '') {
        $errors[] = 'Enter the text content.';
    }

    if ($contentType === 'image' && isset($_FILES['content_file']) && $_FILES['content_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['content_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The selected image could not be uploaded.';
        } else {
            $file = $_FILES['content_file'];

            if ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image must not exceed 5 MB.';
            }

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowed[$mime])) {
                $errors[] = 'Only JPG, PNG and WebP images are allowed.';
            }

            if (!$errors) {
                $uploadDir = UPLOAD_PATH . '/images';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = 'lesson_' . $lessonId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                $target = $uploadDir . '/' . $filename;

                if (!move_uploaded_file($file['tmp_name'], $target)) {
                    $errors[] = 'The new image could not be saved.';
                } else {
                    $oldPath = BASE_PATH . '/' . ltrim((string) $filePath, '/');
                    if ($filePath && is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                    $filePath = 'uploads/images/' . $filename;
                }
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();

        try {
            $oldOrder = (int) $item['content_order'];

            if ($displayOrder !== $oldOrder) {
                if ($displayOrder > $oldOrder) {
                    $shift = $pdo->prepare(
                        "UPDATE lesson_contents
                         SET content_order = content_order - 1
                         WHERE lesson_id = :lesson_id
                           AND content_type <> 'video'
                           AND content_order > :old_order
                           AND content_order <= :new_order"
                    );
                } else {
                    $shift = $pdo->prepare(
                        "UPDATE lesson_contents
                         SET content_order = content_order + 1
                         WHERE lesson_id = :lesson_id
                           AND content_type <> 'video'
                           AND content_order >= :new_order
                           AND content_order < :old_order"
                    );
                }

                $shift->execute([
                    'lesson_id' => $lessonId,
                    'old_order' => $oldOrder,
                    'new_order' => $displayOrder,
                ]);
            }

            $update = $pdo->prepare(
                "UPDATE lesson_contents
                 SET title = :title,
                     content = :content,
                     file_path = :file_path,
                     content_order = :content_order,
                     is_active = :is_active,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $update->execute([
                'title' => $title,
                'content' => $contentType === 'text' ? $content : null,
                'file_path' => $filePath,
                'content_order' => $displayOrder,
                'is_active' => $isActive,
                'id' => $id,
            ]);

            $pdo->commit();

            flash('success', 'Lesson content updated successfully.');
            redirect('app/instructor/content/index.php?lesson_id=' . $lessonId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'The content could not be updated.';
        }
    }
}

$pageTitle = 'Edit Lesson Content';
$showNavbar = true;

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="video-upload-page lesson-content-management">
    <div class="container">

        <div class="module-heading">
            <div>
                <span class="section-label">LESSON CONTENT</span>
                <h1>Edit Content</h1>
                <p>Update this learning item.</p>
            </div>
        </div>

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

        <form method="post" enctype="multipart/form-data" class="form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div class="form-group">
                <label>Content Type</label>
                <input type="text" value="<?= e(ucfirst($contentType)) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="title">Content Title <span class="required">*</span></label>
                <input
                    type="text"
                    name="title"
                    id="title"
                    maxlength="200"
                    value="<?= e($title) ?>"
                    required
                >
            </div>

            <?php if ($contentType === 'text'): ?>

                <div class="form-group">
                    <label for="content">Text Content <span class="required">*</span></label>
                    <textarea name="content" id="content" rows="12" required><?= e($content) ?></textarea>
                </div>

            <?php else: ?>

                <?php if (!empty($item['file_path'])): ?>
                    <div class="form-group current-image">
                        <label>Current Image</label>
                        <img
                            src="<?= e(url($item['file_path'])) ?>"
                            alt="<?= e($title) ?>"
                        >
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="content_file">Replace Image</label>
                    <input
                        type="file"
                        name="content_file"
                        id="content_file"
                        accept=".jpg,.jpeg,.png,.webp"
                    >
                    <small>Leave empty to keep the current image. Maximum 5 MB.</small>
                </div>

            <?php endif; ?>

            <div class="form-group">
                <label for="content_order">Display Order</label>
                <input
                    type="number"
                    name="content_order"
                    id="content_order"
                    min="1"
                    value="<?= (int) $displayOrder ?>"
                >
            </div>

            <div class="checkbox-group">
                <label>
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $isActive === 1 ? 'checked' : '' ?>
                    >
                    Keep this content active
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a
                    href="<?= e(url('app/instructor/content/index.php?lesson_id=' . $lessonId)) ?>"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>
            </div>
        </form>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
