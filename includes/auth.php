<?php
// Login state and page guards.

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, role, full_name, email, phone, avatar_url, must_change_password, motorist_type, employer_provider_id, on_duty
                                   FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

// Where each role lands after logging in.
function home_for(array $user): string
{
    return match ($user['role']) {
        'provider' => 'dashboard.php',
        'admin'    => 'admin.php',
        'employee' => 'employee.php',
        default    => 'home.php',
    };
}

// Put at the top of pages that need a logged-in user, e.g. require_login('provider').
function require_login(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }
    if ($user['must_change_password'] && basename($_SERVER['SCRIPT_NAME']) !== 'change_password.php') {
        redirect('change_password.php');
    }
    if ($role !== null && $user['role'] !== $role) {
        redirect(home_for($user));
    }
    return $user;
}
