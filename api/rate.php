<?php
// POST {id, stars 1-5, comment}
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('customer');

$job = load_job((int) (input()['id'] ?? 0));
if (!$job || (int) $job['customer_id'] !== (int) $user['id']) json_error('Job not found.', 404);
if ($job['status'] !== 'paid') json_error('You can rate once the job is paid.');

$stars = (int) (input()['stars'] ?? 0);
if ($stars < 1 || $stars > 5) json_error('Choose 1 to 5 stars.');
$comment = mb_substr(trim((string) (input()['comment'] ?? '')), 0, 500) ?: null;

db()->prepare('INSERT INTO ratings (job_id, customer_id, provider_id, stars, comment) VALUES (?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE stars = VALUES(stars), comment = VALUES(comment)')
    ->execute([$job['id'], $user['id'], $job['provider_id'], $stars, $comment]);
refresh_provider_rating((int) $job['provider_id']);

json_out(['ok' => true]);
