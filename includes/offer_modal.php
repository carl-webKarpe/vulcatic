<?php // New request popup for shop owners / skilled locals. Filled by assets/js/dashboard.js. ?>
<!-- New job request popup (filled by dashboard.js) -->
<div id="offer-modal" class="fixed inset-0 z-[1000] hidden items-end justify-center bg-ink-900/60 p-3 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="offer-title">
  <div class="sheet-enter w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
    <div class="h-44 w-full" id="offer-map"></div>
    <div class="p-5">
      <div class="flex items-center justify-between">
        <p id="offer-title" class="flex items-center gap-2 font-bold text-brand-600">
          <span class="relative flex h-2.5 w-2.5"><span class="absolute h-full w-full animate-ping rounded-full bg-brand-500"></span><span class="relative h-2.5 w-2.5 rounded-full bg-brand-500"></span></span>
          New job request nearby!
        </p>
        <span id="offer-timer" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">60s</span>
      </div>
      <div id="offer-body" class="mt-4"></div>
      <p id="offer-error" class="mt-3 hidden text-sm text-red-600"></p>
      <div class="mt-5 grid grid-cols-2 gap-3">
        <button id="offer-decline" class="rounded-2xl py-3.5 font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Decline</button>
        <button id="offer-accept" class="rounded-2xl bg-ok-500 py-3.5 font-semibold text-white shadow-lg shadow-green-500/30 hover:bg-ok-600">Accept</button>
      </div>
    </div>
  </div>
</div>
