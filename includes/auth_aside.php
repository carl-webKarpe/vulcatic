<?php // Brand panel beside the login / register / forgot forms on tablet and desktop. ?>
<aside class="auth-aside relative hidden overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-500 via-brand-500 to-brand-700 text-white md:m-3 md:flex md:w-[46%] md:flex-col md:justify-between md:p-10 lg:p-12">
  <div class="auth-dots pointer-events-none absolute inset-0 opacity-40"></div>
  <div class="pointer-events-none absolute -right-28 -top-28 h-96 w-96 rounded-full bg-white/10 blur-2xl"></div>

  <a href="<?= url('index.php') ?>" class="relative flex w-fit items-center gap-2 text-lg font-bold tracking-tight">
    <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/15 ring-1 ring-white/25"><?= logo_img('h-7 w-7') ?></span> Vulcatic
  </a>

  <!-- Gecko with two floating facts -->
  <div class="relative mx-auto grid w-full max-w-sm place-items-center py-6">
    <span class="absolute bottom-6 h-6 w-48 rounded-[50%] bg-black/20 blur-md"></span>
    <?= logo_full('auth-gecko relative h-64 lg:h-72 drop-shadow-[0_18px_24px_rgba(0,0,0,0.25)]') ?>
    <div class="auth-chip absolute left-0 top-10 flex items-center gap-2 rounded-2xl bg-white px-3 py-2 text-xs font-semibold text-ink-900 shadow-xl">
      <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-50 text-brand-600">⚡</span> Help arrives fast
    </div>
    <div class="auth-chip auth-chip-2 absolute bottom-16 right-0 flex items-center gap-2 rounded-2xl bg-white px-3 py-2 text-xs font-semibold text-ink-900 shadow-xl">
      <span class="grid h-7 w-7 place-items-center rounded-full bg-green-50 text-ok-600">✓</span> Verified vulcators
    </div>
  </div>

  <div class="relative">
    <h2 class="text-3xl font-bold leading-tight lg:text-4xl">Flat tire?<br>Help is nearby.</h2>
    <p class="mt-3 max-w-sm text-sm text-white/80">Shops and skilled locals across Siargao, even when you're offline.</p>
  </div>
</aside>
