<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/jobs.php';

if ($user = current_user()) {
    redirect(home_for($user));
}

$role   = ($_POST['role'] ?? $_GET['role'] ?? 'customer') === 'provider' ? 'provider' : 'customer';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = normalize_phone($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (mb_strlen($fullName) < 2)  $errors['full_name'] = 'Enter your full name.';
    if (!$phone)                   $errors['phone'] = 'Enter a valid PH mobile number, e.g. 09171234567.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email or leave it blank.';
    if (empty($_POST['terms']))    $errors['terms'] = 'Please agree to the Terms of Use and Privacy Policy to continue.';
    if (strlen($password) < 8)     $errors['password'] = 'Use at least 8 characters.';
    elseif ($password !== $confirm) $errors['password_confirm'] = 'Passwords do not match.';

    // Extra fields for vulcators
    if ($role === 'provider') {
        $providerType  = ($_POST['provider_type'] ?? '') === 'shop' ? 'shop' : 'individual';
        $displayName   = trim($_POST['display_name'] ?? '') ?: $fullName;
        $address       = trim($_POST['address'] ?? '');
        $lat           = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $lng           = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $offersOnsite  = isset($_POST['offers_onsite']) ? 1 : 0;
        $acceptsWalkIn = isset($_POST['accepts_walk_in']) ? 1 : 0;

        if ($providerType === 'shop' && trim($_POST['display_name'] ?? '') === '') {
            $errors['display_name'] = 'Enter your shop name.';
        }
        if ($address === '') $errors['address'] = 'Enter your address or landmark.';
        if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $errors['location'] = 'Tap "Use my current location" so customers can find you.';
        } elseif (!in_service_area($lat, $lng)) {
            $errors['location'] = 'Vulcatic currently accepts vulcators on Siargao Island only.';
        }
        if (!$offersOnsite && !$acceptsWalkIn) {
            $errors['mode'] = 'Choose at least one: on-site repair or walk-in.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT phone, email FROM users WHERE phone = ? OR (email IS NOT NULL AND email = ?)');
        $stmt->execute([$phone, $email]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['phone'] === $phone)                  $errors['phone'] = 'This number is already registered. Log in instead.';
            if ($email !== '' && $row['email'] === $email) $errors['email'] = 'This email is already registered.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO users (role, full_name, email, phone, password_hash, terms_accepted_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$role, $fullName, $email ?: null, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            if ($role === 'provider') {
                $stmt = $pdo->prepare('INSERT INTO providers
                    (user_id, provider_type, display_name, address, municipality, latitude, longitude, offers_onsite, accepts_walk_in, last_location_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$userId, $providerType, $displayName, $address, municipality_for($lat, $lng), $lat, $lng, $offersOnsite, $acceptsWalkIn]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        login_user(['id' => $userId]);
        flash('success', $role === 'provider'
            ? 'Account created! We will verify your details before you can receive jobs.'
            : 'Account created! Welcome to Vulcatic.');
        redirect($role === 'provider' ? 'dashboard.php' : 'home.php');
    }
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="mt-1 text-sm text-red-600">' . e($errors[$key]) . '</p>' : '';
}

