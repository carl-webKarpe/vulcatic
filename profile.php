<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';
$user = require_login();
$isProvider = $user['role'] === 'provider';
$provider = $isProvider ? provider_for_user($user['id']) : null;
$errors = [];
$section = $_POST['section'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($section === 'account') {
        $name  = trim($_POST['full_name'] ?? '');
        $phone = normalize_phone($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (mb_strlen($name) < 2) $errors['full_name'] = 'Enter your full name.';
        if (!$phone) $errors['phone'] = 'Enter a valid PH mobile number, e.g. 09171234567.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email or leave it blank.';
        $motoristType = array_key_exists($_POST['motorist_type'] ?? '', MOTORIST_TYPES) ? $_POST['motorist_type'] : null;
        if (!$errors) {
            $stmt = db()->prepare('SELECT phone, email FROM users WHERE id <> ? AND (phone = ? OR (email IS NOT NULL AND email = ?))');
            $stmt->execute([$user['id'], $phone, $email]);
            foreach ($stmt->fetchAll() as $row) {
                if ($row['phone'] === $phone) $errors['phone'] = 'Another account uses this number.';
                if ($email !== '' && $row['email'] === $email) $errors['email'] = 'Another account uses this email.';
            }
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?')->execute([$name, $phone, $email ?: null, $user['id']]);
            if ($user['role'] === 'customer') db()->prepare('UPDATE users SET motorist_type = ? WHERE id = ?')->execute([$motoristType, $user['id']]);
            flash('success', 'Your details were saved.');
            redirect('profile.php');
        }
    }

    if ($section === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $stmt->fetchColumn())) $errors['current_password'] = 'Your current password is wrong.';
        elseif (strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
        elseif ($new !== ($_POST['confirm_password'] ?? '')) $errors['confirm_password'] = 'Passwords do not match.';
        if (!$errors) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }

    if ($isProvider && $section === 'shop') {
        $type    = ($_POST['provider_type'] ?? '') === 'shop' ? 'shop' : 'individual';
        $display = trim($_POST['display_name'] ?? '') ?: $user['full_name'];
        $address = trim($_POST['address'] ?? '');
        // Opening hours from the pickers, saved as "Mon-Sun 7:00-19:00" (the offline shop finder reads this).
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $hours = null;
        if (!empty($_POST['has_hours'])) {
            $from = in_array($_POST['day_from'] ?? '', $days, true) ? $_POST['day_from'] : 'Mon';
            $to   = in_array($_POST['day_to'] ?? '', $days, true) ? $_POST['day_to'] : 'Sun';
            $open = preg_match('/^(\d{1,2}):(\d{2})$/', $_POST['open_time'] ?? '', $o) ? (int) $o[1] . ':' . $o[2] : null;
            $close = preg_match('/^(\d{1,2}):(\d{2})$/', $_POST['close_time'] ?? '', $c) ? (int) $c[1] . ':' . $c[2] : null;
            if (!$open || !$close || strtotime($close) <= strtotime($open)) $errors['hours'] = 'Closing time must be after opening time.';
            else $hours = "$from-$to $open-$close";
        }
        $gcash   = trim($_POST['gcash_number'] ?? '') === '' ? null : normalize_phone($_POST['gcash_number']);
        $onsite  = isset($_POST['offers_onsite']) ? 1 : 0;
        $walkin  = isset($_POST['accepts_walk_in']) ? 1 : 0;
        if ($address === '') $errors['address'] = 'Enter your address or landmark.';
        if (trim($_POST['gcash_number'] ?? '') !== '' && !$gcash) $errors['gcash_number'] = 'Enter a valid GCash mobile number.';
        if (!$onsite && !$walkin) $errors['mode'] = 'Choose at least one: on-site repair or walk-in.';
        if (!$errors) {
            db()->prepare('UPDATE providers SET provider_type = ?, display_name = ?, address = ?, opening_hours = ?, gcash_number = ?,
                           offers_onsite = ?, accepts_walk_in = ? WHERE id = ?')
                ->execute([$type, $display, $address, $hours, $gcash, $onsite, $walkin, $provider['id']]);
            flash('success', 'Shop details saved.');
            redirect('profile.php');
        }
    }

    if ($isProvider && $section === 'services') {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM provider_services WHERE provider_id = ?')->execute([$provider['id']]);
        $ins = $pdo->prepare('INSERT INTO provider_services (provider_id, service_id, price) VALUES (?, ?, ?)');
        $count = 0;
        foreach ($_POST['service'] ?? [] as $sid => $on) {
            $price = filter_var($_POST['price'][$sid] ?? '', FILTER_VALIDATE_FLOAT);
            if ($price === false || $price < 0 || $price > 100000) { $errors['services'] = 'Enter a price for each service you offer.'; break; }
            $ins->execute([$provider['id'], (int) $sid, round($price, 2)]);
            $count++;
        }
        if (!$errors && $count === 0) $errors['services'] = 'Offer at least one service.';
        if ($errors) { $pdo->rollBack(); }
        else { $pdo->commit(); flash('success', 'Services and prices saved.'); redirect('profile.php'); }
    }

    if ($isProvider && $section === 'location') {
        $lat = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false) $errors['location'] = 'Tap "Use my current location" first.';
        elseif (!in_service_area($lat, $lng)) $errors['location'] = 'That spot is outside Siargao Island.';
        if (!$errors) {
            db()->prepare('UPDATE providers SET latitude = ?, longitude = ?, municipality = ?, last_location_at = NOW() WHERE id = ?')
                ->execute([$lat, $lng, municipality_for($lat, $lng), $provider['id']]);
            flash('success', 'Location updated.');
            redirect('profile.php');
        }
    }
}

