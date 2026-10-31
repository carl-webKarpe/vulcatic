<?php
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(home_for($user));
}

$pageTitle = 'Roadside tire help near you';
require __DIR__ . '/includes/header.php';
?>
<main class="min-h-screen md:flex">
  <?php require __DIR__ . '/includes/auth_aside.php'; ?>

  <section class="map-pattern flex min-h-screen flex-col md:w-1/2 md:items-center md:justify-center md:bg-white md:bg-none">
    <div class="hero-curve bg-brand-500 px-6 pb-14 pt-12 text-white md:hidden">
      <img src="<?= url('assets/img/logo.svg') ?>" alt="" class="h-14 w-14 rounded-2xl ring-2 ring-white/40">
      <h1 class="mt-6 text-3xl font-bold leading-tight">Flat tire?<br>Help is nearby.</h1>
      <p class="mt-3 text-sm text-white/85">Vulcanizing shops and skilled locals, ready to come to you.</p>
    </div>

    <div class="-mt-8 flex-1 px-5 pb-10 md:mt-0 md:w-full md:max-w-md md:flex-none">
      <div class="rounded-2xl bg-white p-6 shadow-lg ring-1 ring-black/5 md:shadow-none md:ring-0">
        <h2 class="text-xl font-semibold">Welcome to Vulcatic</h2>
        <p class="mt-1 text-sm text-gray-500">How do you want to use the app?</p>

        <a href="<?= url('register.php?role=customer') ?>"
           class="mt-6 flex items-center gap-4 rounded-xl border border-gray-200 p-4 transition hover:border-brand-500 hover:bg-brand-50">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-600">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
          </span>
          <span><span class="block font-semibold">I need help</span>
                <span class="text-sm text-gray-500">Find a repairer near me</span></span>
        </a>

        <a href="<?= url('register.php?role=provider') ?>"
           class="mt-3 flex items-center gap-4 rounded-xl border border-gray-200 p-4 transition hover:border-brand-500 hover:bg-brand-50">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-ink-900 text-white">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
          </span>
          <span><span class="block font-semibold">I'm a vulcator</span>
                <span class="text-sm text-gray-500">Earn by fixing tires near you</span></span>
        </a>

        <p class="mt-6 text-center text-sm text-gray-500">Already have an account?
          <a href="<?= url('login.php') ?>" class="font-semibold text-brand-600 hover:underline">Log in</a></p>
      </div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
