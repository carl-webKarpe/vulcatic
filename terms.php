<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/legal_layout.php';
$pageTitle = 'Terms of Use';
legal_start('Terms of Use', 'October 31, 2026');
?>
<p>By creating an account or using Vulcatic, you agree to these terms. Please read them with our
   <a class="text-brand-600 underline" href="<?= url('privacy.php') ?>">Privacy Policy</a>.</p>

<h2>What Vulcatic is</h2>
<p>Vulcatic is a platform that connects customers on Siargao Island with independent vulcanizing shops and skilled
   locals ("vulcators"). Vulcators are <b>not</b> employees of Vulcatic. The repair agreement is between the customer
   and the vulcator.</p>

<h2>Customers</h2>
<ul>
  <li>Give an accurate location and description, and be reachable on your mobile number while waiting.</li>
  <li>Pay the vulcator the amount shown when the job is done, in cash or by GCash.</li>
  <li>Cancel as early as possible if you no longer need help.</li>
</ul>

<h2>Vulcators</h2>
<ul>
  <li>Give true information about yourself, your shop and your skills. Vulcatic may verify it before approving you.</li>
  <li>Only accept jobs you can do, go to the customer promptly, work safely, and charge the price shown in the app.</li>
  <li>Confirm a payment only after you actually received it.</li>
  <li>A platform fee (currently 5% of the service fee) is recorded for each paid job.</li>
</ul>

<h2>Everyone</h2>
<ul>
  <li>Be respectful. No harassment, fake requests, fake ratings or misuse of other people's contact details.</li>
  <li>Keep your password private. You are responsible for activity on your account.</li>
  <li>Vulcatic may suspend accounts that break these terms or put others at risk.</li>
</ul>

<h2>Safety and limits</h2>
<p>Roadside repairs carry risks. Stay in a safe spot away from traffic while you wait. Vulcatic does its best to keep the
   service running, but cannot guarantee that a vulcator is always available, that the map or location is exact, or
   that the app works without signal beyond the saved shop list. To the extent allowed by law, Vulcatic is not liable for
   the quality of repairs or for losses caused by vulcators or customers.</p>

<h2>Changes and contact</h2>
<p>We may update these terms and will show a notice in the app when we do. Questions: <?= legal_contact() ?>.</p>
<?php legal_end();
