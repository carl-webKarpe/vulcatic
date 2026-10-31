<?php
// Shared layout for terms.php and privacy.php. Set $pageTitle, $updated, then echo the body between legal_start() and legal_end().
function legal_contact(): string
{
    global $config;
    $email = $config['app']['contact_email'] ?? '';
    $phone = $config['app']['contact_phone'] ?? '';
    $parts = array_filter([$email ? '<a class="text-brand-600 underline" href="mailto:' . e($email) . '">' . e($email) . '</a>' : '',
                           $phone ? e($phone) : '']);
    return $parts ? implode(' · ', $parts) : 'the Vulcatic team (contact details coming soon)';
}

function legal_start(string $title, string $updated): void
{
    $pageTitle = $title;
    $bodyClass = '!bg-white';
    require __DIR__ . '/header.php'; ?>
    <header class="bg-brand-500 text-white">
      <div class="mx-auto flex max-w-3xl items-center gap-3 px-5 py-6">
        <a href="<?= url('index.php') ?>" aria-label="Vulcatic home"><?= logo_full('h-14') ?></a>
        <div><h1 class="text-2xl font-bold"><?= e($title) ?></h1><p class="text-sm text-white/80">Last updated <?= e($updated) ?></p></div>
      </div>
    </header>
    <main class="legal mx-auto max-w-3xl space-y-6 px-5 py-8 text-[15px] leading-relaxed text-gray-700">
    <?php
}

function legal_end(): void
{ ?>
      <p class="border-t border-gray-200 pt-6 text-sm">
        <a href="<?= url('terms.php') ?>" class="font-semibold text-brand-600">Terms of Use</a> ·
        <a href="<?= url('privacy.php') ?>" class="font-semibold text-brand-600">Privacy Policy</a> ·
        <a href="<?= url('index.php') ?>" class="font-semibold text-brand-600">Back to Vulcatic</a>
      </p>
    </main>
    <?php require __DIR__ . '/footer.php';
}
