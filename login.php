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
    $key   = $phone ?? mb_strtolower($login);
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

    // Slow down password guessing: 5 wrong tries per account or 20 per device
    // in 15 minutes locks login for 15 minutes.
    $stmt = db()->prepare('SELECT
            (SELECT COUNT(*) FROM login_attempts WHERE login = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE),
            (SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE)');
    $stmt->execute([$key, $ip]);
    [$byLogin, $byIp] = $stmt->fetch(PDO::FETCH_NUM);

    if ($byLogin >= 5 || $byIp >= 20) {
        $error = 'Too many wrong tries. Please wait 15 minutes, or tap "Forgot password?".';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE (phone = ? OR email = ?) AND is_active = 1 LIMIT 1');
        $stmt->execute([$phone ?? '', $login]);
        $found = $stmt->fetch();

        if ($found && password_verify($password, $found['password_hash'])) {
            db()->prepare('DELETE FROM login_attempts WHERE login = ?')->execute([$key]);
            login_user($found);
            if ($found['must_change_password']) redirect('change_password.php');
            flash('success', 'Welcome back, ' . explode(' ', $found['full_name'])[0] . '!');
            redirect(home_for($found));
        }
        db()->prepare('INSERT INTO login_attempts (login, ip) VALUES (?, ?)')->execute([$key, $ip]);
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        $left = 4 - $byLogin;
        $error = 'Wrong phone/email or password.' . ($left <= 2 && $left > 0 ? " $left tr" . ($left === 1 ? 'y' : 'ies') . ' left before a 15-minute wait.' : '');
    }
}

$pageTitle = 'Log in';
$bodyClass = '!bg-white';
require __DIR__ . '/includes/header.php';
$hasError = (bool) $error;
?>
<main class="flex min-h-screen md:p-0">
  <?php require __DIR__ . '/includes/auth_aside.php'; ?>

  <section class="flex min-h-screen flex-1 flex-col px-6 py-6 md:px-10">
    <!-- Top bar: back + sign-up link -->
    <div class="flex items-center justify-between">
      <a href="<?= url('index.php') ?>" class="group inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-900">
        <span class="grid h-9 w-9 place-items-center rounded-full ring-1 ring-gray-200 transition group-hover:-translate-x-0.5 group-hover:ring-gray-300">
          <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></span>
        <span class="md:hidden">Back</span><span class="hidden md:inline">Back to home</span>
      </a>
      <p class="text-sm text-gray-500">New here? <a href="<?= url('register.php') ?>" class="font-semibold text-gray-900 underline decoration-brand-500 decoration-2 underline-offset-4 hover:text-brand-600">Sign up</a></p>
    </div>

    <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
      <?= logo_full('auth-wave mb-6 h-16 self-start md:hidden') ?>
      <h1 class="text-3xl font-bold tracking-tight">Welcome back</h1>
      <p class="mt-1.5 text-gray-500">Log in to get help or manage your jobs.</p>

      <form id="login-form" method="post" class="mt-8 space-y-5 <?= $hasError ? 'shake' : '' ?>" novalidate>
        <?= csrf_field() ?>

        <?php if ($error): ?>
          <div class="flex items-start gap-3 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <div>
          <label for="login" class="mb-1.5 block text-sm font-medium text-gray-700">Phone number or email</label>
          <div class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg id="icon-phone" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>
              <svg id="icon-mail" viewBox="0 0 24 24" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 6.5 12 13l8.5-6.5"/></svg>
            </span>
            <input id="login" name="login" value="<?= old('login') ?>" required autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false"
                   placeholder="09XX XXX XXXX" aria-describedby="login-error" class="auth-input">
          </div>
          <p id="login-error" class="mt-1.5 hidden text-sm text-red-600"></p>
        </div>

        <div>
          <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">Password</label>
          <div class="auth-field">
            <span class="auth-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
            </span>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Your password"
                   aria-describedby="password-error caps-warning" class="auth-input pr-12">
            <button type="button" id="toggle-password" aria-label="Show password" aria-pressed="false"
                    class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-2xl text-gray-400 transition hover:text-gray-700">
              <svg id="eye-open" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eye-closed" viewBox="0 0 24 24" class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.6A9.8 9.8 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.8M6.3 6.4C3.9 8 2.5 12 2.5 12S6 18.5 12 18.5a9.6 9.6 0 0 0 4-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
          </div>
          <!-- Messages on the left, "Forgot password?" on the right, under the field -->
          <div class="mt-2 flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p id="password-error" class="hidden text-sm text-red-600"></p>
              <p id="caps-warning" class="hidden items-center gap-1.5 text-sm text-amber-600">⇪ Caps Lock is on</p>
            </div>
            <a href="<?= url('forgot.php') ?>" class="shrink-0 text-sm font-medium text-gray-500 hover:text-brand-600">Forgot password?</a>
          </div>
        </div>

        <button type="submit" id="login-btn" class="auth-btn group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-ink-900 py-3.5 font-semibold text-white transition hover:bg-black active:scale-[.99] disabled:cursor-wait">
          <span class="btn-label">Log in</span>
          <svg class="btn-arrow h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          <svg class="btn-spinner hidden h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        </button>
      </form>

      <div class="my-8 flex items-center gap-3 text-xs text-gray-400"><span class="h-px flex-1 bg-gray-200"></span>New to Vulcatic?<span class="h-px flex-1 bg-gray-200"></span></div>

      <div class="grid grid-cols-2 gap-3">
        <a href="<?= url('register.php?role=customer') ?>" class="group rounded-2xl p-3.5 ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:ring-brand-300 hover:shadow-md">
          <span class="block text-lg">🛵</span><span class="mt-1 block text-sm font-semibold">I need help</span><span class="text-xs text-gray-500">Motorist account</span></a>
        <a href="<?= url('register.php?role=provider') ?>" class="group rounded-2xl p-3.5 ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:ring-brand-300 hover:shadow-md">
          <span class="block text-lg">🔧</span><span class="mt-1 block text-sm font-semibold">I fix tires</span><span class="text-xs text-gray-500">Shop or vulcator</span></a>
      </div>
    </div>

    <p class="text-center text-xs text-gray-400">
      <a href="<?= url('terms.php') ?>" class="hover:text-gray-600">Terms</a> · <a href="<?= url('privacy.php') ?>" class="hover:text-gray-600">Privacy</a> · Serving Siargao Island
    </p>
  </section>
