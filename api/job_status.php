<?php
// GET ?id=  ->  live job state for the customer who asked or the vulcator doing it.
require __DIR__ . '/../includes/api.php';
$user = api_user();

$job = load_job((int) ($_GET['id'] ?? 0));
if (!$job) json_error('Job not found.', 404);

if ($user['role'] === 'customer') {
    if ((int) $job['customer_id'] !== (int) $user['id']) json_error('Job not found.', 404);
    maintain_job($job);
    $job = load_job((int) $job['id']);
} else {
    $provider = provider_for_user($user['id']);
    if (!$provider || (int) $job['provider_id'] !== (int) $provider['id']) json_error('Job not found.', 404);
}

$stmt = db()->prepare('SELECT status, note, created_at FROM job_status_logs WHERE job_id = ? ORDER BY created_at, id');
$stmt->execute([$job['id']]);

$out = ['job' => job_payload($job), 'log' => $stmt->fetchAll()];

if ($user['role'] === 'provider') {
    $stmt = db()->prepare("SELECT COUNT(*) AS jobs, COALESCE(SUM(provider_earning), 0) AS earned FROM jobs
                           WHERE provider_id = ? AND status = 'paid' AND completed_at >= CURDATE()");
    $stmt->execute([$provider['id']]);
    $t = $stmt->fetch();
    $out['today'] = ['jobs' => (int) $t['jobs'], 'earned' => (float) $t['earned']];
}
json_out($out);
