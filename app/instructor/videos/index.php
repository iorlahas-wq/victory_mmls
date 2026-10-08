<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('instructor');

$user = current_user();
$userId = (int) $user['id'];

$search = trim((string) ($_GET['search'] ?? ''));

$sql = "
    SELECT
        l.id,
        l.title,
        l.is_published,
        lc.id AS video_id,
        lc.title AS video_title,
        lc.file_path,
        lc.is_active AS video_active
    FROM lessons l
    LEFT JOIN lesson_contents lc
        ON lc.lesson_id = l.id
       AND lc.content_type = 'video'
       AND lc.is_active = 1
    WHERE l.created_by = :created_by
";

$params = ['created_by' => $userId];

if ($search !== '') {
    $sql .= " AND l.title LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY l.lesson_order ASC, l.title ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

$pageTitle = 'Instructional Videos';
$showNavbar = true;

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="video-management-page">
    <div class="container">

        <div class="module-header">
            <div>
                <span class="section-label">MULTIMEDIA CONTENT</span>
                <h1>Instructional Videos</h1>
                <p>Manage the instructional video attached to each of your lessons.</p>
            </div>
        </div>

        <div class="module-card">
            <form method="get" class="video-filter-form">

                <div class="form-group">
                    <label for="search">Search Lesson</label>
                    <input
                        type="search"
                        name="search"
                        id="search"
                        value="<?= e($search) ?>"
                        placeholder="Enter lesson title"
                    >
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="<?= e(url('app/instructor/videos/index.php')) ?>" class="btn btn-secondary">
                        Reset
                    </a>
                </div>

            </form>
        </div>

        <div class="module-card">
            <div class="table-header">
                <div>
                    <span class="section-label">MY LESSONS</span>
                    <h2>Instructional Video Status</h2>
                </div>
            </div>

            <?php if (empty($lessons)): ?>

                <div class="empty-state">
                    <h3>No lessons found</h3>
                    <p>Create a lesson first or change the search term.</p>
                </div>

            <?php else: ?>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Lesson</th>
                                <th>Status</th>
                                <th>Video</th>
                                <th>Video Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($lessons as $lesson): ?>
                                <tr>
                                    <td>
                                        <span class="lesson-video-title">
                                            <?= e($lesson['title']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= (int) $lesson['is_published'] === 1 ? 'Published' : 'Draft' ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($lesson['video_id'])): ?>
                                            <span class="video-file-name">
                                                <?= e($lesson['video_title']) ?>
                                            </span>
                                            <span class="muted">
                                                <?= e(basename((string) $lesson['file_path'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="muted">No video uploaded</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($lesson['video_id'])): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-missing">Missing</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="actions-cell">
                                        <?php if (!empty($lesson['video_id'])): ?>
                                            <a
                                                href="<?= e(url('app/instructor/videos/upload.php?lesson_id=' . (int) $lesson['id'])) ?>"
                                                class="action-link"
                                            >
                                                Replace
                                            </a>

                                            <a
                                                href="<?= e(url($lesson['file_path'])) ?>"
                                                class="action-link"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                Open
                                            </a>
                                        <?php else: ?>
                                            <a
                                                href="<?= e(url('app/instructor/videos/upload.php?lesson_id=' . (int) $lesson['id'])) ?>"
                                                class="action-link"
                                            >
                                                Upload
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
