<?php
// Admin area. Each section lives in includes/admin/view_<name>.php; actions are handled here.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
require __DIR__ . '/includes/charts.php';
require __DIR__ . '/includes/admin/layout.php';
$user = require_login('admin');

const ADMIN_VIEWS = ['overview', 'approvals', 'resets', 'live', 'jobs', 'job', 'towns', 'vulcators', 'customers', 'reviews', 'settings'];
$view = in_array($_GET['view'] ?? '', ADMIN_VIEWS, true) ? $_GET['view'] : 'overview';

// After an action, go back to the page (and filters) it came from.
function back(): never
{
    $q = $_POST['back'] ?? '';
    parse_str($q, $params);
    unset($params['temp_done']);
    redirect('admin.php' . ($params ? '?' . http_build_query($params) : ''));
}

if (isset($_GET['temp_done'])) {
    unset($_SESSION['temp_password']);
    redirect('admin.php' . ($view !== 'overview' ? '?view=' . $view : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---------- Password reset requests ----------
    if (isset($_POST['reset_id'])) {
        $stmt = db()->prepare("SELECT r.*, u.full_name FROM password_reset_requests r LEFT JOIN users u ON u.id = r.user_id
                               WHERE r.id = ? AND r.status = 'open'");
        $stmt->execute([(int) $_POST['reset_id']]);
        if ($r = $stmt->fetch()) {
            if ($action === 'reset' && $r['user_id']) {
                // Easy to read out over the phone: no 0/O or 1/I/l.
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $temp = '';
                for ($i = 0; $i < 8; $i++) $temp .= $chars[random_int(0, strlen($chars) - 1)];
                db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, is_active = 1 WHERE id = ?')
                    ->execute([password_hash($temp, PASSWORD_DEFAULT), $r['user_id']]);
                db()->prepare('DELETE FROM login_attempts WHERE login = ?')->execute([$r['phone']]);
                $_SESSION['temp_password'] = ['name' => $r['full_name'], 'phone' => $r['phone'], 'password' => substr($temp, 0, 4) . '-' . substr($temp, 4)];
                db()->prepare("UPDATE password_reset_requests SET status = 'done', handled_at = NOW() WHERE user_id = ? AND status = 'open'")->execute([$r['user_id']]);
            } else {
                db()->prepare("UPDATE password_reset_requests SET status = 'dismissed', handled_at = NOW() WHERE id = ?")->execute([$r['id']]);
                flash('success', 'Request dismissed.');
            }
        }
        back();
    }

    // ---------- Vulcators ----------
    if (isset($_POST['provider_id'])) {
        $pid = (int) $_POST['provider_id'];
        $stmt = db()->prepare('SELECT display_name FROM providers WHERE id = ?');
        $stmt->execute([$pid]);
        $name = $stmt->fetchColumn();
        $sqls = [
            'approve'    => ["UPDATE providers SET verification_status = 'verified' WHERE id = ?", 'approved'],
            'reject'     => ["UPDATE providers SET verification_status = 'rejected', is_online = 0 WHERE id = ?", 'rejected'],
            'suspend'    => ["UPDATE users SET is_active = 0 WHERE id = (SELECT user_id FROM providers WHERE id = ?)", 'suspended'],
            'reactivate' => ["UPDATE users SET is_active = 1 WHERE id = (SELECT user_id FROM providers WHERE id = ?)", 'reactivated'],
        ];
        if ($name !== false && isset($sqls[$action])) {
            db()->prepare($sqls[$action][0])->execute([$pid]);
            if (in_array($action, ['suspend', 'reject'], true)) {
                db()->prepare('UPDATE providers SET is_online = 0 WHERE id = ?')->execute([$pid]);
                db()->prepare("UPDATE job_offers SET status = 'expired' WHERE provider_id = ? AND status = 'pending'")->execute([$pid]);
            }
            flash('success', $name . ' ' . $sqls[$action][1] . '.');
        }
        back();
    }

    // ---------- Customers ----------
    if (isset($_POST['customer_id'])) {
        $stmt = db()->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'customer'");
        $stmt->execute([(int) $_POST['customer_id']]);
        if (($name = $stmt->fetchColumn()) !== false && in_array($action, ['suspend', 'reactivate'], true)) {
            db()->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'customer'")
                ->execute([$action === 'reactivate' ? 1 : 0, (int) $_POST['customer_id']]);
            flash('success', $name . ($action === 'reactivate' ? ' reactivated.' : ' suspended.'));
        }
        back();
    }

    // ---------- Settings ----------
    if ($action === 'save_settings') {
        $fee     = filter_var($_POST['platform_fee_pct'] ?? '', FILTER_VALIDATE_FLOAT);
        $conv    = filter_var($_POST['convenience_fee'] ?? '', FILTER_VALIDATE_FLOAT);
        $timeout = filter_var($_POST['offer_timeout_seconds'] ?? '', FILTER_VALIDATE_INT);
        $radius  = filter_var($_POST['default_search_radius_km'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($fee === false || $fee < 0 || $fee > 30 || $conv === false || $conv < 0 || $conv > 500
            || $timeout === false || $timeout < 20 || $timeout > 600 || $radius === false || $radius < 1 || $radius > 30) {
            flash('error', 'Check the values: fee 0–30%, convenience fee ₱0–500, timeout 20–600 s, radius 1–30 km.');
        } else {
            $up = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $up->execute(['platform_fee_rate', (string) round($fee / 100, 4)]);
            $up->execute(['convenience_fee', number_format($conv, 2, '.', '')]);
            $up->execute(['offer_timeout_seconds', (string) $timeout]);
            $up->execute(['default_search_radius_km', (string) $radius]);
            flash('success', 'Settings saved. New jobs use them right away.');
        }
        back();
    }
    if ($action === 'save_services') {
        $up = db()->prepare('UPDATE services SET base_price = ?, is_active = ? WHERE id = ?');
        foreach ($_POST['base_price'] ?? [] as $sid => $price) {
            $price = filter_var($price, FILTER_VALIDATE_FLOAT);
            if ($price !== false && $price >= 0 && $price <= 100000) {
                $up->execute([round($price, 2), isset($_POST['active'][$sid]) ? 1 : 0, (int) $sid]);
            }
        }
        flash('success', 'Services saved.');
        back();
    }
    back();
}

// ---------- CSV export of the jobs list ----------
if ($view === 'jobs' && ($_GET['export'] ?? '') === 'csv') {
    require __DIR__ . '/includes/admin/jobs_query.php';
    [$rows] = admin_jobs_query($_GET, 5000, 0);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vulcatic-jobs-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the ₱ and accents correctly
    fputcsv($out, ['Job code', 'Requested', 'Town', 'Service', 'Vehicle', 'Customer', 'Vulcator', 'Status', 'Total (PHP)', 'Platform fee', 'Vulcator earning', 'Payment']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['job_code'], $r['requested_at'], $r['municipality'], $r['service'], $r['vehicle_type'], $r['customer'],
                       $r['vulcator'], $r['status'], $r['total_amount'], $r['platform_fee'], $r['provider_earning'], $r['pay_method']]);
    }
    exit;
}

require __DIR__ . '/includes/admin/view_' . $view . '.php';
