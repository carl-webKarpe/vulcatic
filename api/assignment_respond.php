<?php
// POST {id, action: accept|decline}  ->  employee answers a job their shop assigned to them.
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('employee');

$job = load_job((int) (input()['id'] ?? 0));
if (!$job || (int) $job['assigned_user_id'] !== (int) $user['id']) json_error('This job is no longer assigned to you.', 410);
if ($job['status'] === 'cancelled') json_error('The customer cancelled this job.', 409);

if ((input()['action'] ?? '') === 'accept') {
    db()->prepare("UPDATE jobs SET assignment_status = 'accepted' WHERE id = ?")->execute([$job['id']]);
    log_status($job['id'], $job['status'], first_name($user['full_name']) . ' will handle your request');
} else {
    db()->prepare('UPDATE jobs SET assigned_user_id = NULL, assignment_status = NULL WHERE id = ?')->execute([$job['id']]);
    log_status($job['id'], $job['status'], first_name($user['full_name']) . ' couldn\'t take it. The shop is reassigning.');
}
json_out(['ok' => true]);
