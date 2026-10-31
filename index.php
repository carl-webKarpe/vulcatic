<?php
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(home_for($user));
}

// Runs before the page paints: turns on JS-only styles and skips the intro
// if it already played this visit or the user prefers less motion.
$headExtra = <<<'HTML'
<script>
  (function () {
    var d = document.documentElement;
    d.classList.add('js');
    var seen = false;
    try { seen = sessionStorage.getItem('vulcatic-intro') === '1'; } catch (e) {}
    if (seen || matchMedia('(prefers-reduced-motion: reduce)').matches) d.classList.add('no-intro');
  })();
</script>
HTML;

$pageTitle = 'Roadside tire help near you';
$bodyClass = '!bg-white overflow-x-hidden';
require __DIR__ . '/includes/header.php';

$check = '<svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
?>

<!-- ============ INTRO: animated logo ============ -->
<div id="intro" class="intro fixed inset-0 z-[100] grid place-items-center bg-brand-500" aria-hidden="true">
  <div class="flex flex-col items-center">
    <div class="relative">
      <img src="<?= url('assets/img/gecko.webp') ?>" alt="" class="intro-gecko relative z-10 h-[34vh] max-h-72 min-h-48 w-auto" width="480" height="741">
      <span class="intro-shadow absolute -bottom-2 left-1/2 h-4 w-3/4 -translate-x-1/2 rounded-[50%] bg-black/20"></span>
    </div>
    <p class="intro-word mt-5 flex overflow-hidden text-4xl font-bold tracking-tight text-white sm:text-5xl">
      <?php foreach (str_split('Vulcatic') as $i => $ch): ?>
        <span style="--i: <?= $i ?>"><?= $ch ?></span>
      <?php endforeach; ?>
    </p>
    <p class="intro-tag mt-2 text-sm font-medium text-white/80">Help is nearby.</p>
  </div>
</div>

<!-- ============ NAV ============ -->
<header id="nav" class="nav fixed inset-x-0 top-0 z-50 transition-all duration-300">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 md:px-8">
    <a href="<?= url('index.php') ?>" class="group flex items-center gap-2">
      <?= logo_full('h-16 -my-2 origin-bottom transition duration-300 group-hover:-rotate-6 group-hover:scale-110') ?>
      <span class="text-xl font-bold tracking-tight">Vulcatic</span>
    </a>
    <nav class="hidden items-center gap-8 text-sm font-medium text-gray-600 md:flex">
      <a href="#how" class="hover:text-brand-600">How it works</a>
      <a href="#modes" class="hover:text-brand-600">Offline mode</a>
      <a href="#vulcators" class="hover:text-brand-600">For vulcators</a>
    </nav>
    <div class="hidden items-center gap-2 md:flex">
      <a href="<?= url('login.php') ?>" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Log in</a>
      <a href="<?= url('register.php?role=customer') ?>" class="btn-shine rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-500/30 hover:bg-brand-600">Get started</a>
    </div>
    <button id="menu-btn" class="grid h-10 w-10 place-items-center rounded-xl text-gray-700 hover:bg-gray-100 md:hidden" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
      <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path class="menu-top" d="M4 7h16"/><path class="menu-mid" d="M4 12h16"/><path class="menu-bot" d="M4 17h16"/></svg>
    </button>
  </div>
  <div id="mobile-menu" class="mobile-menu mx-4 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 md:hidden">
    <div class="flex flex-col p-3 text-base font-medium">
      <a href="#how" class="rounded-lg px-3 py-3 hover:bg-gray-50">How it works</a>
      <a href="#modes" class="rounded-lg px-3 py-3 hover:bg-gray-50">Offline mode</a>
      <a href="#vulcators" class="rounded-lg px-3 py-3 hover:bg-gray-50">For vulcators</a>
      <div class="mt-2 grid grid-cols-2 gap-2">
        <a href="<?= url('login.php') ?>" class="rounded-xl py-3 text-center font-semibold ring-1 ring-gray-200">Log in</a>
        <a href="<?= url('register.php?role=customer') ?>" class="rounded-xl bg-brand-500 py-3 text-center font-semibold text-white">Get started</a>
      </div>
    </div>
  </div>
</header>

