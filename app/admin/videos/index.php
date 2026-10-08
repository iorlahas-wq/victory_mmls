<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Lesson Videos';
$showNavbar = true;

$search = trim((string) ($_GET['search'] ?? ''));
$skillId = filter_var($_GET['skill_id'] ?? '', FILTER_VALIDATE_INT);
$status = (string) ($_GET['status'] ?? 'all');

$skillsStmt = $pdo->query(
    "SELECT id, name
     FROM skills
     ORDER BY name ASC"
);
$skills = $skillsStmt->fetchAll();

$where = [
    "l.is_published = 1",
];
$params = [];

if ($search !== '') {
    $where[] = "(l.title LIKE :search OR s.name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($skillId !== false && $skillId !== null && $skillId > 0) {
    $where[] = "l.skill_id = :skill_id";
    $params['skill_id'] = $skillId;
}

$videoWhere = "lc.lesson_id = l.id AND lc.content_type = 'video'";

if ($status === 'with_video') {
    $where[] = "EXISTS (
        SELECT 1 FROM lesson_contents v
        WHERE v.lesson_id = l.id
          AND v.content_type = 'video'
          AND v.is_active = 1
    )";
} elseif ($status === 'without_video') {
    $where[] = "NOT EXISTS (
        SELECT 1 FROM lesson_contents v
        WHERE v.lesson_id = l.id
          AND v.content_type = 'video'
    )";
}

$sql = "
    SELECT
        l.id,
        l.title,
        l.lesson_order,
        s.name AS skill_name,
        (
            SELECT v.id
            FROM lesson_contents v
            WHERE v.lesson_id = l.id
              AND v.content_type = 'video'
            ORDER BY v.id DESC
            LIMIT 1
        ) AS video_id,
        (
            SELECT v.title
            FROM lesson_contents v
            WHERE v.lesson_id = l.id
              AND v.content_type = 'video'
            ORDER BY v.id DESC
            LIMIT 1
        ) AS video_title,
        (
            SELECT v.file_path
            FROM lesson_contents v
            WHERE v.lesson_id = l.id
              AND v.content_type = 'video'
            ORDER BY v.id DESC
            LIMIT 1
        ) AS video_path,
        (
            SELECT v.is_active
            FROM lesson_contents v
            WHERE v.lesson_id = l.id
              AND v.content_type = 'video'
            ORDER BY v.id DESC
            LIMIT 1
        ) AS video_active
    FROM lessons l
    INNER JOIN skills s ON s.id = l.skill_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.name ASC, l.lesson_order ASC, l.title ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="container page-content video-management-page">

    <div class="module-header">
        <div>
            <span class="section-label">VIDEO MANAGEMENT</span>
            <h1>Lesson Instructional Videos</h1>
            <p>
                Select a lesson and upload or replace its single practical
                instructional video.
            </p>
        </div>
    </div>

    <section class="module-card video-filter-card">

        <form method="get" class="video-filter-form">

            <div class="form-group">
                <label for="search">Search</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search lessons or skills..."
                >
            </div>

            <div class="form-group">
                <label for="skill_id">Skill</label>
                <select id="skill_id" name="skill_id">
                    <option value="">All skills</option>

                    <?php foreach ($skills as $skill): ?>
                        <option
                            value="<?= (int) $skill['id'] ?>"
                            <?= ((int) $skill['id'] === (int) $skillId) ? 'selected' : '' ?>
                        >
                            <?= e($skill['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Video Status</label>
                <select id="status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All lessons</option>
                    <option value="with_video" <?= $status === 'with_video' ? 'selected' : '' ?>>With video</option>
                    <option value="without_video" <?= $status === 'without_video' ? 'selected' : '' ?>>Without video</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= e(url('app/admin/videos/index.php')) ?>" class="btn btn-secondary">Reset</a>
            </div>

        </form>

    </section>

    <section class="module-card">

        <div class="table-header">
            <div>
                <span class="section-label">LESSON VIDEO CATALOGUE</span>
                <h2><?= count($lessons) ?> lessons</h2>
            </div>
        </div>

        <?php if (!$lessons): ?>

            <div class="empty-state">
                <h3>No lessons found</h3>
                <p>Adjust the filters and try again.</p>
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Skill</th>
                            <th>Lesson</th>
                            <th>Video</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($lessons as $lesson): ?>

                        <tr>

                            <td>
                                <strong><?= e($lesson['skill_name']) ?></strong>
                            </td>

                            <td>
                                <div class="lesson-video-title">
                                    <?= e($lesson['lesson_order']) ?>.
                                    <?= e($lesson['title']) ?>
                                </div>
                            </td>

                            <td>

                                <?php if (!empty($lesson['video_id'])): ?>

                                    <span class="video-file-name">
                                        <?= e($lesson['video_title'] ?: 'Instructional video') ?>
                                    </span>

                                    <?php if (!empty($lesson['video_path'])): ?>
                                        <a
                                            class="small-link"
                                            href="<?= e(url($lesson['video_path'])) ?>"
                                            target="_blank"
                                        >
                                            Preview
                                        </a>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="muted">No video uploaded</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!empty($lesson['video_id'])): ?>

                                    <?php if ((int) $lesson['video_active'] === 1): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="status-badge status-missing">Missing</span>

                                <?php endif; ?>

                            </td>

                            <td class="actions-cell">

                                <a
                                    class="action-link"
                                    href="<?= e(url('app/admin/videos/upload.php?lesson_id=' . (int) $lesson['id'])) ?>"
                                >
                                    <?= !empty($lesson['video_id']) ? 'Replace' : 'Upload' ?>
                                </a>

                                <?php if (!empty($lesson['video_id'])): ?>

                                    <form
                                        method="post"
                                        action="<?= e(url('app/admin/videos/toggle.php')) ?>"
                                        class="inline-form"
                                    >
                                        <?= csrf_field() ?>
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $lesson['video_id'] ?>"
                                        >
                                        <button type="submit" class="action-button">
                                            <?= (int) $lesson['video_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <form
                                        method="post"
                                        action="<?= e(url('app/admin/videos/delete.php')) ?>"
                                        class="inline-form"
                                        onsubmit="return confirm('Remove this instructional video from the lesson?');"
                                    >
                                        <?= csrf_field() ?>
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $lesson['video_id'] ?>"
                                        >
                                        <button type="submit" class="action-button danger">
                                            Remove
                                        </button>
                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <div class="module-note">
        <strong>Video policy:</strong>
        Each lesson is designed to have one active instructional video.
        Uploading a replacement automatically removes the previous video file.
        Recommended formats are MP4 or WebM.
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
