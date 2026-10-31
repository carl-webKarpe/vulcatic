<?php
// POST {id, action}  ->  vulcator moves their job forward.
//   start: accepted -> on_the_way     arrive: on_the_way -> arrived
//   fixed: arrived -> completed       paid:   completed -> paid (payment received)
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('provider');
$provider = provider_for_user($user['id']);

$job = load_job((int) (input()['id'] ?? 0));
if (!$job || (int) $job['provider_id'] !== (int) $provider['id']) json_error('Job not found.', 404);

$steps = [
    'start'  => ['accepted',   'on_the_way', '',                     'On the way'],
    'arrive' => ['on_the_way', 'arrived',    ', arrived_at = NOW()', 'Arrived at customer'],
    'fixed'  => ['arrived',    'completed',  ', completed_at = NOW()', 'Tire fixed'],
    'paid'   => ['completed',  'paid',       '',                     'Payment received'],
];
$action = input()['action'] ?? '';
if (!isset($steps[$action])) json_error('Unknown action.');
[$from, $to, $extra, $note] = $steps[$action];

if ($job['status'] === 'cancelled') json_error('The customer cancelled this job.', 409);
if ($job['status'] !== $from) {
    $behind = array_search($job['status'], JOB_FLOW, true) < array_search($from, JOB_FLOW, true);
    json_error($behind ? 'Finish the earlier steps first.' : 'This step is already done.', 409);
}

$pdo = db();
$pdo->beginTransaction();
$pdo->prepare("UPDATE jobs SET status = ? $extra WHERE id = ? AND status = ?")->execute([$to, $job['id'], $from]);

if ($action === 'paid') {
    // Customer may not have picked a method yet; then it was cash in hand.
    $stmt = $pdo->prepare('SELECT method FROM payments WHERE job_id = ?');
    $stmt->execute([$job['id']]);
    $method = $stmt->fetchColumn() ?: 'cash';
    $pdo->prepare("INSERT INTO payments (job_id, method, amount, status, paid_at) VALUES (?, ?, ?, 'paid', NOW())
                   ON DUPLICATE KEY UPDATE status = 'paid', paid_at = NOW(), amount = VALUES(amount)")
        ->execute([$job['id'], $method, $job['total_amount']]);
    $note = ($method === 'gcash' ? 'GCash' : ucfirst($method)) . ' payment received';
}
log_status($job['id'], $to, $note);
$pdo->commit();

json_out(['ok' => true, 'status' => $to]);