$input = 'w-full rounded-xl border border-gray-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100';
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<main class="min-h-screen md:flex">
  <?php require __DIR__ . '/includes/auth_aside.php'; ?>

  <section class="flex min-h-screen flex-col bg-white md:h-screen md:w-1/2 md:overflow-y-auto">
    <div class="hero-curve relative overflow-hidden bg-brand-500 px-6 pb-10 pt-10 text-white md:hidden">
      <?= logo_full('gecko-pop absolute -bottom-1 right-5 h-28 drop-shadow-[0_6px_10px_rgba(0,0,0,0.25)]') ?>
      <a href="<?= url('index.php') ?>" class="inline-flex items-center gap-2 text-sm text-white/85">
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg> Back
      </a>
      <h1 class="mt-5 text-2xl font-bold">Create account</h1>
      <p class="text-sm text-white/85">It only takes a minute.</p>
    </div>

    <div class="mx-auto w-full px-6 py-8 md:max-w-md md:py-12">
      <h1 class="hidden text-3xl font-bold md:block">Create account</h1>
      <p class="mb-6 hidden text-gray-500 md:block">It only takes a minute.</p>

      <form method="post" class="space-y-4" data-once novalidate>
        <?= csrf_field() ?>

        <fieldset>
          <legend class="mb-2 text-sm font-medium">I want to</legend>
          <div class="grid grid-cols-2 gap-3">
            <?php foreach (['customer' => ['Get help', 'Find a repairer'], 'provider' => ['Fix tires', 'Be a vulcator']] as $value => [$title, $sub]): ?>
              <label class="role-card flex cursor-pointer items-start gap-3 rounded-xl border-2 border-gray-200 p-3 transition">
                <input type="radio" name="role" value="<?= $value ?>" class="sr-only" <?= $role === $value ? 'checked' : '' ?>>
                <span class="role-dot mt-1 h-4 w-4 shrink-0 rounded-full border-2 border-gray-300"></span>
                <span><span class="block text-sm font-semibold"><?= $title ?></span>
                      <span class="text-xs text-gray-500"><?= $sub ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div>
          <label for="full_name" class="mb-1 block text-sm font-medium">Full name</label>
          <input id="full_name" name="full_name" value="<?= old('full_name') ?>" autocomplete="name" placeholder="Juan Dela Cruz" class="<?= $input ?>">
          <?= field_error($errors, 'full_name') ?>
        </div>
        <div>
          <label for="phone" class="mb-1 block text-sm font-medium">Mobile number</label>
          <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= old('phone') ?>" autocomplete="tel" placeholder="09XX XXX XXXX" class="<?= $input ?>">
          <?= field_error($errors, 'phone') ?>
        </div>
        <div>
          <label for="email" class="mb-1 block text-sm font-medium">Email <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="email" name="email" type="email" value="<?= old('email') ?>" autocomplete="email" placeholder="you@example.com" class="<?= $input ?>">
          <?= field_error($errors, 'email') ?>
        </div>

        <!-- Vulcator details (shown when "Fix tires" is selected) -->
        <div id="provider-fields" class="space-y-4 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 <?= $role === 'provider' ? '' : 'hidden' ?>">
          <p class="text-sm font-semibold">Vulcator details</p>

          <div class="grid grid-cols-2 gap-2 text-sm">
            <?php $ptype = $_POST['provider_type'] ?? 'individual'; ?>
            <label class="role-card flex cursor-pointer items-center gap-2 rounded-lg border-2 border-gray-200 bg-white px-3 py-2">
              <input type="radio" name="provider_type" value="individual" class="sr-only" <?= $ptype !== 'shop' ? 'checked' : '' ?>>
              <span class="role-dot h-3.5 w-3.5 rounded-full border-2 border-gray-300"></span> Skilled local
            </label>
            <label class="role-card flex cursor-pointer items-center gap-2 rounded-lg border-2 border-gray-200 bg-white px-3 py-2">
              <input type="radio" name="provider_type" value="shop" class="sr-only" <?= $ptype === 'shop' ? 'checked' : '' ?>>
              <span class="role-dot h-3.5 w-3.5 rounded-full border-2 border-gray-300"></span> Vulcanizing shop
            </label>
          </div>

          <div>
            <label for="display_name" class="mb-1 block text-sm font-medium">Shop name <span class="font-normal text-gray-400">(skilled locals can leave blank)</span></label>
            <input id="display_name" name="display_name" value="<?= old('display_name') ?>" placeholder="e.g. Dapa Vulcanizing Shop" class="<?= $input ?> bg-white">
            <?= field_error($errors, 'display_name') ?>
          </div>
          <div>
            <label for="address" class="mb-1 block text-sm font-medium">Address / landmark</label>
            <input id="address" name="address" value="<?= old('address') ?>" placeholder="e.g. Tourism Road, General Luna" class="<?= $input ?> bg-white">
            <?= field_error($errors, 'address') ?>
          </div>

          <div>
            <button type="button" id="locate-btn"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-brand-500 bg-white py-2.5 text-sm font-semibold text-brand-600 transition hover:bg-brand-50">
              <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
              Use my current location
            </button>
            <p id="locate-status" class="mt-1 text-xs text-gray-500"></p>
            <div class="mt-2 grid grid-cols-2 gap-2">
              <input name="latitude" id="latitude" value="<?= old('latitude') ?>" placeholder="Latitude" inputmode="decimal" class="<?= $input ?> bg-white py-2 text-sm">
              <input name="longitude" id="longitude" value="<?= old('longitude') ?>" placeholder="Longitude" inputmode="decimal" class="<?= $input ?> bg-white py-2 text-sm">
            </div>
            <?= field_error($errors, 'location') ?>
          </div>

          <fieldset class="space-y-2 text-sm">
            <legend class="mb-1 font-medium">Services you offer</legend>
            <?php $isPost = $_SERVER['REQUEST_METHOD'] === 'POST'; ?>
            <label class="flex items-center gap-2">
              <input type="checkbox" name="offers_onsite" class="h-4 w-4 accent-brand-500" <?= !$isPost || isset($_POST['offers_onsite']) ? 'checked' : '' ?>>
              On-site repair (I go to the customer)
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" name="accepts_walk_in" class="h-4 w-4 accent-brand-500" <?= isset($_POST['accepts_walk_in']) ? 'checked' : '' ?>>
              Walk-in (customers come to my shop)
            </label>
            <?= field_error($errors, 'mode') ?>
          </fieldset>
        </div>

        <div>
          <label for="password" class="mb-1 block text-sm font-medium">Password</label>
          <div class="relative">
            <input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" class="<?= $input ?> pr-16">
            <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-0 px-4 text-sm font-medium text-gray-500">Show</button>
          </div>
          <?= field_error($errors, 'password') ?>
        </div>
        <div>
          <label for="password_confirm" class="mb-1 block text-sm font-medium">Confirm password</label>
          <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" class="<?= $input ?>">
          <?= field_error($errors, 'password_confirm') ?>
        </div>

        <label class="flex items-start gap-3 text-sm text-gray-600">
          <input type="checkbox" name="terms" value="1" class="mt-0.5 h-4 w-4 shrink-0 accent-brand-500" <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
          <span>I agree to the <a href="<?= url('terms.php') ?>" target="_blank" class="font-semibold text-brand-600 underline">Terms of Use</a>
            and <a href="<?= url('privacy.php') ?>" target="_blank" class="font-semibold text-brand-600 underline">Privacy Policy</a>,
            including sharing my name, number and location with the other person during a job.</span>
        </label>
        <?= field_error($errors, 'terms') ?>

        <button type="submit" class="w-full rounded-xl bg-brand-500 py-3 font-semibold text-white shadow-md shadow-brand-500/30 transition hover:bg-brand-600">
          Create account
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500">Already have an account?
        <a href="<?= url('login.php') ?>" class="font-semibold text-brand-600 hover:underline">Log in</a></p>
    </div>
  </section>
</main>

<script>
  // Show vulcator fields only for the "Fix tires" role.
  const providerFields = document.getElementById('provider-fields');
  document.querySelectorAll('input[name=role]').forEach((r) =>
    r.addEventListener('change', () => providerFields.classList.toggle('hidden', r.value !== 'provider' || !r.checked)));

  // Fill latitude/longitude from the phone's GPS.
  document.getElementById('locate-btn').addEventListener('click', () => {
    const status = document.getElementById('locate-status');
    if (!navigator.geolocation) { status.textContent = 'Your browser cannot share location. Type it in instead.'; return; }
    status.textContent = 'Getting your location...';
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        document.getElementById('latitude').value  = pos.coords.latitude.toFixed(7);
        document.getElementById('longitude').value = pos.coords.longitude.toFixed(7);
        status.textContent = 'Location set (accurate to about ' + Math.round(pos.coords.accuracy) + ' m).';
      },
      () => { status.textContent = 'Could not get your location. Allow location access, or type it in.'; },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
