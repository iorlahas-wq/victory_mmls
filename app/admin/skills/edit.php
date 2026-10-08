<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Edit Vocational Skill';
$showNavbar = true;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    flash('error', 'Invalid skill selected.');
    redirect('app/admin/skills/index.php');
}

$stmt = $pdo->prepare(
    "
    SELECT id, name, description, is_active
    FROM skills
    WHERE id = :id
    LIMIT 1
    "
);

$stmt->execute([':id' => $id]);

$skill = $stmt->fetch();

if (!$skill) {
    flash('error', 'The selected skill could not be found.');
    redirect('app/admin/skills/index.php');
}

$error = '';

$name = (string) $skill['name'];
$description = (string) ($skill['description'] ?? '');
$isActive = (int) $skill['is_active'];

if (is_post()) {

    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Please enter the skill name.';
    } elseif (mb_strlen($name) > 150) {
        $error = 'The skill name cannot exceed 150 characters.';
    } elseif (mb_strlen($description) > 5000) {
        $error = 'The description is too long.';
    } else {

        $stmt = $pdo->prepare(
            "
            SELECT id
            FROM skills
            WHERE name = :name
              AND id <> :id
            LIMIT 1
            "
        );

        $stmt->execute([
            ':name' => $name,
            ':id' => $id
        ]);

        if ($stmt->fetch()) {
            $error = 'Another vocational skill already uses this name.';
        } else {

            $stmt = $pdo->prepare(
                "
                UPDATE skills
                SET
                    name = :name,
                    description = :description,
                    is_active = :is_active
                WHERE id = :id
                "
            );

            $stmt->execute([
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':is_active' => $isActive,
                ':id' => $id
            ]);

            flash('success', 'Vocational skill updated successfully.');

            redirect('app/admin/skills/index.php');
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="admin-page">
    <div class="container">

        <section class="module-header">
            <div>
                <span class="section-label">CONTENT MANAGEMENT</span>
                <h1>Edit Vocational Skill</h1>
                <p>
                    Update the skill details and its availability
                    to learners.
                </p>
            </div>

            <a
                href="<?= e(url('app/admin/skills/index.php')) ?>"
                class="btn btn-secondary"
            >
                ← Back to Skills
            </a>
        </section>

        <section class="module-card form-card">

            <div class="form-card-heading">
                <span class="section-label">SKILL DETAILS</span>
                <h2><?= e($skill['name']) ?></h2>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= e(
                    url(
                        'app/admin/skills/edit.php?id=' .
                        (int) $skill['id']
                    )
                ) ?>"
                class="admin-form"
            >
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="name">
                        Skill Name <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($name) ?>"
                        maxlength="150"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>

                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        maxlength="5000"
                    ><?= e($description) ?></textarea>
                </div>

                <label class="checkbox-field">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        <?= $isActive === 1 ? 'checked' : '' ?>
                    >

                    <span>
                        Make this skill available to learners
                    </span>
                </label>

                <div class="form-actions">

                    <a
                        href="<?= e(url('app/admin/skills/index.php')) ?>"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>

                </div>
            </form>

        </section>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
