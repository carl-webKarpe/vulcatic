<?php // Temporary password, shown once after "Set temporary password". ?>
<?php if ($temp = $_SESSION['temp_password'] ?? null): ?>
  <section class="rounded-2xl bg-ink-900 p-5 text-white">
    <p class="text-sm text-white/70">Temporary password for <b class="text-white"><?= e($temp['name']) ?></b> · <?= e($temp['phone']) ?></p>
    <p class="mt-2 font-mono text-3xl font-bold tracking-widest text-ok-500"><?= e($temp['password']) ?></p>
    <p class="mt-2 text-sm text-white/70">Call or text it to them now (without the dash is fine). They'll choose a new password when they log in.
      This won't be shown again.</p>
    <div class="mt-4 flex flex-wrap gap-2">
      <a href="sms:<?= e($temp['phone']) ?>?body=<?= rawurlencode('Vulcatic: your temporary password is ' . str_replace('-', '', $temp['password']) . '. Log in and choose a new password.') ?>"
         class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-ink-900">Text it</a>
      <a href="tel:<?= e($temp['phone']) ?>" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold ring-1 ring-white/20">Call</a>
      <a href="<?= admin_url(['view' => $view === 'overview' ? null : $view, 'temp_done' => 1]) ?>" class="rounded-xl px-4 py-2 text-sm font-semibold text-white/70 hover:text-white">Done, close this</a>
    </div>
  </section>
<?php endif; ?>
