<?php
// Team (shops only): add employees, see who's on duty, reset passwords, deactivate.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login('provider');
$provider = provider_for_user($user['id']);
if ($provider['provider_type'] !== 'shop') {
    flash('error', 'Team is for vulcanizing shops. Switch your profile to "Vulcanizing shop" to add employees.');
    redirect('profile.php');
}
$pid = (int) $provider['id'];
$errors = [];

// Readable temporary passwords (no 0/O, 1/I/l).
function temp_password(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $p = '';
    for ($i = 0; $i < 8; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name  = trim($_POST['full_name'] ?? '');
        $phone = normalize_phone($_POST['phone'] ?? '');
        if (mb_strlen($name) < 2) $errors['full_name'] = 'Enter their full name.';
        if (!$phone) $errors['phone'] = 'Enter a valid PH mobile number.';
        if (!$errors) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn()) $errors['phone'] = 'This number already has a Vulcatic account.';
        }
        if (!$errors) {
            $temp = temp_password();
            db()->prepare("INSERT INTO users (role, full_name, phone, password_hash, employer_provider_id, must_change_password, terms_accepted_at)
                           VALUES ('employee', ?, ?, ?, ?, 1, NOW())")
                ->execute([$name, $phone, password_hash($temp, PASSWORD_DEFAULT), $pid]);
            $_SESSION['team_temp'] = ['name' => $name, 'phone' => $phone, 'password' => substr($temp, 0, 4) . '-' . substr($temp, 4), 'new' => true];
            redirect('team.php');
        }
    } else {
        $stmt = db()->prepare("SELECT id, full_name, phone FROM users WHERE id = ? AND role = 'employee' AND employer_provider_id = ?");
        $stmt->execute([(int) ($_POST['user_id'] ?? 0), $pid]);
        if ($emp = $stmt->fetch()) {
            if ($action === 'reset') {
                $temp = temp_password();
                db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), $emp['id']]);
                $_SESSION['team_temp'] = ['name' => $emp['full_name'], 'phone' => $emp['phone'], 'password' => substr($temp, 0, 4) . '-' . substr($temp, 4), 'new' => false];
            } elseif (in_array($action, ['deactivate', 'reactivate'], true)) {
                db()->prepare('UPDATE users SET is_active = ?, on_duty = 0 WHERE id = ?')->execute([$action === 'reactivate' ? 1 : 0, $emp['id']]);
                if ($action === 'deactivate') {
                    // Hand their open assignments back to the owner.
                    db()->prepare("UPDATE jobs SET assigned_user_id = NULL, assignment_status = NULL WHERE assigned_user_id = ? AND status IN ('accepted','on_the_way','arrived')")->execute([$emp['id']]);
                }
                flash('success', $emp['full_name'] . ($action === 'reactivate' ? ' can log in again.' : ' can no longer log in.'));
            }
        }
        redirect('team.php');
    }
}
if (isset($_GET['done'])) { unset($_SESSION['team_temp']); redirect('team.php'); }

