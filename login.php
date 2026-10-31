<?php
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(home_for($user));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Log in with either a phone number or an email address.
    $phone = normalize_phone($login);
    $stmt = db()->prepare('SELECT * FROM users WHERE (phone = ? OR email = ?) AND is_active = 1 LIMIT 1');
    $stmt->execute([$phone ?? '', $login]);
    $found = $stmt->fetch();

    if ($found && password_verify($password, $found['password_hash'])) {
        login_user($found);
        flash('success', 'Welcome back, ' . explode(' ', $found['full_name'])[0] . '!');
        redirect(home_for($found));
    }
    $error = 'Wrong phone/email or password.';
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<main class="min-h-screen md:flex">
  <?php require __DIR__ . '/includes/auth_aside.php'; ?>

  <section class="flex min-h-screen flex-col bg-white md:w-1/2 md:items-center md:justify-center">
    <div class="hero-curve bg-brand-500 px-6 pb-10 pt-10 text-white md:hidden">
      <a href="<?= url('index.php') ?>" class="inline-flex items-center gap-2 text-sm text-white/85">
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg> Back
      </a>
      <h1 class="mt-5 text-2xl font-bold">Log in</h1>
      <p class="text-sm text-white/85">Good to see you again.</p>
    </div>

    <div class="w-full px-6 py-8 md:max-w-md">
      <h1 class="hidden text-3xl font-bold md:block">Log in</h1>
      <p class="mb-6 hidden text-gray-500 md:block">Good to see you again.</p>

      <?php if ($error): ?>
        <p class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" class="space-y-4" data-once novalidate>
        <?= csrf_field() ?>
        <div>
          <label for="login" class="mb-1 block text-sm font-medium">Phone number or email</label>
          <input id="login" name="login" value="<?= old('login') ?>" required autocomplete="username"
                 placeholder="09XX XXX XXXX"
                 class="w-full rounded-xl border border-gray-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        </div>
        <div>
          <label for="password" class="mb-1 block text-sm font-medium">Password</label>
          <div class="relative">
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   placeholder="Password"
                   class="w-full rounded-xl border border-gray-300 px-4 py-3 pr-16 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
            <button type="button" data-toggle-password="password"
                    class="absolute inset-y-0 right-0 px-4 text-sm font-medium text-gray-500">Show</button>
          </div>
        </div>
        <button type="submit"
                class="w-full rounded-xl bg-brand-500 py-3 font-semibold text-white shadow-md shadow-brand-500/30 transition hover:bg-brand-600">
          Log in
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500">New to Vulcatic?
        <a href="<?= url('register.php') ?>" class="font-semibold text-brand-600 hover:underline">Create an account</a></p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
