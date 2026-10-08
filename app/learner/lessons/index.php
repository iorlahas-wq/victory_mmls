<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('learner');

$user = current_user();
$learnerId = (int) $user['id'];
$pageTitle = 'Browse Lessons';
$showNavbar = true;

$search = trim((string) ($_GET['search'] ?? ''));
$skillId = filter_var($_GET['skill_id'] ?? '', FILTER_VALIDATE_INT);
if ($skillId === false || $skillId < 1) {
    $skillId = null;
}

$skillsStmt = $pdo->query(
    "SELECT id, name FROM skills WHERE is_active = 1 ORDER BY name ASC"
);
$skills = $skillsStmt->fetchAll();

$where = ['l.is_published = 1', 's.is_active = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(l.title LIKE :search_title OR l.description LIKE :search_description OR s.name LIKE :search_skill)';
    $term = '%' . $search . '%';
    $params['search_title'] = $term;
    $params['search_description'] = $term;
    $params['search_skill'] = $term;
}

if ($skillId !== null) {
    $where[] = 's.id = :skill_id';
    $params['skill_id'] = $skillId;
}

$sql = "
    SELECT
        l.id,
        l.title,
        l.description,
        l.lesson_order,
        s.name AS skill_name,
        COALESCE(lp.status, 'not_started') AS progress_status,
        (
            SELECT COUNT(*)
            FROM lesson_contents lc
            WHERE lc.lesson_id = l.id
              AND lc.is_active = 1
        ) AS content_count,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.lesson_id = l.id
              AND q.is_active = 1
        ) AS question_count
    FROM lessons l
    INNER JOIN skills s ON s.id = l.skill_id
    LEFT JOIN learner_progress lp
        ON lp.lesson_id = l.id AND lp.learner_id = :learner_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.name ASC, l.lesson_order ASC, l.title ASC
";

$params['learner_id'] = $learnerId;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="learner-page">
    <div class="container">
        <section class="learner-page-heading">
            <span class="section-label">LEARNING LIBRARY</span>
            <h1>Browse Vocational Lessons</h1>
            <p>Choose a published lesson to study its text, images and instructional videos.</p>
        </section>

        <section class="learner-filter-card">
            <form method="get" action="<?= e(url('app/learner/lessons/index.php')) ?>" class="learner-filter-form">
                <div class="form-group">
                    <label for="search">Search lessons</label>
                    <input type="search" name="search" id="search" value="<?= e($search) ?>" placeholder="Search by lesson or skill">
                </div>
                <div class="form-group">
                    <label for="skill_id">Vocational skill</label>
                    <select name="skill_id" id="skill_id">
                        <option value="">All skills</option>
                        <?php foreach ($skills as $skill): ?>
                            <option value="<?= (int) $skill['id'] ?>" <?= $skillId === (int) $skill['id'] ? 'selected' : '' ?>>
                                <?= e($skill['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="learner-filter-actions">
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a class="btn btn-secondary" href="<?= e(url('app/learner/lessons/index.php')) ?>">Reset</a>
                </div>
            </form>
        </section>

        <div class="learner-results-heading">
            <h2><?= count($lessons) ?> lesson<?= count($lessons) === 1 ? '' : 's' ?> available</h2>
        </div>

        <?php if (!$lessons): ?>
            <div class="learner-empty">
                <h3>No lessons found</h3>
                <p>Try a different search or skill filter. Only published lessons are displayed here.</p>
            </div>
        <?php else: ?>
            <div class="learner-card-grid">
                <?php foreach ($lessons as $lesson): ?>
                    <article class="learner-lesson-card">
                        <span class="learner-skill-label"><?= e($lesson['skill_name']) ?></span>
                        <h3><?= e($lesson['title']) ?></h3>
                        <p><?= e(mb_strimwidth((string) ($lesson['description'] ?? ''), 0, 180, '…', 'UTF-8')) ?></p>

                        <div class="learner-lesson-meta">
                            <span><?= (int) $lesson['content_count'] ?> learning item(s)</span>
                            <span><?= (int) $lesson['question_count'] ?> active question(s)</span>
                        </div>

                        <span class="learner-progress-badge learner-progress-<?= e($lesson['progress_status']) ?>">
                            <?php
                            $progressLabels = [
                                'not_started' => 'Not started',
                                'in_progress' => 'In progress',
                                'completed' => 'Completed',
                            ];
                            echo e($progressLabels[$lesson['progress_status']] ?? 'Not started');
                            ?>
                        </span>

                        <a class="btn btn-primary" href="<?= e(url('app/learner/lessons/view.php?lesson_id=' . (int) $lesson['id'])) ?>">Open Lesson</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
