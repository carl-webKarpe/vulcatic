<?php
// POST {id, method: cash|gcash, reference}  ->  customer chooses how they pay.
// The vulcator then confirms with "Mark as payment received".
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('customer');

$job = load_job((int) (input()['id'] ?? 0));
if (!$job || (int) $job['customer_id'] !== (int) $user['id']) json_error('Job not found.', 404);
if ($job['status'] !== 'completed') json_error('Payment opens once the repair is done.');

$method = input()['method'] ?? '';
if (!in_array($method, ['cash', 'gcash'], true)) json_error('Choose Cash or GCash.');

$ref = null;
if ($method === 'gcash') {
    $ref = preg_replace('/\D/', '', (string) (input()['reference'] ?? ''));
    if (strlen($ref) < 8 || strlen($ref) > 20) json_error('Enter the GCash reference number from your receipt.');
}

db()->prepare("INSERT INTO payments (job_id, method, amount, status, reference_no) VALUES (?, ?, ?, 'pending', ?)
               ON DUPLICATE KEY UPDATE method = VALUES(method), reference_no = VALUES(reference_no)")
    ->execute([$job['id'], $method, $job['total_amount'], $ref]);
log_status($job['id'], 'completed', ($method === 'gcash' ? 'Paid with GCash, waiting for confirmation' : 'Paying with cash'));

json_out(['ok' => true]);
