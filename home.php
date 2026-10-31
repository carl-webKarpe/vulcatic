<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('customer');

// Already waiting for help? Go straight to tracking.
if ($jobId = active_job_id_for_customer($user['id'])) {
    redirect('job.php?id=' . $jobId);
}

$services = db()->query('SELECT id, name, description, base_price FROM services WHERE is_active = 1 ORDER BY id')->fetchAll();
$vehicles = ['motorcycle' => 'Motorcycle', 'car' => 'Car', 'tricycle' => 'Tricycle', 'bicycle' => 'Bicycle', 'van' => 'Van', 'truck' => 'Truck'];

$greeting  = 'Hi! <span class="text-brand-500">' . e(first_name($user['full_name'])) . '</span>';
$subline   = 'Need help with a flat tire?';
$pageTitle = 'Home';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto max-w-6xl md:grid md:grid-cols-[1fr_420px] md:gap-6 md:px-8 md:py-6">
  <!-- Map -->
  <section class="relative h-[42vh] md:h-[calc(100vh-8.5rem)] md:overflow-hidden md:rounded-3xl md:ring-1 md:ring-black/5">
    <div id="map" class="h-full w-full"></div>
    <button id="locate-btn" type="button"
            class="absolute left-3 top-3 z-[500] flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold shadow-lg ring-1 ring-black/5 hover:bg-gray-50">
      <svg viewBox="0 0 24 24" class="h-4 w-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
      Use my location
    </button>
    <p id="map-hint" class="pointer-events-none absolute inset-x-3 bottom-3 z-[500] mx-auto w-fit rounded-full bg-ink-900/85 px-3 py-1.5 text-xs font-medium text-white">
      Drag the orange pin or tap the map to set where you are
    </p>
  </section>

  <!-- Request panel -->
  <section class="space-y-4 px-5 pb-48 pt-5 md:h-[calc(100vh-8.5rem)] md:overflow-y-auto md:px-0 md:pb-6 md:pt-0">
    <form id="request-form" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5" novalidate>
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">Request help</h2>
          <p class="text-sm text-gray-500">A nearby vulcator on Siargao comes to you.</p>
        </div>
        <span id="online-count" class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Looking…</span>
      </div>

      <fieldset class="mt-5">
        <legend class="mb-2 text-sm font-semibold">What do you need?</legend>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach ($services as $i => $s): ?>
            <label class="role-card flex cursor-pointer flex-col rounded-xl border-2 border-gray-200 p-3 transition">
              <input type="radio" name="service_id" value="<?= (int) $s['id'] ?>" class="sr-only" <?= $i === 0 ? 'checked' : '' ?>>
              <span class="text-sm font-semibold leading-tight"><?= e($s['name']) ?></span>
              <span class="mt-1 text-xs text-gray-500">from <?= e(peso((float) $s['base_price'])) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="mt-5">
        <legend class="mb-2 text-sm font-semibold">Your vehicle</legend>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($vehicles as $value => $label): ?>
            <label class="role-card cursor-pointer rounded-full border-2 border-gray-200 px-3.5 py-1.5 text-sm font-medium transition">
              <input type="radio" name="vehicle_type" value="<?= $value ?>" class="sr-only" <?= $value === 'motorcycle' ? 'checked' : '' ?>>
              <?= $label ?>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="mt-5 space-y-3">
        <div>
          <label for="address" class="mb-1 block text-sm font-semibold">Landmark <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="address" name="address" maxlength="255" placeholder="e.g. Tourism Road, near Cloud 9"
                 class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        </div>
        <div>
          <label for="notes" class="mb-1 block text-sm font-semibold">Notes for the vulcator <span class="font-normal text-gray-400">(optional)</span></label>
          <textarea id="notes" name="notes" rows="2" maxlength="500" placeholder="e.g. Rear tire, I'm beside the sari-sari store"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>
        </div>
      </div>

      <p id="area-warning" class="mt-4 hidden rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
        Vulcatic currently serves <b>Siargao Island</b> only. Move the pin to where you are on Siargao.
      </p>
      <p id="request-error" class="mt-4 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200"></p>
      <button id="request-btn" type="submit"
              class="btn-shine mt-5 hidden w-full md:flex items-center justify-center gap-2 rounded-2xl bg-brand-500 py-3.5 font-semibold text-white shadow-lg shadow-brand-500/30 transition hover:bg-brand-600 disabled:opacity-60">
        Find a vulcator
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </button>
    </form>

    <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5">
      <div class="flex items-center justify-between">
        <h2 class="font-bold">Nearby</h2>
        <span class="flex gap-3 text-[11px] font-medium text-gray-500">
          <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-ink-900"></span>Vulcator</span>
          <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-ok-600"></span>Shop</span>
        </span>
      </div>
      <ul id="nearby-list" class="mt-3 divide-y divide-gray-100 text-sm">
        <li class="py-6 text-center text-gray-400">Set your location to see who's near you.</li>
      </ul>
      <a href="<?= url('shops.php') ?>" class="mt-3 flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 text-sm font-semibold hover:bg-gray-100">
        <span>No signal later? All shops are saved for offline use.</span><span class="text-brand-600">Open →</span>
      </a>
    </div>
  </section>
</main>
<!-- Phones: the main action stays in reach above the bottom bar -->
<div class="fixed inset-x-0 bottom-[calc(3.6rem+env(safe-area-inset-bottom))] z-30 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur md:hidden">
  <div class="flex items-center gap-3">
    <div class="min-w-0 flex-1">
      <p id="sticky-service" class="truncate text-sm font-bold">Flat tire repair</p>
      <p id="sticky-meta" class="truncate text-xs text-gray-500">Motorcycle · from ₱150.00</p>
    </div>
    <button type="submit" form="request-form" id="sticky-btn"
            class="btn-shine shrink-0 rounded-2xl bg-brand-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-600 disabled:opacity-60">
      Find a vulcator →
    </button>
  </div>
</div>
<?php
$activeTab   = 'home';
$pageScripts = ['home.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
