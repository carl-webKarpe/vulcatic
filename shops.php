<?php
// Shops tab inside the app (with the normal menu). With no signal, the
// service worker shows offline.html instead, which has the same content.
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
if ($user['role'] === 'admin') redirect('admin.php?view=towns');

$isProvider = $user['role'] === 'provider';
$activeTab = 'shops';
$greeting  = 'Nearby shops';
$subline   = 'Saved on your phone for when there\'s no signal';
$pageTitle = 'Nearby shops';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 pt-4 md:px-8">
  <p id="net-status" class="text-sm font-medium text-brand-500">Checking your connection…</p>
  <a id="back-link" href="<?= url($isProvider ? 'dashboard.php' : 'home.php') ?>"
     class="hidden rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600"><?= $isProvider ? 'Back to dashboard' : 'Request on-site help' ?></a>
</div>
<?php require __DIR__ . '/includes/shops_panel.php'; ?>
<?php
$pageScripts = ['offline.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
