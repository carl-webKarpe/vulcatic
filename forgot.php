<?php
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) redirect(home_for($user));

$sent = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $phone = normalize_phone($_POST['phone'] ?? '');
    if (!$phone) {
        $error = 'Enter the mobile number you signed up with, e.g. 09171234567.';
    } else {
        // At most 3 requests per number per day.
        $stmt = db()->prepare('SELECT COUNT(*) FROM password_reset_requests WHERE phone = ? AND created_at > NOW() - INTERVAL 1 DAY');
        $stmt->execute([$phone]);
        if ((int) $stmt->fetchColumn() < 3) {
            $stmt = db()->prepare('SELECT id FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
            db()->prepare('INSERT INTO password_reset_requests (user_id, phone, note) VALUES (?, ?, ?)')
                ->execute([$stmt->fetchColumn() ?: null, $phone, mb_substr(trim($_POST['note'] ?? ''), 0, 255) ?: null]);
        }
        // Same answer whether or not the number has an account.
        $sent = true;
    }
}

$pageTitle = 'Forgot password';
require __DIR__ . '/includes/header.php';
?>
<main class="min-h-screen md:flex">
  <?php require __DIR__ . '/includes/auth_aside.php'; ?>
  <section class="flex min-h-screen flex-col bg-white md:w-1/2 md:items-center md:justify-center">
    <div class="hero-curve relative overflow-hidden bg-brand-500 px-6 pb-10 pt-10 text-white md:hidden">
      <?= logo_full('absolute -bottom-1 right-5 h-28 drop-shadow-[0_6px_10px_rgba(0,0,0,0.25)]') ?>
      <a href="<?= url('login.php') ?>" class="inline-flex items-center gap-2 text-sm text-white/85">
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg> Back
      </a>
      <h1 class="mt-5 text-2xl font-bold">Forgot password</h1>
      <p class="text-sm text-white/85">We'll help you get back in.</p>
    </div>

    <div class="w-full px-6 py-8 md:max-w-md">
      <h1 class="hidden text-3xl font-bold md:block">Forgot password</h1>
      <?php if ($sent): ?>
        <div class="rounded-2xl bg-green-50 p-5 text-green-800 ring-1 ring-green-200 md:mt-6">
          <p class="font-semibold">Request sent</p>
          <p class="mt-1 text-sm">If that number has a Vulcatic account, our team will call or text you with a temporary password,
            usually within a day. You'll choose a new password when you log in.</p>
        </div>
        <a href="<?= url('login.php') ?>" class="mt-6 block rounded-xl bg-brand-500 py-3 text-center font-semibold text-white hover:bg-brand-600">Back to log in</a>
      <?php else: ?>
        <p class="mb-6 hidden text-gray-500 md:block">We'll help you get back in.</p>
        <p class="mb-5 text-sm text-gray-600">Enter your mobile number. A Vulcatic admin will call or text you a temporary password.</p>
        <?php if ($error): ?><p class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="space-y-4" data-once novalidate>
          <?= csrf_field() ?>
          <div>
            <label for="phone" class="mb-1 block text-sm font-medium">Mobile number</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= old('phone') ?>" placeholder="09XX XXX XXXX" autocomplete="tel"
                   class="w-full rounded-xl border border-gray-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
          </div>
          <div>
            <label for="note" class="mb-1 block text-sm font-medium">Best time to reach you <span class="font-normal text-gray-400">(optional)</span></label>
            <input id="note" name="note" maxlength="255" placeholder="e.g. after 5 PM, or text only" value="<?= old('note') ?>"
                   class="w-full rounded-xl border border-gray-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
          </div>
          <button class="w-full rounded-xl bg-brand-500 py-3 font-semibold text-white shadow-md shadow-brand-500/30 hover:bg-brand-600">Send request</button>
        </form>
        <p class="mt-6 text-center text-sm text-gray-500">Remembered it? <a href="<?= url('login.php') ?>" class="font-semibold text-brand-600 hover:underline">Log in</a></p>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
