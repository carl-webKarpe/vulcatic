<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login();
// Shop owners / skilled locals and the employee assigned to the job can open it.
$job  = load_job((int) ($_GET['id'] ?? 0));
$role = $job ? job_role($job, $user) : null;
$home = $user['role'] === 'employee' ? 'employee.php' : 'dashboard.php';
if (!in_array($role, ['owner', 'employee'], true)) {
    flash('error', 'Job not found.');
    redirect($user['role'] === 'customer' ? 'home.php' : $home);
}
$provider = $role === 'owner' ? provider_for_user($user['id']) : null;

$greeting  = e(STATUS_LABELS[$job['status']]) . ' · ' . ($job['mode'] === 'walk_in' ? 'Shop visit' : 'Roadside');
$subline   = 'Job ' . $job['job_code'];
$statusDot = $user['role'] === 'provider' ? ($provider['is_online'] ? 'online' : 'offline') : null;
$pageTitle = 'Current job';
$activeTab = 'job';
$useMap    = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main id="pjob" data-job-id="<?= (int) $job['id'] ?>" data-gecko="<?= e(url('assets/img/gecko.webp')) ?>" data-home="<?= $home ?>"
      data-shares-location="<?= $provider && $provider['provider_type'] === 'individual' ? '1' : '0' ?>"
      class="mx-auto max-w-6xl md:grid md:grid-cols-[1fr_420px] md:gap-6 md:px-8 md:py-6">
  <section class="relative isolate h-[40vh] md:h-[calc(100vh-8.5rem)] md:overflow-hidden md:rounded-3xl md:ring-1 md:ring-black/5">
    <div id="map" class="h-full w-full"></div>
    <a id="directions" target="_blank" rel="noopener"
       class="absolute left-3 top-3 z-[500] hidden items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold shadow-lg ring-1 ring-black/5 hover:bg-gray-50">
      <svg viewBox="0 0 24 24" class="h-4 w-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8z"/></svg>
      Directions
    </a>
  </section>
  <section class="px-5 pb-28 pt-5 md:h-[calc(100vh-8.5rem)] md:overflow-y-auto md:px-0 md:pb-6 md:pt-0">
    <div id="panel" class="space-y-4">
      <div class="rounded-3xl bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-black/5">Loading job…</div>
    </div>
  </section>
</main>
<?php
$pageScripts = ['provider_job.js'];
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
