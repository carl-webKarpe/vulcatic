<?php
// POST {id, user_id}  ->  shop owner assigns a job to an employee (user_id 0 = "I'll do it myself").
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('provider');
$provider = provider_for_user($user['id']);

$job = load_job((int) (input()['id'] ?? 0));
if (!$job || (int) $job['provider_id'] !== (int) $provider['id']) json_error('Job not found.', 404);
if (!in_array($job['status'], ['accepted', 'on_the_way', 'arrived'], true)) json_error('This job can no longer be reassigned.');

$to = (int) (input()['user_id'] ?? 0);
if ($to === 0) {
    db()->prepare('UPDATE jobs SET assigned_user_id = NULL, assignment_status = NULL WHERE id = ?')->execute([$job['id']]);
    log_status($job['id'], $job['status'], first_name($user['full_name']) . ' is handling this job');
    json_out(['ok' => true]);
}
$stmt = db()->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'employee' AND employer_provider_id = ? AND is_active = 1");
$stmt->execute([$to, $provider['id']]);
$name = $stmt->fetchColumn();
if ($name === false) json_error('Choose one of your team members.');

db()->prepare("UPDATE jobs SET assigned_user_id = ?, assignment_status = 'pending' WHERE id = ?")->execute([$to, $job['id']]);
log_status($job['id'], $job['status'], 'Assigned to ' . first_name($name) . ' from ' . $provider['display_name']);
json_out(['ok' => true]);
