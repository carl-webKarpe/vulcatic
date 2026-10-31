<?php
// Shown right after logging in with a temporary password from the admin.
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if (!$user['must_change_password']) redirect(home_for($user));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $new = $_POST['new_password'] ?? '';
    if (strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
    elseif ($new !== ($_POST['confirm_password'] ?? '')) $errors['confirm_password'] = 'Passwords do not match.';
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        flash('success', 'New password saved. Welcome back!');
        redirect(home_for($user));
    }
}

$pageTitle = 'Set a new password';
require __DIR__ . '/includes/header.php';
$input = 'w-full rounded-xl border border-gray-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';
?>
<main class="grid min-h-screen place-items-center px-5 py-10">
  <form method="post" class="w-full max-w-md rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5" data-once novalidate>
    <?= csrf_field() ?>
    <?= logo_full('mx-auto h-24') ?>
    <h1 class="mt-4 text-center text-2xl font-bold">Set a new password</h1>
    <p class="mt-1 text-center text-sm text-gray-500">Hi <?= e(first_name($user['full_name'])) ?>! You logged in with a temporary password. Choose your own to continue.</p>
    <div class="mt-6 space-y-4">
      <div>
        <label for="new_password" class="mb-1 block text-sm font-medium">New password</label>
        <input id="new_password" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" class="<?= $input ?>">
        <?php if (isset($errors['new_password'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['new_password']) ?></p><?php endif; ?>
      </div>
      <div>
        <label for="confirm_password" class="mb-1 block text-sm font-medium">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" class="<?= $input ?>">
        <?php if (isset($errors['confirm_password'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
      </div>
    </div>
    <button class="mt-6 w-full rounded-xl bg-brand-500 py-3 font-semibold text-white shadow-md shadow-brand-500/30 hover:bg-brand-600">Save and continue</button>
  </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