$stmt = db()->prepare("SELECT u.id, u.full_name, u.phone, u.on_duty, u.is_active, u.must_change_password, u.created_at,
                              (SELECT COUNT(*) FROM jobs j WHERE j.assigned_user_id = u.id AND j.status IN ('accepted','on_the_way','arrived','in_progress','completed')) AS active_jobs,
                              (SELECT COUNT(*) FROM jobs j WHERE j.assigned_user_id = u.id AND j.status = 'paid') AS done_jobs,
                              (SELECT COUNT(*) FROM jobs j WHERE j.assigned_user_id = u.id AND j.status = 'paid' AND j.completed_at >= CURDATE()) AS today_jobs
                       FROM users u WHERE u.role = 'employee' AND u.employer_provider_id = ? ORDER BY u.is_active DESC, u.on_duty DESC, u.full_name");
$stmt->execute([$pid]);
$team = $stmt->fetchAll();
$temp = $_SESSION['team_temp'] ?? null;

$activeTab = 'team';
$greeting  = 'Team';
$subline   = count(array_filter($team, fn ($t) => $t['is_active'] && $t['on_duty'])) . ' on duty · ' . count(array_filter($team, fn ($t) => $t['is_active'])) . ' active';
$pageTitle = 'Team';
$statusDot = $provider['is_online'] ? 'online' : 'offline';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
$input = 'mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';
?>
<main class="mx-auto grid max-w-6xl gap-5 px-5 pb-28 pt-5 md:px-8 md:pb-10 lg:grid-cols-3">
  <div class="min-w-0 space-y-5 lg:col-span-2">
    <?php if ($temp): ?>
      <section class="rounded-2xl bg-ink-900 p-5 text-white">
        <p class="text-sm text-white/70"><?= $temp['new'] ? 'Account created for' : 'New temporary password for' ?> <b class="text-white"><?= e($temp['name']) ?></b> · <?= e($temp['phone']) ?></p>
        <p class="mt-2 font-mono text-3xl font-bold tracking-widest text-ok-500"><?= e($temp['password']) ?></p>
        <p class="mt-2 text-sm text-white/70">They log in with their phone number and this password, then choose their own. It won't be shown again.</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <a href="sms:<?= e($temp['phone']) ?>?body=<?= rawurlencode('Vulcatic: log in with your number and the password ' . str_replace('-', '', $temp['password']) . ', then choose your own password.') ?>" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-ink-900">Text it</a>
          <a href="<?= url('team.php?done=1') ?>" class="rounded-xl px-4 py-2 text-sm font-semibold text-white/70 hover:text-white">Done, close this</a>
        </div>
      </section>
    <?php endif; ?>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
      <h2 class="mb-1 font-bold">Your team</h2>
      <p class="mb-3 text-sm text-gray-500">Employees get the jobs you assign. They switch "On duty" when they're working.</p>
      <?php if (!$team): ?><p class="py-8 text-center text-sm text-gray-400">No employees yet. Add your first one.</p><?php endif; ?>
      <ul class="divide-y divide-gray-100">
        <?php foreach ($team as $t): ?>
          <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center <?= $t['is_active'] ? '' : 'opacity-60' ?>">
            <div class="flex min-w-0 flex-1 items-center gap-3">
              <span class="relative grid h-11 w-11 shrink-0 place-items-center rounded-full bg-ink-900 text-sm font-bold text-white"><?= e(initials($t['full_name'])) ?>
                <span class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full ring-2 ring-white <?= $t['is_active'] && $t['on_duty'] ? 'bg-ok-500' : 'bg-gray-300' ?>"></span></span>
              <div class="min-w-0"><p class="truncate font-semibold"><?= e($t['full_name']) ?>
                  <span class="ml-1 text-xs font-medium <?= !$t['is_active'] ? 'text-red-600' : ($t['on_duty'] ? 'text-green-700' : 'text-gray-400') ?>"><?= !$t['is_active'] ? 'Deactivated' : ($t['on_duty'] ? 'On duty' : 'Off duty') ?></span></p>
                <p class="truncate text-xs text-gray-500"><a href="tel:<?= e($t['phone']) ?>" class="hover:text-brand-600"><?= e($t['phone']) ?></a> · <?= (int) $t['active_jobs'] ?> active · <?= (int) $t['today_jobs'] ?> done today · <?= (int) $t['done_jobs'] ?> total
                  <?= $t['must_change_password'] ? ' · <span class="text-amber-600">hasn\'t logged in yet</span>' : '' ?></p></div>
            </div>
            <form method="post" class="flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>">
              <button name="action" value="reset" data-confirm="Make a new temporary password for <?= e($t['full_name']) ?>?" data-confirm-label="Reset" class="rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ring-gray-200 hover:bg-gray-50">Reset password</button>
              <?php if ($t['is_active']): ?>
                <button name="action" value="deactivate" data-confirm="Deactivate <?= e($t['full_name']) ?>? Their open jobs come back to you." data-confirm-label="Deactivate" data-danger class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Deactivate</button>
              <?php else: ?>
                <button name="action" value="reactivate" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-green-700 ring-1 ring-green-300 hover:bg-green-50">Reactivate</button>
              <?php endif; ?>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <form method="post" class="h-fit rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5" data-once novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2 class="font-bold">Add an employee</h2>
    <p class="text-sm text-gray-500">We'll make a temporary password for them.</p>
    <label class="mt-4 block text-sm font-semibold" for="full_name">Full name</label>
    <input id="full_name" name="full_name" value="<?= old('full_name') ?>" class="<?= $input ?>">
    <?php if (isset($errors['full_name'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['full_name']) ?></p><?php endif; ?>
    <label class="mt-3 block text-sm font-semibold" for="phone">Mobile number</label>
    <input id="phone" name="phone" type="tel" value="<?= old('phone') ?>" placeholder="09XX XXX XXXX" class="<?= $input ?>">
    <?php if (isset($errors['phone'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['phone']) ?></p><?php endif; ?>
    <button class="mt-5 w-full rounded-xl bg-brand-500 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Add employee</button>
  </form>
</main>
<?php
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