<main>
  <!-- ============ HERO ============ -->
  <section id="hero" class="relative overflow-hidden pb-20 pt-28 md:pb-28 md:pt-36">
    <div class="pointer-events-none absolute inset-0 -z-10">
      <div class="blob blob-a absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-brand-100" data-depth="20"></div>
      <div class="blob blob-b absolute -left-40 top-1/2 h-80 w-80 rounded-full bg-orange-50" data-depth="35"></div>
      <div class="hero-grid absolute inset-0"></div>
    </div>

    <div class="mx-auto grid max-w-6xl items-center gap-14 px-5 md:grid-cols-2 md:px-8">
      <div>
        <p data-reveal class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-100">
          <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-500 opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-brand-500"></span></span>
          Roadside tire help for travelers &amp; locals
        </p>
        <h1 data-reveal style="--d: 80ms" class="mt-5 text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
          <span class="rotator block h-[1.15em] overflow-hidden text-brand-500" aria-live="polite">
            <span class="rotator-word block">Flat tire?</span>
          </span>
          Help is minutes away.
        </h1>
        <p data-reveal style="--d: 160ms" class="mt-5 max-w-lg text-lg text-gray-600">
          Vulcatic connects you with the nearest vulcanizing shop or skilled local. They come to you, or we guide you to them, even with no signal.
        </p>
        <div data-reveal style="--d: 240ms" class="mt-8 flex flex-col gap-3 sm:flex-row">
          <a href="<?= url('register.php?role=customer') ?>" class="magnetic btn-shine group inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 px-7 py-4 font-semibold text-white shadow-lg shadow-brand-500/30 transition hover:bg-brand-600">
            I need help now
            <svg viewBox="0 0 24 24" class="h-5 w-5 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
          <a href="<?= url('register.php?role=provider') ?>" class="magnetic inline-flex items-center justify-center gap-2 rounded-2xl bg-ink-900 px-7 py-4 font-semibold text-white transition hover:bg-ink-800">
            Become a vulcator
          </a>
        </div>
        <ul data-reveal style="--d: 320ms" class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-gray-600">
          <li class="flex items-center gap-1.5 text-gray-700"><span class="text-ok-500"><?= $check ?></span>Works offline</li>
          <li class="flex items-center gap-1.5 text-gray-700"><span class="text-ok-500"><?= $check ?></span>Verified vulcators</li>
          <li class="flex items-center gap-1.5 text-gray-700"><span class="text-ok-500"><?= $check ?></span>Cash &amp; GCash</li>
        </ul>
      </div>

      <!-- Phone with the live rescue animation -->
      <div data-reveal style="--d: 200ms" class="relative mx-auto" data-tilt>
        <div class="float-chip chip-a absolute -right-6 top-28 z-20 hidden items-center gap-2 rounded-2xl bg-white px-3 py-2 text-xs font-semibold shadow-xl ring-1 ring-black/5 sm:flex" data-depth="-25">
          <span class="grid h-7 w-7 place-items-center rounded-full bg-ok-500/15 text-ok-600"><?= $check ?></span> Tire fixed in 12 min
        </div>
        <div class="float-chip chip-b absolute -left-10 bottom-40 z-20 hidden items-center gap-2 rounded-2xl bg-white px-3 py-2 text-xs font-semibold shadow-xl ring-1 ring-black/5 sm:flex" data-depth="-40">
          <span class="text-base">★</span> 4.9 · Jose Dela Cruz
        </div>

        <div class="phone relative h-[560px] w-[280px] rounded-[2.75rem] bg-ink-900 p-3 shadow-2xl shadow-ink-900/30">
          <div class="relative h-full w-full overflow-hidden rounded-[2.1rem] bg-[#EEF0F3]">
            <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between bg-white px-5 pb-3 pt-3 shadow-sm">
              <div>
                <p class="text-[13px] font-bold">Hi! <span class="text-brand-500">Juan</span></p>
                <p id="scene-status" class="scene-status text-[10px] font-semibold text-brand-500">Finding help near you…</p>
              </div>
              <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700 ring-2 ring-brand-500">JC</span>
            </div>

            <svg id="scene" viewBox="0 0 260 460" class="absolute inset-0 h-full w-full">
              <!-- city blocks -->
              <g fill="#fff">
                <rect x="10" y="70" width="60" height="70" rx="6"/><rect x="88" y="70" width="70" height="70" rx="6"/>
                <rect x="176" y="70" width="74" height="70" rx="6"/><rect x="10" y="158" width="60" height="90" rx="6"/>
                <rect x="88" y="158" width="70" height="90" rx="6"/><rect x="176" y="158" width="74" height="40" rx="6"/>
                <rect x="10" y="266" width="148" height="70" rx="6"/><rect x="208" y="216" width="42" height="120" rx="6"/>
                <rect x="10" y="354" width="90" height="90" rx="6"/><rect x="118" y="354" width="132" height="90" rx="6"/>
              </g>
              <g fill="#D1FAE5"><rect x="22" y="282" width="40" height="38" rx="5"/><rect x="132" y="372" width="34" height="30" rx="5"/></g>
              <!-- route -->
              <path id="route-path" d="M40 150 H167 V207 H199 V345" fill="none" stroke="#E8501E" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" class="route" pathLength="1"/>
              <!-- other vulcators -->
              <g class="other-pins">
                <g transform="translate(225 110)"><circle r="9" fill="#1E2235"/><circle r="3" fill="#fff"/></g>
                <g transform="translate(120 300)"><circle r="9" fill="#1E2235"/><circle r="3" fill="#fff"/></g>
              </g>
              <!-- user + radar -->
              <g transform="translate(199 345)">
                <circle class="radar r1" r="16" fill="#E8501E"/><circle class="radar r2" r="16" fill="#E8501E"/>
                <circle r="11" fill="#E8501E" stroke="#fff" stroke-width="4"/>
              </g>
              <!-- the vulcator that comes -->
              <g id="mover" class="mover">
                <circle r="13" fill="#1E2235" stroke="#fff" stroke-width="3"/>
                <path d="M-5 3l4-4m0 0a3 3 0 1 0 4-4l-2 2-1.5-.5L-1-5a3 3 0 0 0-4 4z" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" transform="translate(1 1)"/>
                <animateMotion id="mover-motion" dur="4s" begin="indefinite" fill="freeze" calcMode="linear" keyPoints="0;1" keyTimes="0;1">
                  <mpath href="#route-path"/>
                </animateMotion>
              </g>
              <!-- fixed burst -->
              <g transform="translate(199 345)" class="burst">
                <?php for ($a = 0; $a < 8; $a++): ?>
                  <circle r="3.5" fill="<?= $a % 2 ? '#22C55E' : '#E8501E' ?>" style="--a: <?= $a * 45 ?>deg"/>
                <?php endfor; ?>
              </g>
            </svg>

            <!-- bottom sheet -->
            <div id="scene-card" class="scene-card absolute inset-x-3 bottom-3 z-10 rounded-2xl bg-white p-3.5 shadow-xl">
              <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-ink-900 text-xs font-bold text-white">JD</span>
                <div class="min-w-0 flex-1">
                  <p class="text-[13px] font-bold">Jose Dela Cruz</p>
                  <p class="text-[10px] text-gray-500">★ 4.9 · Flat tire · Motorcycle</p>
                </div>
                <div class="text-right">
                  <p id="scene-eta" class="text-sm font-bold text-brand-500">4 min</p>
                  <p class="text-[10px] text-gray-400">ETA</p>
                </div>
              </div>
              <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100"><div id="scene-bar" class="scene-bar h-full rounded-full bg-brand-500"></div></div>
            </div>
          </div>
          <div class="absolute left-1/2 top-3 h-6 w-24 -translate-x-1/2 rounded-b-2xl bg-ink-900"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ MARQUEE ============ -->
  <div class="marquee border-y border-gray-100 bg-gray-50 py-4" aria-hidden="true">
    <div class="marquee-track flex w-max gap-10 text-sm font-semibold uppercase tracking-widest text-gray-400">
      <?php for ($k = 0; $k < 2; $k++): foreach (['Flat tire repair', 'Vulcanizing', 'Air refill', 'Tire replacement', 'Battery jump-start', 'On-site service', 'Walk-in shops'] as $s): ?>
        <span class="flex items-center gap-10"><?= $s ?><span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span></span>
      <?php endforeach; endfor; ?>
    </div>
  </div>

  <!-- ============ HOW IT WORKS ============ -->
  <section id="how" class="scroll-mt-20 py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-5 md:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <p data-reveal class="text-sm font-semibold uppercase tracking-widest text-brand-500">How it works</p>
        <h2 data-reveal style="--d: 80ms" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">From stranded to rolling in four steps</h2>
      </div>

      <div class="steps relative mt-16">
        <div class="absolute left-6 top-0 h-full w-1 rounded-full bg-gray-100 md:left-0 md:top-6 md:h-1 md:w-full">
          <div id="steps-fill" class="steps-fill h-full w-full rounded-full bg-brand-500"></div>
        </div>
        <ol class="relative grid gap-10 md:grid-cols-4 md:gap-6">
          <?php
          $steps = [
            ['Share your location', 'Tap once and we detect where you are, even on a road you don\'t know.', 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
            ['Get matched', 'The nearest verified vulcators get your request. The first to accept is on the way.', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M17 11l2 2 4-4'],
            ['Track the arrival', 'Watch them come on the map with a live ETA, and call or message anytime.', 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z M9 4v14 M15 6v14'],
            ['Pay & rate', 'Pay with cash or GCash when the job is done, then rate your vulcator.', 'M3 7h18v10H3z M3 10h18 M7 15h3'],
          ];
          foreach ($steps as $i => [$title, $text, $icon]): ?>
            <li data-reveal style="--d: <?= $i * 120 ?>ms" class="step relative flex gap-5 md:block">
              <span class="step-dot relative z-10 grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white text-brand-500 shadow-lg ring-1 ring-gray-100 transition">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $icon ?>"/></svg>
              </span>
              <div class="md:mt-6">
                <p class="text-xs font-bold text-brand-500">STEP <?= $i + 1 ?></p>
                <h3 class="mt-1 text-lg font-semibold"><?= $title ?></h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-600"><?= $text ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <!-- ============ ONLINE / OFFLINE MODES ============ -->
  <section id="modes" class="scroll-mt-20 bg-ink-900 py-20 text-white md:py-28">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 md:grid-cols-2 md:px-8">
      <div>
        <p data-reveal class="text-sm font-semibold uppercase tracking-widest text-brand-500">Signal or no signal</p>
        <h2 data-reveal style="--d: 80ms" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">We've got you either way.</h2>
        <div data-reveal style="--d: 160ms" class="mode-switch relative mt-8 inline-grid grid-cols-2 rounded-2xl bg-white/10 p-1.5" role="tablist">
          <span class="mode-pill absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-brand-500 transition-transform duration-500"></span>
          <button role="tab" aria-selected="true" data-mode="online" class="relative z-10 rounded-xl px-5 py-2.5 text-sm font-semibold">I have signal</button>
          <button role="tab" aria-selected="false" data-mode="offline" class="relative z-10 rounded-xl px-5 py-2.5 text-sm font-semibold text-white/70">No signal</button>
        </div>
        <div class="mode-texts relative mt-8 min-h-[13rem]">
          <div data-mode-panel="online" class="mode-panel is-active">
            <h3 class="text-xl font-semibold">On-site repair</h3>
            <p class="mt-2 text-white/70">Request help in a tap. A nearby vulcator comes to where you're stranded, with the tools to fix it on the spot.</p>
            <ul class="mt-5 space-y-3 text-sm text-white/85">
              <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Live tracking and ETA</li>
              <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Call or message your vulcator</li>
              <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Clear price before they arrive</li>
            </ul>
          </div>
          <div data-mode-panel="offline" class="mode-panel">
            <h3 class="text-xl font-semibold">Walk to the nearest shop</h3>
            <p class="mt-2 text-white/70">Vulcatic saves nearby shops on your phone while you're online. Lose signal, and you can still find the closest one and get there.</p>
            <ul class="mt-5 space-y-3 text-sm text-white/85">
              <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Nearby shops saved for offline use</li>
              <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Distance and opening hours</li>
              <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Works in areas with weak coverage</li>
            </ul>
          </div>
        </div>
      </div>

      <div data-reveal style="--d: 120ms" class="mode-art relative mx-auto aspect-square w-full max-w-md">
        <!-- online art -->
        <svg data-mode-panel="online" viewBox="0 0 400 400" class="mode-panel is-active absolute inset-0 h-full w-full">
          <circle cx="200" cy="200" r="170" fill="#ffffff08"/><circle cx="200" cy="200" r="120" fill="#ffffff0d"/>
          <path d="M70 300 C 140 300, 140 120, 300 110" fill="none" stroke="#ffffff30" stroke-width="3" stroke-dasharray="8 10"/>
          <path class="art-route" d="M70 300 C 140 300, 140 120, 300 110" fill="none" stroke="#E8501E" stroke-width="5" stroke-linecap="round" pathLength="1"/>
          <g transform="translate(300 110)"><circle class="radar r1" r="22" fill="#E8501E"/><circle r="14" fill="#E8501E" stroke="#fff" stroke-width="4"/></g>
          <g class="art-car">
            <circle r="18" fill="#fff"/><path d="M-7 4l6-6m0 0a4 4 0 1 0 6-6l-3 3-2-.6-.6-2 3-3a4 4 0 0 0-6 6z" fill="none" stroke="#1E2235" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" transform="translate(1 1)"/>
            <animateMotion dur="3.5s" repeatCount="indefinite" path="M70 300 C 140 300, 140 120, 300 110"/>
          </g>
          <g transform="translate(250 250)"><rect x="-70" y="-26" width="140" height="52" rx="14" fill="#fff"/><text x="-54" y="-3" font-size="12" font-weight="700" fill="#1E2235" font-family="Poppins, sans-serif">Vulcator on the way</text><text x="-54" y="14" font-size="11" fill="#E8501E" font-family="Poppins, sans-serif">Arriving in 4 min</text></g>
        </svg>
        <!-- offline art -->
        <svg data-mode-panel="offline" viewBox="0 0 400 400" class="mode-panel absolute inset-0 h-full w-full">
          <circle cx="200" cy="200" r="170" fill="#ffffff08"/><circle cx="200" cy="200" r="120" fill="#ffffff0d"/>
          <g transform="translate(95 105)" class="nosignal">
            <rect x="-36" y="-36" width="72" height="72" rx="20" fill="#ffffff14"/>
            <path d="M-16 12v4M-6 4v12M4-4v20M14-12v28" stroke="#ffffff60" stroke-width="5" stroke-linecap="round"/>
            <path d="M-22-20L22 22" stroke="#EF4444" stroke-width="5" stroke-linecap="round"/>
          </g>
          <path class="art-walk" d="M110 300 Q 200 300 220 230 T 310 150" fill="none" stroke="#fff" stroke-width="4" stroke-dasharray="2 12" stroke-linecap="round"/>
          <g transform="translate(110 300)"><circle r="12" fill="#E8501E" stroke="#fff" stroke-width="4"/></g>
          <g transform="translate(310 140)"><g class="shop-pin">
            <path d="M0 26C-18 8-22 0-22-8a22 22 0 0 1 44 0c0 8-4 16-22 34z" fill="#22C55E"/>
            <path d="M-8-12h16v12h-16z M-10-12l10-8 10 8" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round"/>
          </g></g>
          <g transform="translate(250 305)"><rect x="-80" y="-26" width="160" height="52" rx="14" fill="#fff"/><text x="-64" y="-3" font-size="12" font-weight="700" fill="#1E2235" font-family="Poppins, sans-serif">JD Vulcanizing · 350 m</text><text x="-64" y="14" font-size="11" fill="#16A34A" font-family="Poppins, sans-serif">Open now · saved offline</text></g>
        </svg>
      </div>
    </div>
  </section>

  <!-- ============ AUDIENCES ============ -->
  <section id="vulcators" class="scroll-mt-20 py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-5 md:px-8">
      <div class="mx-auto max-w-2xl text-center">
        <p data-reveal class="text-sm font-semibold uppercase tracking-widest text-brand-500">Built for both sides of the road</p>
        <h2 data-reveal style="--d: 80ms" class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Get help, or get paid to help.</h2>
      </div>
      <div class="mt-14 grid gap-6 md:grid-cols-2">
        <article data-reveal data-tilt class="glow-card group relative overflow-hidden rounded-3xl bg-brand-50 p-8 ring-1 ring-brand-100">
          <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-500 text-white shadow-lg shadow-brand-500/30 transition group-hover:-rotate-6 group-hover:scale-110">
            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
          </span>
          <h3 class="mt-6 text-2xl font-bold">For travelers &amp; locals</h3>
          <p class="mt-2 text-gray-600">New to the area? Vulcatic knows who can fix your tire nearby.</p>
          <ul class="mt-6 space-y-3 text-sm text-gray-700">
            <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Shops and skilled locals in one map</li>
            <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>On-site repair or walk-in</li>
            <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Pay with cash or GCash</li>
          </ul>
          <a href="<?= url('register.php?role=customer') ?>" class="mt-8 inline-flex items-center gap-2 font-semibold text-brand-600">Create a free account <span class="transition group-hover:translate-x-1">→</span></a>
        </article>
        <article data-reveal style="--d: 120ms" data-tilt class="glow-card group relative overflow-hidden rounded-3xl bg-ink-900 p-8 text-white">
          <span class="grid h-14 w-14 place-items-center rounded-2xl bg-white text-ink-900 transition group-hover:rotate-12 group-hover:scale-110">
            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
          </span>
          <h3 class="mt-6 text-2xl font-bold">For vulcators</h3>
          <p class="mt-2 text-white/70">Run a vulcanizing shop, or just know your way around a tire? Earn from jobs near you.</p>
          <ul class="mt-6 space-y-3 text-sm text-white/85">
            <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Go online whenever you're free</li>
            <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Accept only the jobs you want</li>
            <li class="flex gap-2"><span class="text-brand-500"><?= $check ?></span>Track jobs and earnings each day</li>
          </ul>
          <a href="<?= url('register.php?role=provider') ?>" class="mt-8 inline-flex items-center gap-2 font-semibold text-brand-500">Become a vulcator <span class="transition group-hover:translate-x-1">→</span></a>
        </article>
      </div>

      <dl class="mt-16 grid grid-cols-2 gap-6 text-center md:grid-cols-4">
        <?php foreach ([[0, '₱', '', 'to sign up'], [2, '', '', 'ways to get help'], [3, '', '', 'payment options'], [24, '', '/7', 'request anytime']] as $i => [$n, $pre, $suf, $label]): ?>
          <div data-reveal style="--d: <?= $i * 90 ?>ms" class="rounded-2xl bg-gray-50 p-6">
            <dt class="sr-only"><?= $label ?></dt>
            <dd class="text-4xl font-bold text-ink-900"><?= $pre ?><span data-count="<?= $n ?>"><?= $n ?></span><?= $suf ?></dd>
            <dd class="mt-1 text-sm text-gray-500"><?= $label ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>

  <!-- ============ CTA ============ -->
  <section class="px-5 pb-20 md:px-8">
    <div data-reveal class="cta relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-brand-500 px-6 py-16 text-center text-white md:py-20">
      <svg class="cta-tire pointer-events-none absolute -right-16 -top-16 h-64 w-64 text-white/10" viewBox="0 0 120 120" aria-hidden="true">
        <circle cx="60" cy="60" r="40" fill="none" stroke="currentColor" stroke-width="14"/><path d="M60 22v11M60 87v11M22 60h11M87 60h11" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
      </svg>
      <svg class="cta-tire cta-tire-2 pointer-events-none absolute -bottom-20 -left-12 h-56 w-56 text-black/10" viewBox="0 0 120 120" aria-hidden="true">
        <circle cx="60" cy="60" r="40" fill="none" stroke="currentColor" stroke-width="14"/><path d="M60 22v11M60 87v11M22 60h11M87 60h11" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
      </svg>
      <h2 class="relative text-3xl font-bold tracking-tight sm:text-5xl">Don't wait for a flat to find help.</h2>
      <p class="relative mx-auto mt-4 max-w-xl text-white/85">Sign up now, so Vulcatic is ready the moment your tire isn't.</p>
      <div class="relative mt-8 flex flex-col justify-center gap-3 sm:flex-row">
        <a href="<?= url('register.php?role=customer') ?>" class="magnetic rounded-2xl bg-white px-7 py-4 font-semibold text-brand-600 shadow-lg transition hover:bg-brand-50">Create free account</a>
        <a href="<?= url('login.php') ?>" class="magnetic rounded-2xl px-7 py-4 font-semibold text-white ring-2 ring-white/40 transition hover:bg-white/10">I already have one</a>
      </div>
    </div>
  </section>
</main>

<footer class="border-t border-gray-100 py-10">
  <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 text-sm text-gray-500 md:flex-row md:px-8">
    <a href="<?= url('index.php') ?>" class="flex items-center gap-2 font-semibold text-gray-800"><?= logo_full('h-12') ?> Vulcatic</a>
    <p>Roadside tire help for travelers, tourists and locals.</p>
    <p>© <?= date('Y') ?> Vulcatic</p>
  </div>
</footer>

<?php
$pageScripts = ['landing.js'];
require __DIR__ . '/includes/footer.php';
