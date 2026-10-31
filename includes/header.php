<?php
// Usage: $pageTitle = 'Login'; $bodyClass = '...'; $headExtra = '<script>..</script>'; require 'includes/header.php';
$pageTitle = isset($pageTitle) ? $pageTitle . ' · Vulcatic' : 'Vulcatic';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#E8501E">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" href="<?= url('assets/img/favicon.png') ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= url('assets/img/apple-touch-icon.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Built from tailwind/input.css. Rebuild after adding classes (see README). -->
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
<?= $headExtra ?? '' ?>
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased <?= e($bodyClass ?? '') ?>">
<?php foreach (take_flashes() as $f): ?>
  <div class="flash fixed inset-x-4 top-4 z-50 mx-auto max-w-md rounded-xl px-4 py-3 text-sm font-medium shadow-lg
              <?= $f['type'] === 'error' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-green-50 text-green-700 ring-1 ring-green-200' ?>"
       role="status">
    <?= e($f['message']) ?>
  </div>
<?php endforeach; ?>
