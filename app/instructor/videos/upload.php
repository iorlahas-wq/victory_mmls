<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$user = current_user();
$userId = (int) $user['id'];

$lessonId = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);

if (!$lessonId && isset($_POST['lesson_id'])) {
    $lessonId = filter_var($_POST['lesson_id'], FILTER_VALIDATE_INT);
}

if (!$lessonId) {
    flash('error', 'Please select a lesson.');
    redirect('app/instructor/videos/index.php');
}

$lessonStmt = $pdo->prepare(
    "SELECT id, title, description
     FROM lessons
     WHERE id = :id AND created_by = :created_by
     LIMIT 1"
);
$lessonStmt->execute([
    'id' => $lessonId,
    'created_by' => $userId,
]);
$lesson = $lessonStmt->fetch();

if (!$lesson) {
    http_response_code(404);
    exit('Lesson not found.');
}

$videoStmt = $pdo->prepare(
    "SELECT id, title, file_path, content, is_active
     FROM lesson_contents
     WHERE lesson_id = :lesson_id
       AND content_type = 'video'
       AND is_active = 1
     ORDER BY id DESC
     LIMIT 1"
);
$videoStmt->execute(['lesson_id' => $lessonId]);
$currentVideo = $videoStmt->fetch();

$errors = [];
$videoTitle = (string) ($currentVideo['title'] ?? ($lesson['title'] . ' - Instructional Video'));
$videoDescription = (string) ($currentVideo['content'] ?? '');

if (is_post()) {
    verify_csrf();

    $videoTitle = trim((string) ($_POST['video_title'] ?? ''));
    $videoDescription = trim((string) ($_POST['video_description'] ?? ''));

    if ($videoTitle === '') {
        $errors[] = 'Video title is required.';
    } elseif (mb_strlen($videoTitle) > 255) {
        $errors[] = 'Video title must not exceed 200 characters.';
    }

    if (
        !isset($_FILES['video_file']) ||
        $_FILES['video_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $errors[] = 'Please select an instructional video.';
    } else {
        $file = $_FILES['video_file'];

        if ($file['size'] > 200 * 1024 * 1024) {
            $errors[] = 'Video must not exceed 200 MB.';
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        $allowed = [
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm',
            'video/quicktime' => 'mov',
        ];

        if (!isset($allowed[$mime])) {
            $errors[] = 'Only MP4, WebM and MOV videos are allowed.';
        }
    }

    if (!$errors) {
        $uploadDir = UPLOAD_PATH . '/videos';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = $allowed[$mime];
        $filename = 'lesson_' . $lessonId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($_FILES['video_file']['tmp_name'], $target)) {
            $errors[] = 'The video could not be saved.';
        } else {
            $newPath = 'uploads/videos/' . $filename;

            $pdo->beginTransaction();

            try {
                /*
                 * Keep exactly one active instructional video for the lesson.
                 */
                $deactivate = $pdo->prepare(
                    "UPDATE lesson_contents
                     SET is_active = 0,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE lesson_id = :lesson_id
                       AND content_type = 'video'"
                );
                $deactivate->execute(['lesson_id' => $lessonId]);

                $insert = $pdo->prepare(
                    "INSERT INTO lesson_contents
                        (lesson_id, content_type, title, content, file_path,
                         content_order, is_active)
                     VALUES
                        (:lesson_id, 'video', :title, :content, :file_path,
                         :content_order, 1)"
                );

                $orderStmt = $pdo->prepare(
                    "SELECT COALESCE(MAX(content_order), 0) + 1
                     FROM lesson_contents
                     WHERE lesson_id = :lesson_id"
                );
                $orderStmt->execute(['lesson_id' => $lessonId]);
                $contentOrder = (int) $orderStmt->fetchColumn();

                $insert->execute([
                    'lesson_id' => $lessonId,
                    'title' => $videoTitle,
                    'content' => $videoDescription !== '' ? $videoDescription : null,
                    'file_path' => $newPath,
                    'content_order' => $contentOrder,
                ]);

                $pdo->commit();

                /*
                 * Remove the previous physical file only after the new
                 * database record has been safely created.
                 */
                if ($currentVideo && !empty($currentVideo['file_path'])) {
                    $oldPath = BASE_PATH . '/' . ltrim((string) $currentVideo['file_path'], '/');
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                flash(
                    'success',
                    $currentVideo
                        ? 'Instructional video replaced successfully.'
                        : 'Instructional video uploaded successfully.'
                );

                redirect('app/instructor/videos/index.php');
            } catch (Throwable $e) {
                $pdo->rollBack();

                if (is_file($target)) {
                    @unlink($target);
                }

                $errors[] = 'The instructional video could not be saved.';
            }
        }
    }
}

$pageTitle = 'Upload Instructional Video';
$showNavbar = true;

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="video-upload-page">
    <div class="container">

        <div class="module-header">
            <div>
                <span class="section-label">INSTRUCTIONAL VIDEO</span>
                <h1><?= $currentVideo ? 'Replace Video' : 'Upload Video' ?></h1>
                <p>
                    Lesson:
                    <strong><?= e($lesson['title']) ?></strong>
                </p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="module-card form-errors">
                <strong>Please correct the following:</strong>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($currentVideo): ?>
            <div class="module-card current-video-card">
                <span class="section-label">CURRENT VIDEO</span>
                <h2><?= e($currentVideo['title']) ?></h2>

                <video
                    class="lesson-video-player"
                    controls
                    preload="metadata"
                >
                    <source
                        src="<?= e(url($currentVideo['file_path'])) ?>"
                    >
                    Your browser does not support video playback.
                </video>

                <?php if (!empty($currentVideo['content'])): ?>
                    <p class="video-description">
                        <?= e($currentVideo['content']) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="module-card">
            <div class="video-upload-note">
                <strong><?= $currentVideo ? 'Replace the current video.' : 'Add the instructional video for this lesson.' ?></strong>
                <br>
                MP4, WebM and MOV are supported. Maximum size: 200 MB.
                Uploading a new video automatically deactivates the previous video for this lesson.
            </div>

            <form method="post" enctype="multipart/form-data" class="video-upload-form">
                <?= csrf_field() ?>
                <input type="hidden" name="lesson_id" value="<?= (int) $lessonId ?>">

                <div class="form-group">
                    <label for="video_title">
                        Video Title <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="video_title"
                        id="video_title"
                        maxlength="200"
                        value="<?= e($videoTitle) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="video_description">
                        Video Description
                    </label>
                    <textarea
                        name="video_description"
                        id="video_description"
                        rows="5"
                    ><?= e($videoDescription) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="video_file">
                        <?= $currentVideo ? 'New Video File' : 'Video File' ?>
                        <span class="required">*</span>
                    </label>
                    <input
                        type="file"
                        name="video_file"
                        id="video_file"
                        accept="video/mp4,video/webm,video/quicktime"
                        required
                    >
                    <p class="field-help">
                        MP4, WebM or MOV. Maximum 200 MB.
                    </p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $currentVideo ? 'Replace Video' : 'Upload Video' ?>
                    </button>

                    <a
                        href="<?= e(url('app/instructor/videos/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
