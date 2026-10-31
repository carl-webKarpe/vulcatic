<?php
// POST {id, reason}  ->  customer cancels before the vulcator arrives.
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('customer');

$job = load_job((int) (input()['id'] ?? 0));
if (!$job || (int) $job['customer_id'] !== (int) $user['id']) json_error('Job not found.', 404);
if (!in_array($job['status'], ['searching', 'matched', 'accepted', 'on_the_way'], true)) {
    json_error('This job can no longer be cancelled.');
}

$reason = mb_substr(trim((string) (input()['reason'] ?? '')), 0, 255) ?: 'Cancelled by customer';
db()->prepare("UPDATE jobs SET status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$reason, $job['id']]);
db()->prepare("UPDATE job_offers SET status = 'expired' WHERE job_id = ? AND status = 'pending'")->execute([$job['id']]);
log_status($job['id'], 'cancelled', $reason);
json_out(['ok' => true]);
