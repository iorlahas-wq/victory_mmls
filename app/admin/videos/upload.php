<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Upload Lesson Video';
$showNavbar = true;

$lessonId = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);

if (!$lessonId || $lessonId < 1) {
    flash('error', 'A valid lesson must be selected.');
    redirect('app/admin/videos/index.php');
}

$lessonStmt = $pdo->prepare(
    "SELECT
        l.id,
        l.title,
        l.description,
        l.lesson_order,
        s.name AS skill_name
     FROM lessons l
     INNER JOIN skills s ON s.id = l.skill_id
     WHERE l.id = :id
     LIMIT 1"
);
$lessonStmt->execute(['id' => $lessonId]);
$lesson = $lessonStmt->fetch();

if (!$lesson) {
    flash('error', 'The selected lesson could not be found.');
    redirect('app/admin/videos/index.php');
}

$videoStmt = $pdo->prepare(
    "SELECT id, title, content, file_path, is_active
     FROM lesson_contents
     WHERE lesson_id = :lesson_id
       AND content_type = 'video'
     ORDER BY id DESC
     LIMIT 1"
);
$videoStmt->execute(['lesson_id' => $lessonId]);
$currentVideo = $videoStmt->fetch();

$errors = [];

if (is_post()) {

    verify_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($title === '') {
        $errors[] = 'Video title is required.';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Video title must not exceed 255 characters.';
    }

    if (mb_strlen($description) > 5000) {
        $errors[] = 'Video description must not exceed 5,000 characters.';
    }

    if (!isset($_FILES['video'])) {
        $errors[] = 'Please select a video file.';
    } else {
        $file = $_FILES['video'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The video upload failed. Check the file size and try again.';
        }

        if ((int) $file['size'] <= 0) {
            $errors[] = 'The selected video file is empty.';
        }

        /*
         * 200 MB application-level limit.
         * PHP upload_max_filesize and post_max_size must also permit the file.
         */
        if ((int) $file['size'] > 200 * 1024 * 1024) {
            $errors[] = 'The video file must not exceed 200 MB.';
        }

        $extension = strtolower(
            pathinfo((string) $file['name'], PATHINFO_EXTENSION)
        );

        $allowedExtensions = [
            'mp4',
            'webm',
            'ogg',
        ];

        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Only MP4, WebM and OGG video files are allowed.';
        }
    }

    if (!$errors) {

        $file = $_FILES['video'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);

        $allowedMimeTypes = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
        ];

        if (!isset($allowedMimeTypes[$mime])) {
            $errors[] = 'The uploaded file is not recognised as a supported video.';
        }
    }

    if (!$errors) {

        $extension = $allowedMimeTypes[$mime];

        $uploadDirectory = UPLOAD_PATH . '/videos';

        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                $errors[] = 'The video upload directory could not be created.';
            }
        }
    }

    if (!$errors) {

        $safeFileName =
            'lesson_' .
            (int) $lessonId .
            '_' .
            bin2hex(random_bytes(10)) .
            '.' .
            $extension;

        $destination = $uploadDirectory . '/' . $safeFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'The video could not be saved on the server.';
        }
    }

    if (!$errors) {

        $relativePath =
            'uploads/videos/' .
            $safeFileName;

        try {

            $pdo->beginTransaction();

            /*
             * This module deliberately keeps one video record per lesson.
             * If a previous video exists, it is replaced.
             */
            $existingStmt = $pdo->prepare(
                "SELECT id, file_path
                 FROM lesson_contents
                 WHERE lesson_id = :lesson_id
                   AND content_type = 'video'
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $existingStmt->execute(['lesson_id' => $lessonId]);
            $existing = $existingStmt->fetch();

            if ($existing) {

                $updateStmt = $pdo->prepare(
                    "UPDATE lesson_contents
                     SET
                        title = :title,
                        content = :content,
                        file_path = :file_path,
                        content_order = 9999,
                        is_active = 1,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                );

                $updateStmt->execute([
                    'title' => $title,
                    'content' => $description,
                    'file_path' => $relativePath,
                    'id' => (int) $existing['id'],
                ]);

                $oldRelativePath = (string) ($existing['file_path'] ?? '');

            } else {

                /*
                 * 9999 keeps the video logically separate from the normal
                 * text/image sequence while remaining compatible with the
                 * existing lesson_contents structure.
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
                            'video',
                            :title,
                            :content,
                            :file_path,
                            9999,
                            1
                        )"
                );

                $insertStmt->execute([
                    'lesson_id' => $lessonId,
                    'title' => $title,
                    'content' => $description,
                    'file_path' => $relativePath,
                ]);

                $oldRelativePath = '';
            }

            $pdo->commit();

            /*
             * Remove the old physical file only after the database update
             * succeeds.
             */
            if ($oldRelativePath !== '') {

                $oldFile = BASE_PATH . '/' . ltrim($oldRelativePath, '/');

                if (
                    is_file($oldFile) &&
                    realpath($oldFile) !== realpath($destination)
                ) {
                    @unlink($oldFile);
                }
            }

            flash(
                'success',
                'Instructional video saved successfully.'
            );

            redirect('app/admin/videos/index.php');

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (is_file($destination)) {
                @unlink($destination);
            }

            $errors[] = DEBUG
                ? $e->getMessage()
                : 'The video could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container page-content video-upload-page">

    <div class="module-header">
        <div>
            <span class="section-label">LESSON VIDEO</span>
            <h1><?= e($currentVideo ? 'Replace Instructional Video' : 'Upload Instructional Video') ?></h1>
            <p>
                <?= e($lesson['skill_name']) ?>
                —
                <?= e($lesson['lesson_order']) ?>.
                <?= e($lesson['title']) ?>
            </p>
        </div>

        <a
            href="<?= e(url('app/admin/videos/index.php')) ?>"
            class="btn btn-secondary"
        >
            Back to Videos
        </a>
    </div>

    <?php if ($errors): ?>

        <div class="alert alert-error">
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

    <?php endif; ?>

    <?php if ($currentVideo && !empty($currentVideo['file_path'])): ?>

        <section class="module-card current-video-card">

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
                Your browser does not support HTML5 video.
            </video>

            <?php if (!empty($currentVideo['content'])): ?>
                <p class="video-description">
                    <?= nl2br(e($currentVideo['content'])) ?>
                </p>
            <?php endif; ?>

        </section>

    <?php endif; ?>

    <section class="module-card">

        <form
            method="post"
            enctype="multipart/form-data"
            class="video-upload-form"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="lesson_id"
                value="<?= (int) $lessonId ?>"
            >

            <div class="form-group">
                <label for="title">Video Title <span class="required">*</span></label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="255"
                    required
                    value="<?= e(old('title', $currentVideo['title'] ?? $lesson['title'] . ' — Practical Demonstration')) ?>"
                    placeholder="e.g. Complete Practical Demonstration"
                >
            </div>

            <div class="form-group">
                <label for="description">Video Description</label>

                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    maxlength="5000"
                    placeholder="Briefly describe what the instructional video demonstrates..."
                ><?= e(old('description', $currentVideo['content'] ?? '')) ?></textarea>
            </div>

            <div class="form-group">

                <label for="video">
                    <?= $currentVideo ? 'Replacement Video' : 'Video File' ?>
                    <span class="required">*</span>
                </label>

                <input
                    type="file"
                    id="video"
                    name="video"
                    accept="video/mp4,video/webm,video/ogg"
                    required
                >

                <p class="field-help">
                    Recommended format: MP4. Maximum application size: 200 MB.
                    Your PHP upload settings must also allow the selected file size.
                </p>

            </div>

            <div class="video-upload-note">
                <strong>One video per lesson.</strong>
                <?= $currentVideo
                    ? 'Uploading this file will replace the existing instructional video.'
                    : 'This video will become the single instructional video for this lesson.'
                ?>
            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= $currentVideo ? 'Replace Video' : 'Upload Video' ?>
                </button>

                <a
                    href="<?= e(url('app/admin/videos/index.php')) ?>"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