</main>

<script>
  (() => {
    const form = document.getElementById('login-form');
    const login = document.getElementById('login');
    const pass = document.getElementById('password');
    const btn = document.getElementById('login-btn');
    const show = (id, msg) => { const el = document.getElementById(id); el.textContent = msg || ''; el.classList.toggle('hidden', !msg); };

    // Phone icon becomes an envelope when typing an email.
    const syncIcon = () => {
      const email = login.value.includes('@') || /[a-z]/i.test(login.value);
      document.getElementById('icon-phone').classList.toggle('hidden', email);
      document.getElementById('icon-mail').classList.toggle('hidden', !email);
    };
    login.addEventListener('input', () => { syncIcon(); show('login-error'); login.closest('.auth-field').classList.remove('is-invalid'); });
    pass.addEventListener('input', () => { show('password-error'); pass.closest('.auth-field').classList.remove('is-invalid'); });
    syncIcon();

    // Show / hide password
    document.getElementById('toggle-password').addEventListener('click', (e) => {
      const visible = pass.type === 'password';
      pass.type = visible ? 'text' : 'password';
      e.currentTarget.setAttribute('aria-pressed', String(visible));
      e.currentTarget.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      document.getElementById('eye-open').classList.toggle('hidden', visible);
      document.getElementById('eye-closed').classList.toggle('hidden', !visible);
      pass.focus();
    });

    // Caps Lock warning
    const caps = document.getElementById('caps-warning');
    ['keydown', 'keyup'].forEach((t) => pass.addEventListener(t, (e) => {
      const on = e.getModifierState && e.getModifierState('CapsLock');
      caps.classList.toggle('hidden', !on); caps.classList.toggle('flex', on);
    }));

    // Check before sending, then show a spinner (no double taps).
    form.addEventListener('submit', (e) => {
      let ok = true;
      const v = login.value.trim();
      const phoneOk = /^(\+?63|0)9\d{9}$/.test(v.replace(/[\s-]/g, ''));
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
      if (!v) { show('login-error', 'Enter your phone number or email.'); ok = false; }
      else if (!phoneOk && !emailOk) { show('login-error', 'That doesn\'t look like a PH mobile number (09XX XXX XXXX) or an email.'); ok = false; }
      if (!pass.value) { show('password-error', 'Enter your password.'); ok = false; }
      if (!ok) {
        e.preventDefault();
        form.classList.remove('shake'); void form.offsetWidth; form.classList.add('shake');
        (document.querySelector('#login-error:not(.hidden)') ? login : pass).focus();
        login.closest('.auth-field').classList.toggle('is-invalid', !document.getElementById('login-error').classList.contains('hidden'));
        pass.closest('.auth-field').classList.toggle('is-invalid', !document.getElementById('password-error').classList.contains('hidden'));
        return;
      }
      btn.disabled = true;
      btn.querySelector('.btn-label').textContent = 'Logging in…';
      btn.querySelector('.btn-arrow').classList.add('hidden');
      btn.querySelector('.btn-spinner').classList.remove('hidden');
    });

    (<?= $hasError ? 'pass' : 'login' ?>).focus();
  })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
