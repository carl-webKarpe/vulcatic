<?php
// POST {id, body}  ->  chat message between the motorist and whoever handles the job.
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user();

$job = load_job((int) (input()['id'] ?? 0));
$role = $job ? job_role($job, $user) : null;
if (!$role) json_error('Job not found.', 404);
if (!$job['provider_id']) json_error('You can chat once a shop or vulcator accepts.');
if (in_array($job['status'], ['paid', 'cancelled'], true)) json_error('This job is closed.');

$body = trim(preg_replace('/\s+/', ' ', (string) (input()['body'] ?? '')));
if ($body === '') json_error('Type a message.');
$body = mb_substr($body, 0, 500);

// Slow down spam: at most 20 messages a minute per person.
$stmt = db()->prepare('SELECT COUNT(*) FROM job_messages WHERE sender_id = ? AND created_at > NOW() - INTERVAL 1 MINUTE');
$stmt->execute([$user['id']]);
if ((int) $stmt->fetchColumn() >= 20) json_error('You\'re sending messages too fast.', 429);

db()->prepare('INSERT INTO job_messages (job_id, sender_id, body) VALUES (?, ?, ?)')->execute([$job['id'], $user['id'], $body]);
json_out(['ok' => true, 'messages' => job_messages((int) $job['id'], (int) $user['id'])]);
