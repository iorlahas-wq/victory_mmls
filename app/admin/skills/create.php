<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Add Vocational Skill';
$showNavbar = true;

$error = '';

$name = trim((string) old('name'));
$description = trim((string) old('description'));
$isActive = 1;

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
            'SELECT id FROM skills WHERE name = :name LIMIT 1'
        );

        $stmt->execute([':name' => $name]);

        if ($stmt->fetch()) {
            $error = 'A vocational skill with this name already exists.';
        } else {

            $user = current_user();

            $stmt = $pdo->prepare(
                "
                INSERT INTO skills (
                    name,
                    description,
                    is_active,
                    created_by
                )
                VALUES (
                    :name,
                    :description,
                    :is_active,
                    :created_by
                )
                "
            );

            $stmt->execute([
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':is_active' => $isActive,
                ':created_by' => (int) $user['id']
            ]);

            flash('success', 'Vocational skill created successfully.');

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
                <h1>Add Vocational Skill</h1>
                <p>
                    Create a new practical skill that can later contain
                    structured lessons and multimedia content.
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
                <h2>Create Skill</h2>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= e(url('app/admin/skills/create.php')) ?>"
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
                        placeholder="e.g. Catering and Baking"
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
                        placeholder="Describe what learners will acquire from this skill..."
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
                        Make this skill active immediately
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
                        Create Skill
                    </button>

                </div>
            </form>

        </section>

    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
