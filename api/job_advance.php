<?php
// POST {id, action, eta}  ->  the shop / vulcator / assigned employee moves the job forward.
//   start  : accepted -> on_the_way (roadside; optional eta in minutes)
//   eta    : update the ETA while on the way
//   arrive : on_the_way -> arrived (roadside)   or   accepted -> arrived (motorist reached the shop)
//   begin  : arrived -> in_progress
//   fixed  : in_progress -> completed
//   paid   : completed -> paid (payment received)
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user();
if (!in_array($user['role'], ['provider', 'employee'], true)) json_error('Not allowed.', 403);

$job = load_job((int) (input()['id'] ?? 0));
$role = $job ? job_role($job, $user) : null;
if (!$role || $role === 'customer') json_error('Job not found.', 404);
if (!can_work_job($job, $role)) json_error('Accept the assignment first.', 403);
if ($job['status'] === 'cancelled') json_error('The customer cancelled this job.', 409);

$action = input()['action'] ?? '';
$walkIn = $job['mode'] === 'walk_in';
$eta = filter_var(input()['eta'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 180]]) ?: null;
$who = first_name($user['full_name']);

if ($action === 'eta') {
    if ($job['status'] !== 'on_the_way' || !$eta) json_error('Set an ETA while you are on the way.');
    db()->prepare('UPDATE jobs SET eta_minutes = ?, eta_set_at = NOW() WHERE id = ?')->execute([$eta, $job['id']]);
    log_status($job['id'], 'on_the_way', "New ETA: about $eta min");
    json_out(['ok' => true]);
}

$steps = [
    'start'  => $walkIn ? null : ['accepted', 'on_the_way', ', eta_minutes = ?, eta_set_at = IF(? IS NULL, NULL, NOW())', "$who is on the way"],
    'arrive' => $walkIn ? ['accepted', 'arrived', ', arrived_at = NOW()', 'You arrived at the shop']
                        : ['on_the_way', 'arrived', ', arrived_at = NOW(), eta_minutes = NULL', "$who arrived"],
    'begin'  => ['arrived', 'in_progress', ', started_at = NOW()', 'Repair started'],
    'fixed'  => ['in_progress', 'completed', ', completed_at = NOW()', 'Repair done'],
    'paid'   => ['completed', 'paid', '', 'Payment received'],
];
if (empty($steps[$action])) json_error('Unknown action.');
[$from, $to, $extra, $note] = $steps[$action];

if ($job['status'] !== $from) {
    $behind = array_search($job['status'], JOB_FLOW, true) < array_search($from, JOB_FLOW, true);
    json_error($behind ? 'Finish the earlier steps first.' : 'This step is already done.', 409);
}

$args = [$to];
if ($action === 'start') { array_push($args, $eta, $eta); if ($eta) $note .= " · about $eta min"; }
array_push($args, $job['id'], $from);

$pdo = db();
$pdo->beginTransaction();
$pdo->prepare("UPDATE jobs SET status = ? $extra WHERE id = ? AND status = ?")->execute($args);

if ($action === 'paid') {
    // The motorist may not have picked a method yet; then it was cash in hand.
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
