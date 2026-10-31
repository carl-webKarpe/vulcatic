<?php // Brand panel shown beside the login/register forms on tablet and desktop. ?>
<aside class="relative hidden overflow-hidden bg-brand-500 text-white md:flex md:w-1/2 md:flex-col md:justify-between md:p-12">
  <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
  <div class="absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-black/10"></div>
  <a href="<?= url('index.php') ?>" class="relative flex items-center gap-3">
    <img src="<?= url('assets/img/logo.svg') ?>" alt="" class="h-11 w-11 rounded-xl ring-2 ring-white/40">
    <span class="text-2xl font-bold tracking-tight">Vulcatic</span>
  </a>
  <div class="relative max-w-md">
    <h2 class="text-4xl font-bold leading-tight">Flat tire?<br>Help is nearby.</h2>
    <p class="mt-4 text-white/85">Find vulcanizing shops and skilled locals around you. Request on-site repair,
       or walk to the nearest shop even when you're offline.</p>
  </div>
  <p class="relative text-sm text-white/70">For travelers, tourists and locals.</p>
</aside>
