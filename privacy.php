<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/legal_layout.php';
$pageTitle = 'Privacy Policy';
legal_start('Privacy Policy', 'October 31, 2026');
?>
<p>Vulcatic connects people on Siargao Island who need tire help with nearby vulcanizing shops and skilled locals.
   This policy explains what personal information we collect, why, and your rights under the
   <b>Data Privacy Act of 2012 (Republic Act No. 10173)</b>.</p>

<h2>What we collect</h2>
<ul>
  <li><b>Account details:</b> your name, mobile number, optional email, and password (stored only as a secure hash).</li>
  <li><b>Location:</b> where you ask for help, and for vulcators, their position while online or on a job.
      We only read your location when you tap a location button or use a live screen.</li>
  <li><b>Requests and payments:</b> service, vehicle, landmark and notes you enter, job status and times, amount,
      payment method and GCash reference number.</li>
  <li><b>Ratings</b> and comments you leave.</li>
  <li><b>Vulcator details:</b> shop name, address, opening hours, services and prices, and GCash number.</li>
</ul>

<h2>Why we use it</h2>
<ul>
  <li>To find vulcators near you and show them your request, location and landmark.</li>
  <li>To let the customer and vulcator see each other's name and mobile number during a job, so you can call or text.</li>
  <li>To keep receipts, earnings and ratings, and to prevent fraud and misuse.</li>
  <li>To save the list of shops on your phone for offline use.</li>
</ul>
<p>We do <b>not</b> sell your information or use it for advertising.</p>

<h2>Who can see it</h2>
<ul>
  <li><b>Nearby vulcators</b> see your first request details (service, vehicle, landmark, distance). Once one accepts,
      they see your name, mobile number and exact pin until the job ends.</li>
  <li><b>Customers</b> see an approved vulcator's name, mobile number, rating, shop location and GCash number.</li>
  <li><b>Vulcatic admins</b> see account and job records to approve vulcators, handle password resets and resolve problems.</li>
  <li><b>Service providers</b> that run the website (web hosting) and the map (OpenStreetMap tiles, which receive
      your device's internet address when the map loads).</li>
</ul>

<h2>How long we keep it</h2>
<p>We keep your account while it is active. Job records and receipts are kept for up to 5 years for accounting and
   dispute purposes. Failed-login records are deleted after one day. You can ask us to delete your account at any time.</p>

<h2>Your rights</h2>
<p>You have the right to be informed, to access and correct your information, to object, to ask for it to be deleted or
   blocked, to data portability, and to file a complaint with the <b>National Privacy Commission</b> (privacy.gov.ph).
   You can update most details yourself on the Profile page. For anything else, contact <?= legal_contact() ?>.</p>

<h2>Security</h2>
<p>Passwords are hashed, the site uses HTTPS when published, logins are protected against repeated guessing,
   and only admins can see account records.</p>

<h2>Changes</h2>
<p>If we change this policy in an important way, we'll show a notice in the app.</p>
<?php legal_end();
