<?php
require __DIR__ . '/includes/bootstrap.php';

// POST only, so another site can't log people out with a link.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

logout_user();
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
