<?php
// POST {offer_id, action: accept|decline}
require __DIR__ . '/../includes/api.php';
require_post();
$user = api_user('provider');
$provider = provider_for_user($user['id']);

$stmt = db()->prepare("SELECT * FROM job_offers WHERE id = ? AND provider_id = ? AND status = 'pending'
                       AND offered_at >= NOW() - INTERVAL ? SECOND");
$stmt->execute([(int) (input()['offer_id'] ?? 0), $provider['id'], (int) setting('offer_timeout_seconds', '60')]);
$offer = $stmt->fetch();
if (!$offer) json_error('This request is no longer available.', 410);

if ((input()['action'] ?? '') !== 'accept') {
    db()->prepare("UPDATE job_offers SET status = 'declined', responded_at = NOW() WHERE id = ?")->execute([$offer['id']]);
    json_out(['ok' => true]);
}

if (active_job_id_for_provider($provider['id'])) json_error('Finish your current job first.');

$job = load_job((int) $offer['job_id']);
$stmt = db()->prepare('SELECT price FROM provider_services WHERE provider_id = ? AND service_id = ?');
$stmt->execute([$provider['id'], $job['service_id']]);
$price = $stmt->fetchColumn();
$fees  = job_fees($price !== false ? (float) $price : (float) $job['service_fee']);

$pdo = db();
$pdo->beginTransaction();
// Only the first vulcator to accept gets the job.
$stmt = $pdo->prepare("UPDATE jobs SET provider_id = ?, status = 'accepted', accepted_at = NOW(),
                              service_fee = ?, convenience_fee = ?, total_amount = ?, platform_fee = ?, provider_earning = ?
                       WHERE id = ? AND status IN ('searching','matched')");
$stmt->execute([$provider['id'], $fees['service_fee'], $fees['convenience_fee'], $fees['total_amount'],
                $fees['platform_fee'], $fees['provider_earning'], $job['id']]);
if ($stmt->rowCount() === 0) {
    $pdo->rollBack();
    db()->prepare("UPDATE job_offers SET status = 'expired' WHERE id = ?")->execute([$offer['id']]);
    json_error('Another vulcator already took this job.', 409);
}
$pdo->prepare("UPDATE job_offers SET status = 'accepted', responded_at = NOW() WHERE id = ?")->execute([$offer['id']]);
$pdo->prepare("UPDATE job_offers SET status = 'expired' WHERE job_id = ? AND id <> ? AND status = 'pending'")
    ->execute([$job['id'], $offer['id']]);
log_status($job['id'], 'accepted', first_name($provider['display_name']) . ' accepted your request');
$pdo->commit();

json_out(['ok' => true, 'job_id' => (int) $job['id']]);
