<?php
// Filtered job list for the admin "All jobs" page and its CSV export.
// Filters: town, status (active|searching|paid|cancelled), days (7|30|90|all), q (code / name), provider, customer.

const JOB_DAY_OPTIONS = ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'all' => 'All time'];

function admin_jobs_filters(array $in): array
{
    return [
        'town'     => array_key_exists($in['town'] ?? '', SIARGAO_TOWNS) ? $in['town'] : '',
        'status'   => in_array($in['status'] ?? '', ['active', 'searching', 'paid', 'cancelled'], true) ? $in['status'] : '',
        'days'     => array_key_exists((string) ($in['days'] ?? ''), JOB_DAY_OPTIONS) ? (string) $in['days'] : '30',
        'q'        => mb_substr(trim((string) ($in['q'] ?? '')), 0, 60),
        'provider' => (int) ($in['provider'] ?? 0) ?: '',
        'customer' => (int) ($in['customer'] ?? 0) ?: '',
    ];
}

// Returns [rows, total count].
function admin_jobs_query(array $in, int $limit, int $offset): array
{
    $f = admin_jobs_filters($in);
    $where = ['1 = 1']; $args = [];
    if ($f['town'])     { $where[] = 'j.municipality = ?'; $args[] = $f['town']; }
    if ($f['provider']) { $where[] = 'j.provider_id = ?'; $args[] = $f['provider']; }
    if ($f['customer']) { $where[] = 'j.customer_id = ?'; $args[] = $f['customer']; }
    if ($f['days'] !== 'all') { $where[] = 'j.requested_at >= CURDATE() - INTERVAL ' . ((int) $f['days'] - 1) . ' DAY'; }
    $where[] = match ($f['status']) {
        'active'    => "j.status IN ('accepted','on_the_way','arrived','completed')",
        'searching' => "j.status IN ('searching','matched')",
        'paid'      => "j.status = 'paid'",
        'cancelled' => "j.status = 'cancelled'",
        default     => '1 = 1',
    };
    if ($f['q'] !== '') {
        $where[] = '(j.job_code LIKE ? OR u.full_name LIKE ? OR p.display_name LIKE ? OR u.phone LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
        array_push($args, $like, $like, $like, $like);
    }
    $from = 'FROM jobs j JOIN services s ON s.id = j.service_id JOIN users u ON u.id = j.customer_id
             LEFT JOIN providers p ON p.id = j.provider_id LEFT JOIN payments pay ON pay.job_id = j.id
             WHERE ' . implode(' AND ', $where);

    $stmt = db()->prepare("SELECT COUNT(*) $from");
    $stmt->execute($args);
    $total = (int) $stmt->fetchColumn();

    $stmt = db()->prepare("SELECT j.id, j.job_code, j.status, j.municipality, j.vehicle_type, j.total_amount, j.platform_fee,
                                  j.provider_earning, j.requested_at, s.name AS service, u.full_name AS customer,
                                  p.display_name AS vulcator, pay.method AS pay_method
                           $from ORDER BY j.requested_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($args);
    return [$stmt->fetchAll(), $total];
}
