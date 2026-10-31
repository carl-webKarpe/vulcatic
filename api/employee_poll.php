<?php
// GET  ->  an employee's assigned jobs (new assignments first).
require __DIR__ . '/../includes/api.php';
$user = api_user('employee');

$stmt = db()->prepare("SELECT j.id, j.job_code, j.status, j.mode, j.assignment_status, j.vehicle_type, j.problem, j.address_text,
                              j.municipality, j.requested_at, u.full_name AS customer
                       FROM jobs j JOIN users u ON u.id = j.customer_id
                       WHERE j.assigned_user_id = ? AND j.status IN ('accepted','on_the_way','arrived','in_progress','completed')
                       ORDER BY j.assignment_status = 'pending' DESC, j.requested_at");
$stmt->execute([$user['id']]);
json_out(['jobs' => array_map(fn ($j) => [
    'id' => (int) $j['id'], 'code' => $j['job_code'], 'status' => $j['status'], 'status_label' => STATUS_LABELS[$j['status']],
    'mode' => $j['mode'], 'pending' => $j['assignment_status'] === 'pending', 'customer' => $j['customer'],
    'service' => PROBLEMS[$j['problem'] ?? 'other'][0], 'vehicle' => ucfirst($j['vehicle_type']),
    'address' => $j['address_text'], 'municipality' => $j['municipality'],
], $stmt->fetchAll())]);
