<?php
// Motorist home: the 5-step roadside assistance request.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('customer');

// Already have a request going? Show it.
if ($jobId = active_job_id_for_customer($user['id'])) {
    redirect('job.php?id=' . $jobId);
}

$prices = db()->query('SELECT id, base_price FROM services')->fetchAll(PDO::FETCH_KEY_PAIR);
$problems = [];
foreach (PROBLEMS as $key => [$label, $sid]) $problems[$key] = ['label' => $label, 'service_id' => $sid, 'from' => (float) ($prices[$sid] ?? 0)];

$greeting  = 'Hi! <span class="text-brand-500">' . e(first_name($user['full_name'])) . '</span>';
$subline   = 'Tire trouble? Let\'s get you help.';
$pageTitle = 'Request help';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';

$card = 'wiz-card flex cursor-pointer items-center gap-4 rounded-2xl border-2 border-gray-200 bg-white p-4 transition hover:border-brand-200';
$icon = fn ($path) => '<span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-50 text-brand-600"><svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' . $path . '"/></svg></span>';
?>
<main id="wizard" data-problems='<?= e(json_encode($problems)) ?>' class="mx-auto max-w-6xl md:grid md:grid-cols-[1fr_440px] md:gap-6 md:px-8 md:py-6">
  <!-- Map (used in step 2 and to show nearby help) -->
  <section class="relative isolate h-[34vh] md:h-[calc(100vh-8.5rem)] md:overflow-hidden md:rounded-3xl md:ring-1 md:ring-black/5">
    <div id="map" class="h-full w-full"></div>
    <button id="locate-btn" type="button" class="absolute left-3 top-3 z-[500] flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold shadow-lg ring-1 ring-black/5 hover:bg-gray-50">
      <svg viewBox="0 0 24 24" class="h-4 w-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
      Use my GPS location
    </button>
    <p id="map-hint" class="pointer-events-none absolute inset-x-3 bottom-3 z-[500] mx-auto w-fit rounded-full bg-ink-900/85 px-3 py-1.5 text-xs font-medium text-white">
      Drag the orange pin or tap the map to set where you are
    </p>
  </section>

  <section class="px-5 pb-48 pt-5 md:h-[calc(100vh-8.5rem)] md:overflow-y-auto md:px-0 md:pb-6 md:pt-0">
    <form id="request-form" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-black/5" novalidate>
      <!-- Progress -->
      <ol class="mb-5 grid grid-cols-5 gap-1.5" aria-label="Steps">
        <?php foreach (['Vehicle', 'Location', 'Problem', 'Help', 'Review'] as $i => $label): ?>
          <li class="wiz-step text-center" data-step-dot="<?= $i + 1 ?>">
            <span class="block h-1.5 rounded-full bg-gray-200"></span>
            <span class="mt-1.5 block text-[11px] font-semibold text-gray-400"><?= $label ?></span>
          </li>
        <?php endforeach; ?>
      </ol>

      <!-- Step 1: vehicle -->
      <fieldset data-step="1" class="wiz-panel">
        <legend class="text-lg font-bold">What are you riding?</legend>
        <p class="mb-4 text-sm text-gray-500">So the vulcator brings the right tools.</p>
        <div class="space-y-3">
          <label class="<?= $card ?>"><input type="radio" name="vehicle_group" value="motorcycle" class="sr-only" checked>
            <?= $icon('M5 17a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm14 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM8 14l3-6h4l2 3h2M11 8 9 5H7') ?>
            <span class="flex-1"><span class="block font-semibold">Motorcycle</span><span class="text-sm text-gray-500">Scooter, motorbike, rental bike</span></span><span class="wiz-dot"></span></label>
          <label class="<?= $card ?>"><input type="radio" name="vehicle_group" value="car" class="sr-only">
            <?= $icon('M5 17h14M5 17a2 2 0 1 1-4 0v-4l2-5h14l3 5v4a2 2 0 1 1-4 0M7 17a2 2 0 1 0 4 0m2 0a2 2 0 1 0 4 0M4 12h16') ?>
            <span class="flex-1"><span class="block font-semibold">Car</span><span class="text-sm text-gray-500">Sedan, SUV, pickup</span></span><span class="wiz-dot"></span></label>
          <label class="<?= $card ?>"><input type="radio" name="vehicle_group" value="other" class="sr-only">
            <?= $icon('M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z') ?>
            <span class="flex-1"><span class="block font-semibold">Other vehicle</span><span class="text-sm text-gray-500">Tricycle, van, truck, bicycle</span></span><span class="wiz-dot"></span></label>
        </div>
        <div id="other-vehicle" class="mt-3 hidden flex-wrap gap-2">
          <?php foreach (['tricycle' => 'Tricycle', 'van' => 'Van', 'truck' => 'Truck', 'bicycle' => 'Bicycle', 'other' => 'Something else'] as $v => $t): ?>
            <label class="role-card cursor-pointer rounded-full border-2 border-gray-200 px-3.5 py-1.5 text-sm font-medium">
              <input type="radio" name="vehicle_other" value="<?= $v ?>" class="sr-only" <?= $v === 'tricycle' ? 'checked' : '' ?>><?= $t ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <!-- Step 2: location -->
      <fieldset data-step="2" class="wiz-panel hidden">
        <legend class="text-lg font-bold">Where are you?</legend>
        <p class="mb-4 text-sm text-gray-500">Use GPS, or tap the map / drag the orange pin to your exact spot.</p>
        <button type="button" data-locate class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-brand-500 py-3 font-semibold text-brand-600 hover:bg-brand-50">
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg> Detect my location (GPS)
        </button>
        <p id="loc-status" class="mt-2 rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600">Location not set yet.</p>
        <p id="area-warning" class="mt-2 hidden rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
          Vulcatic currently serves <b>Siargao Island</b> only. Move the pin to where you are on Siargao.</p>
        <label class="mt-4 block text-sm font-semibold" for="address">Landmark <span class="font-normal text-gray-400">(recommended)</span></label>
        <input id="address" name="address" maxlength="255" placeholder="e.g. Tourism Road, near Cloud 9"
               class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        <label class="mt-3 block text-sm font-semibold" for="directions">More directions <span class="font-normal text-gray-400">(optional)</span></label>
        <textarea id="directions" name="directions" rows="2" maxlength="255" placeholder="e.g. Beside the sari-sari store, I'm wearing a red shirt"
                  class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>
      </fieldset>

      <!-- Step 3: problem -->
      <fieldset data-step="3" class="wiz-panel hidden">
        <legend class="text-lg font-bold">What happened?</legend>
        <p class="mb-4 text-sm text-gray-500">Pick the closest one.</p>
        <div class="space-y-3">
          <?php foreach ($problems as $key => $p): ?>
            <label class="<?= $card ?>"><input type="radio" name="problem" value="<?= $key ?>" class="sr-only" <?= $key === 'flat_tire' ? 'checked' : '' ?>>
              <?= $icon(['flat_tire' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM3 21h18', 'puncture' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM17 3l-2 4', 'other' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z'][$key]) ?>
              <span class="flex-1"><span class="block font-semibold"><?= e($p['label']) ?></span>
                <span class="text-sm text-gray-500"><?= ['flat_tire' => 'Tire is flat or losing air', 'puncture' => 'Nail, screw or hole in the tire', 'other' => 'Valve, rim, bead or something else'][$key] ?> · from <?= e(peso($p['from'])) ?></span></span>
              <span class="wiz-dot"></span></label>
          <?php endforeach; ?>
        </div>
        <label class="mt-4 block text-sm font-semibold" for="details">Anything else? <span class="font-normal text-gray-400">(optional)</span></label>
        <textarea id="details" name="details" rows="2" maxlength="500" placeholder="e.g. Rear tire, I have a spare"
                  class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>
      </fieldset>

      <!-- Step 4: assistance -->
      <fieldset data-step="4" class="wiz-panel hidden">
        <legend class="text-lg font-bold">How do you want help?</legend>
        <p class="mb-4 text-sm text-gray-500">Choose what works for you.</p>
        <div class="space-y-3">
          <label class="<?= $card ?>"><input type="radio" name="mode" value="onsite" class="sr-only" checked>
            <?= $icon('M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z') ?>
            <span class="flex-1"><span class="block font-semibold">Request roadside repair</span>
              <span id="onsite-sub" class="text-sm text-gray-500">A vulcator comes to you</span></span><span class="wiz-dot"></span></label>
          <label class="<?= $card ?>"><input type="radio" name="mode" value="walk_in" class="sr-only">
            <?= $icon('M4 10l8-6 8 6v10H4z M9 20v-6h6v6') ?>
            <span class="flex-1"><span class="block font-semibold">Find a shop to visit</span>
              <span id="walkin-sub" class="text-sm text-gray-500">Ride or walk to a vulcanizing shop</span></span><span class="wiz-dot"></span></label>
        </div>
        <p id="onsite-warning" class="mt-3 hidden rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
          No vulcator is online near you right now. You can still send the request (we keep trying), or pick a shop to visit.</p>
        <div id="shop-picker" class="mt-4 hidden">
          <p class="mb-2 text-sm font-semibold">Pick a shop</p>
          <ul id="shop-list" class="space-y-2"></ul>
        </div>
      </fieldset>

      <!-- Step 5: review -->
      <fieldset data-step="5" class="wiz-panel hidden">
        <legend class="text-lg font-bold">Check and send</legend>
        <p class="mb-4 text-sm text-gray-500">Make sure everything is right.</p>
        <dl id="review" class="divide-y divide-gray-100 rounded-2xl bg-gray-50 px-4 text-sm"></dl>
        <p class="mt-3 text-xs text-gray-500">You pay after the repair, in cash or by GCash. The final price is confirmed by the shop or vulcator.</p>
      </fieldset>

      <p id="request-error" class="mt-4 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200" role="alert"></p>

      <div class="mt-6 hidden gap-3 md:flex">
        <button type="button" data-back class="rounded-2xl px-5 py-3 font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">Back</button>
        <button type="button" data-next class="btn-shine flex-1 rounded-2xl bg-brand-500 py-3 font-semibold text-white shadow-lg shadow-brand-500/30 hover:bg-brand-600 disabled:opacity-60">Next</button>
      </div>
    </form>

    <a href="<?= url('shops.php') ?>" class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 text-sm font-semibold shadow-sm ring-1 ring-black/5 hover:bg-gray-50">
      <span>No signal later? All shops are saved for offline use.</span><span class="shrink-0 text-brand-600">Open →</span>
    </a>
  </section>
</main>

<!-- Phones: Back / Next stay in reach above the bottom tabs -->
<div class="fixed inset-x-0 bottom-[calc(3.6rem+env(safe-area-inset-bottom))] z-30 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur md:hidden">
  <div class="flex items-center gap-3">
    <button type="button" data-back class="rounded-2xl px-4 py-3 text-sm font-semibold text-gray-600 ring-1 ring-gray-200">Back</button>
    <div class="min-w-0 flex-1"><p id="sticky-step" class="truncate text-xs font-semibold text-gray-500">Step 1 of 5</p>
      <p id="sticky-summary" class="truncate text-sm font-bold">Motorcycle</p></div>
    <button type="button" data-next id="sticky-btn" class="btn-shine shrink-0 rounded-2xl bg-brand-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 disabled:opacity-60">Next →</button>
  </div>
</div>
<?php
$activeTab   = 'home';
$pageScripts = ['home.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
