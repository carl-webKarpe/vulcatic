<?php
// Online / offline switch (sidebar and dashboard). Returns to the page it was used on.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('provider');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard.php');
verify_csrf();

$provider = provider_for_user($user['id']);
if ($provider['verification_status'] !== 'verified') {
    flash('error', 'Your account is still being verified. You can go online once approved.');
} else {
    $online = ($_POST['is_online'] ?? '0') === '1' ? 1 : 0;
    db()->prepare('UPDATE providers SET is_online = ? WHERE id = ?')->execute([$online, $provider['id']]);
    if (!$online) {
        db()->prepare("UPDATE job_offers SET status = 'expired' WHERE provider_id = ? AND status = 'pending'")->execute([$provider['id']]);
    }
    flash('success', $online ? 'You\'re online. New requests near you will pop up with a ring.' : 'You\'re offline. You won\'t get new requests.');
}
// Only go back to our own pages.
$back = basename((string) ($_POST['back'] ?? 'dashboard.php'));
redirect(in_array($back, ['dashboard.php', 'bookings.php', 'customers.php', 'team.php', 'history.php', 'profile.php', 'shops.php'], true) ? $back : 'dashboard.php');
