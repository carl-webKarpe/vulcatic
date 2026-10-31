<?php
// GET ?id=  ->  live job state for the motorist, the shop owner / vulcator, or the assigned employee.
require __DIR__ . '/../includes/api.php';
$user = api_user();

$job = load_job((int) ($_GET['id'] ?? 0));
$role = $job ? job_role($job, $user) : null;
if (!$role) json_error('Job not found.', 404);

if ($role === 'customer') {
    maintain_job($job);
    $job = load_job((int) $job['id']);
}

$stmt = db()->prepare('SELECT status, note, created_at FROM job_status_logs WHERE job_id = ? ORDER BY created_at, id');
$stmt->execute([$job['id']]);

$out = ['job' => job_payload($job), 'log' => $stmt->fetchAll(), 'role' => $role,
        'can_work' => can_work_job($job, $role), 'messages' => job_messages((int) $job['id'], (int) $user['id'])];

if ($role === 'owner') {
    $provider = provider_for_user($user['id']);
    $out['is_shop'] = $provider['provider_type'] === 'shop';
    $out['employees'] = $out['is_shop'] ? array_map(fn ($e) => ['id' => (int) $e['id'], 'name' => $e['full_name'],
        'on_duty' => (bool) $e['on_duty'], 'busy' => (int) $e['active_jobs']], shop_employees((int) $provider['id'])) : [];
    $stmt = db()->prepare("SELECT COUNT(*) AS jobs, COALESCE(SUM(provider_earning), 0) AS earned FROM jobs
                           WHERE provider_id = ? AND status = 'paid' AND completed_at >= CURDATE()");
    $stmt->execute([$provider['id']]);
    $t = $stmt->fetch();
    $out['today'] = ['jobs' => (int) $t['jobs'], 'earned' => (float) $t['earned']];
}
json_out($out);
