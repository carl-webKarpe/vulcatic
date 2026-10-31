<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('customer');

$job = load_job((int) ($_GET['id'] ?? 0));
if (!$job || (int) $job['customer_id'] !== (int) $user['id']) {
    flash('error', 'Request not found.');
    redirect('home.php');
}

$greeting  = 'Hi! <span class="text-brand-500">' . e(first_name($user['full_name'])) . '</span>';
$subline   = 'Request ' . $job['job_code'];
$pageTitle = 'Your request';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main id="job" data-job-id="<?= (int) $job['id'] ?>" data-gecko="<?= e(url('assets/img/gecko.webp')) ?>"
      class="mx-auto max-w-6xl md:grid md:grid-cols-[1fr_420px] md:gap-6 md:px-8 md:py-6">
  <section class="relative h-[40vh] md:h-[calc(100vh-8.5rem)] md:overflow-hidden md:rounded-3xl md:ring-1 md:ring-black/5">
    <div id="map" class="h-full w-full"></div>
  </section>
  <section class="px-5 pb-28 pt-5 md:h-[calc(100vh-8.5rem)] md:overflow-y-auto md:px-0 md:pb-6 md:pt-0">
    <div id="panel" class="space-y-4">
      <div class="rounded-3xl bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-black/5">Loading your request…</div>
    </div>
  </section>
</main>
<?php
$activeTab   = 'home';
$pageScripts = ['job.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
