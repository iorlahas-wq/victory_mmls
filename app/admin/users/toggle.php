<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
if (!is_post()) { http_response_code(405); exit('Method Not Allowed'); }
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
$currentUserId = (int) current_user()['id'];
if ($id <= 0 || $id === $currentUserId) { flash('error', 'Invalid user action. You cannot deactivate your own account.'); redirect('app/admin/users/index.php'); }
$stmt = $pdo->prepare('SELECT id, is_active FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();
if (!$user) { flash('error', 'The selected user could not be found.'); redirect('app/admin/users/index.php'); }
$newStatus = (int) $user['is_active'] === 1 ? 0 : 1;
$stmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
$stmt->execute(['is_active' => $newStatus, 'id' => $id]);
flash('success', $newStatus === 1 ? 'User activated successfully.' : 'User deactivated successfully.');
redirect('app/admin/users/index.php');
