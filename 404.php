<?php
// Shown for any address that doesn't exist (see .htaccess).
require __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$user = current_user();
$pageTitle = 'Page not found';
require __DIR__ . '/includes/header.php';
?>
<main class="grid min-h-screen place-items-center bg-brand-500 px-6 text-center text-white">
  <div>
    <?= logo_full('gecko-pop mx-auto h-48 drop-shadow-[0_10px_16px_rgba(0,0,0,0.25)]') ?>
    <p class="mt-6 text-sm font-bold uppercase tracking-widest text-white/70">404</p>
    <h1 class="mt-1 text-3xl font-bold">Lost on the road?</h1>
    <p class="mx-auto mt-2 max-w-sm text-white/85">This page doesn't exist. Let's get you back on track.</p>
    <a href="<?= url($user ? home_for($user) : 'index.php') ?>"
       class="mt-8 inline-block rounded-2xl bg-white px-7 py-3.5 font-semibold text-brand-600 shadow-lg hover:bg-brand-50">
      <?= $user ? 'Back to Vulcatic' : 'Go to the home page' ?>
    </a>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