$user = current_user();
if ($isProvider) {
    $provider = provider_for_user($user['id']);
    $stmt = db()->prepare('SELECT s.id, s.name, s.base_price, ps.price FROM services s
                           LEFT JOIN provider_services ps ON ps.service_id = s.id AND ps.provider_id = ?
                           WHERE s.is_active = 1 ORDER BY s.id');
    $stmt->execute([$provider['id']]);
    $services = $stmt->fetchAll();
}

// Keep posted values in the form that had errors; otherwise show saved values.
function val(string $key, ?string $saved, string $section): string
{
    return e(($_POST['section'] ?? '') === $section && isset($_POST[$key]) ? $_POST[$key] : (string) $saved);
}
function err(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="mt-1 text-sm text-red-600">' . e($errors[$key]) . '</p>' : '';
}

$input = 'w-full rounded-xl border border-gray-300 px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';
$label = 'mb-1 block text-sm font-semibold';
$card  = 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5';
$save  = 'mt-5 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-600';

$activeTab = 'profile';
$greeting  = 'Profile';
$subline   = ['customer' => 'Customer', 'provider' => 'Vulcator', 'admin' => 'Admin'][$user['role']] . ' · ' . $user['phone'];
$pageTitle = 'Profile';
$statusDot = $isProvider ? ($provider['is_online'] ? 'online' : 'offline') : null;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/app_header.php';
?>
<main class="mx-auto grid max-w-6xl gap-5 px-5 pb-28 pt-5 md:grid-cols-2 md:px-8 md:pb-10">
  <?php if ($isProvider): ?>
    <!-- Phones: the business pages that don't fit in the bottom tabs -->
    <nav class="grid grid-cols-3 gap-2 md:col-span-2 lg:hidden" aria-label="Business">
      <?php foreach (array_filter(['customers.php' => ['Customers', 'customers'], 'team.php' => $provider['provider_type'] === 'shop' ? ['Team', 'team'] : null, 'shops.php' => ['Nearby shops', 'shops']]) as $href => [$t, $ic]): ?>
        <a href="<?= url($href) ?>" class="flex flex-col items-center gap-1 rounded-2xl bg-white py-3 text-xs font-semibold shadow-sm ring-1 ring-black/5 hover:bg-gray-50"><?= nav_icon($ic, 'h-5 w-5 text-brand-500') ?><?= $t ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <div class="space-y-5">
    <form method="post" class="<?= $card ?>" data-once novalidate>
      <?= csrf_field() ?><input type="hidden" name="section" value="account">
      <h2 class="font-bold">Your details</h2>
      <div class="mt-4 space-y-3">
        <div><label class="<?= $label ?>" for="full_name">Full name</label>
          <input id="full_name" name="full_name" value="<?= val('full_name', $user['full_name'], 'account') ?>" class="<?= $input ?>"><?= err($errors, 'full_name') ?></div>
        <div><label class="<?= $label ?>" for="phone">Mobile number</label>
          <input id="phone" name="phone" type="tel" value="<?= val('phone', $user['phone'], 'account') ?>" class="<?= $input ?>"><?= err($errors, 'phone') ?></div>
        <div><label class="<?= $label ?>" for="email">Email <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="email" name="email" type="email" value="<?= val('email', $user['email'], 'account') ?>" class="<?= $input ?>"><?= err($errors, 'email') ?></div>
        <?php if ($user['role'] === 'customer'): $mt = ($_POST['section'] ?? '') === 'account' ? ($_POST['motorist_type'] ?? '') : ($user['motorist_type'] ?? ''); ?>
          <div><label class="<?= $label ?>" for="motorist_type">I am a…</label>
            <select id="motorist_type" name="motorist_type" class="<?= $input ?> bg-white"><option value="">Prefer not to say</option>
              <?php foreach (MOTORIST_TYPES as $v => $t): ?><option value="<?= $v ?>" <?= $mt === $v ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
      </div>
      <button class="<?= $save ?>">Save details</button>
    </form>

    <form method="post" class="<?= $card ?>" data-once novalidate>
      <?= csrf_field() ?><input type="hidden" name="section" value="password">
      <h2 class="font-bold">Change password</h2>
      <div class="mt-4 space-y-3">
        <div><label class="<?= $label ?>" for="current_password">Current password</label>
          <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="<?= $input ?>"><?= err($errors, 'current_password') ?></div>
        <div><label class="<?= $label ?>" for="new_password">New password</label>
          <input id="new_password" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" class="<?= $input ?>"><?= err($errors, 'new_password') ?></div>
        <div><label class="<?= $label ?>" for="confirm_password">Confirm new password</label>
          <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" class="<?= $input ?>"><?= err($errors, 'confirm_password') ?></div>
      </div>
      <button class="<?= $save ?>">Change password</button>
    </form>

    <form method="post" action="<?= url('logout.php') ?>" class="<?= $card ?> flex items-center justify-between">
      <?= csrf_field() ?>
      <div><p class="font-bold">Log out</p><p class="text-sm text-gray-500">Signed in as <?= e($user['full_name']) ?> ·
        <a href="<?= url('terms.php') ?>" class="underline">Terms</a> · <a href="<?= url('privacy.php') ?>" class="underline">Privacy</a></p></div>
      <button class="rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Log out</button>
    </form>
  </div>

  <?php if ($isProvider): ?>
  <div class="space-y-5">
    <form method="post" class="<?= $card ?>" data-once novalidate>
      <?= csrf_field() ?><input type="hidden" name="section" value="shop">
      <div class="flex items-start justify-between gap-3">
        <h2 class="font-bold">Vulcator details</h2>
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1
          <?= ['verified' => 'bg-green-50 text-green-700 ring-green-200', 'pending' => 'bg-amber-50 text-amber-700 ring-amber-200', 'rejected' => 'bg-red-50 text-red-700 ring-red-200'][$provider['verification_status']] ?>">
          <?= ucfirst($provider['verification_status']) ?></span>
      </div>
      <?php $ptype = ($_POST['section'] ?? '') === 'shop' ? ($_POST['provider_type'] ?? '') : $provider['provider_type']; ?>
      <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
        <?php foreach (['individual' => 'Skilled local', 'shop' => 'Vulcanizing shop'] as $v => $t): ?>
          <label class="role-card flex cursor-pointer items-center gap-2 rounded-lg border-2 border-gray-200 px-3 py-2">
            <input type="radio" name="provider_type" value="<?= $v ?>" class="sr-only" <?= $ptype === $v ? 'checked' : '' ?>>
            <span class="role-dot h-3.5 w-3.5 rounded-full border-2 border-gray-300"></span><?= $t ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 space-y-3">
        <div><label class="<?= $label ?>" for="display_name">Name customers see</label>
          <input id="display_name" name="display_name" value="<?= val('display_name', $provider['display_name'], 'shop') ?>" class="<?= $input ?>"></div>
        <div><label class="<?= $label ?>" for="address">Address / landmark</label>
          <input id="address" name="address" value="<?= val('address', $provider['address'], 'shop') ?>" class="<?= $input ?>"><?= err($errors, 'address') ?></div>
        <?php
          $h = ($_POST['section'] ?? '') === 'shop' ? null : $provider['opening_hours'];
          preg_match('/^(\w{3})-(\w{3})\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', (string) $h, $hm);
          $hasHours = ($_POST['section'] ?? '') === 'shop' ? !empty($_POST['has_hours']) : (bool) $hm;
          $pad = fn ($hh, $mm) => sprintf('%02d:%s', (int) $hh, $mm);
          $dFrom = $_POST['day_from'] ?? ($hm[1] ?? 'Mon'); $dTo = $_POST['day_to'] ?? ($hm[2] ?? 'Sun');
          $tOpen = $_POST['open_time'] ?? ($hm ? $pad($hm[3], $hm[4]) : '07:00'); $tClose = $_POST['close_time'] ?? ($hm ? $pad($hm[5], $hm[6]) : '18:00');
        ?>
        <fieldset class="rounded-xl bg-gray-50 p-3">
          <legend class="sr-only">Operating hours</legend>
          <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="has_hours" value="1" class="h-4 w-4 accent-brand-500" <?= $hasHours ? 'checked' : '' ?>
            onchange="document.getElementById('hours-fields').classList.toggle('hidden', !this.checked)"> Operating hours</label>
          <div id="hours-fields" class="mt-3 grid grid-cols-2 gap-2 <?= $hasHours ? '' : 'hidden' ?>">
            <?php foreach (['day_from' => ['From', $dFrom], 'day_to' => ['To', $dTo]] as $n => [$t, $sel]): ?>
              <label class="text-xs font-semibold text-gray-500"><?= $t ?>
                <select name="<?= $n ?>" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900">
                  <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><option <?= $d === $sel ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></label>
            <?php endforeach; ?>
            <label class="text-xs font-semibold text-gray-500">Opens<input type="time" name="open_time" value="<?= e($tOpen) ?>" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900"></label>
            <label class="text-xs font-semibold text-gray-500">Closes<input type="time" name="close_time" value="<?= e($tClose) ?>" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900"></label>
          </div>
          <?= err($errors, 'hours') ?>
        </fieldset>
        <div><label class="<?= $label ?>" for="gcash_number">GCash number <span class="font-normal text-gray-400">(customers pay here)</span></label>
          <input id="gcash_number" name="gcash_number" type="tel" value="<?= val('gcash_number', $provider['gcash_number'], 'shop') ?>" placeholder="09XX XXX XXXX" class="<?= $input ?>"><?= err($errors, 'gcash_number') ?></div>
        <?php $isShopPost = ($_POST['section'] ?? '') === 'shop'; ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="offers_onsite" class="h-4 w-4 accent-brand-500"
          <?= ($isShopPost ? isset($_POST['offers_onsite']) : $provider['offers_onsite']) ? 'checked' : '' ?>> Roadside assistance (I go to the motorist)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accepts_walk_in" class="h-4 w-4 accent-brand-500"
          <?= ($isShopPost ? isset($_POST['accepts_walk_in']) : $provider['accepts_walk_in']) ? 'checked' : '' ?>> Shop visits (motorists come to my shop)</label>
        <?= err($errors, 'mode') ?>
      </div>
      <button class="<?= $save ?>">Save vulcator details</button>
    </form>

    <form method="post" class="<?= $card ?>" data-once novalidate>
      <?= csrf_field() ?><input type="hidden" name="section" value="services">
      <h2 class="font-bold">Services &amp; prices</h2>
      <p class="text-sm text-gray-500">Customers see your price before you arrive.</p>
      <ul class="mt-4 space-y-2">
        <?php foreach ($services as $s): $on = $s['price'] !== null; ?>
          <li class="flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2 sm:gap-3">
            <input type="checkbox" id="svc<?= (int) $s['id'] ?>" name="service[<?= (int) $s['id'] ?>]" value="1" class="h-4 w-4 accent-brand-500" <?= $on ? 'checked' : '' ?>>
            <label for="svc<?= (int) $s['id'] ?>" class="min-w-0 flex-1 text-sm font-medium"><?= e($s['name']) ?></label>
            <span class="text-sm text-gray-400">₱</span>
            <input name="price[<?= (int) $s['id'] ?>]" inputmode="decimal" value="<?= e(number_format((float) ($s['price'] ?? $s['base_price']), 2, '.', '')) ?>"
                   aria-label="Price for <?= e($s['name']) ?>" class="w-20 shrink-0 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm outline-none focus:border-brand-500">
          </li>
        <?php endforeach; ?>
      </ul>
      <?= err($errors, 'services') ?>
      <button class="<?= $save ?>">Save services</button>
    </form>

    <form method="post" class="<?= $card ?>" id="location-form" novalidate>
      <?= csrf_field() ?><input type="hidden" name="section" value="location">
      <h2 class="font-bold">Your location</h2>
      <p class="text-sm text-gray-500">Currently: <b><?= e($provider['municipality'] ?: 'Unknown town') ?></b> ·
        <?= e(number_format((float) $provider['latitude'], 5)) ?>, <?= e(number_format((float) $provider['longitude'], 5)) ?></p>
      <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude">
      <button type="button" id="locate-btn" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-brand-500 py-2.5 text-sm font-semibold text-brand-600 hover:bg-brand-50">
        Use my current location
      </button>
      <p id="locate-status" class="mt-2 text-xs text-gray-500"></p>
      <?= err($errors, 'location') ?>
    </form>
  </div>
  <?php endif; ?>
</main>
<?php if ($isProvider): ?>
<script>
  document.getElementById('locate-btn').addEventListener('click', () => {
    const status = document.getElementById('locate-status');
    if (!navigator.geolocation) { status.textContent = 'Your browser cannot share location.'; return; }
    status.textContent = 'Getting your location…';
    navigator.geolocation.getCurrentPosition((p) => {
      document.getElementById('latitude').value = p.coords.latitude.toFixed(7);
      document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
      document.getElementById('location-form').submit();
    }, () => { status.textContent = 'Could not get your location. Allow location access and try again.'; },
    { enableHighAccuracy: true, timeout: 15000 });
  });
</script>
<?php endif; ?>
<?php
require __DIR__ . '/includes/bottom_nav.php';
require __DIR__ . '/includes/footer.php';
