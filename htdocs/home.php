<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('customer');

$greeting  = 'Hi! <span class="text-brand-500">' . e(first_name($user['full_name'])) . '</span>';
$subline   = 'Need help with a flat tire?';
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto max-w-5xl px-5 pb-28 pt-5 md:px-8 md:pb-10">
  <div class="grid gap-5 md:grid-cols-5">
    <!-- Map goes here in the next step (Leaflet + OpenStreetMap) -->
    <section class="map-pattern relative grid min-h-[50vh] place-items-center overflow-hidden rounded-2xl ring-1 ring-black/5 md:col-span-3 md:min-h-[60vh]">
      <div class="text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-500 text-white shadow-lg shadow-brand-500/40">
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
        </span>
        <p class="mt-3 text-sm font-medium text-gray-600">Live map coming in the next step</p>
      </div>
    </section>

    <section class="space-y-4 md:col-span-2">
      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <h2 class="font-semibold">Request help</h2>
        <p class="mt-1 text-sm text-gray-500">A nearby vulcator comes to your location.</p>
        <button disabled class="mt-4 w-full cursor-not-allowed rounded-xl bg-brand-500/60 py-3 font-semibold text-white">
          Find a vulcator (coming soon)
        </button>
      </div>
      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <h2 class="font-semibold">No signal?</h2>
        <p class="mt-1 text-sm text-gray-500">We'll save nearby shops on your phone so you can walk to the closest one even offline.</p>
      </div>
    </section>
  </div>
</main>
<?php
$activeTab = 'home';
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
