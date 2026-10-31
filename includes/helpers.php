<?php
// Small helpers used by every page.

// Escape output for HTML.
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Build a link inside the app: url('login.php') -> /vulcatic/login.php
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

// Folder the site is served from, e.g. '/vulcatic/htdocs'.
// 'auto' works it out from the current page, since every page sits in htdocs/.
function base_url(): string
{
    global $config;
    $base = $config['app']['base_url'] ?? 'auto';
    if ($base === 'auto') {
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    }
    return rtrim($base, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// One-time messages shown on the next page ("Account created!").
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// CSRF protection for every POST form.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Go back, refresh the page and try again.');
    }
}

// Keep what the user typed when a form has errors.
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

// Accepts 09171234567, +639171234567 or 639171234567; returns 09171234567 or null.
function normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (str_starts_with($digits, '63')) {
        $digits = '0' . substr($digits, 2);
    }
    return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
}

// The gecko mascot's head, used as the logo everywhere.
function logo_img(string $class = 'h-10 w-10'): string
{
    return '<img src="' . e(url('assets/img/gecko-head.png')) . '" alt="" class="' . e($class) . ' object-contain" width="256" height="256">';
}

// The whole gecko, standing. Size it by height, e.g. 'h-14'.
function logo_full(string $class = 'h-14'): string
{
    return '<img src="' . e(url('assets/img/gecko-small.webp')) . '" alt="" class="' . e($class) . ' w-auto" width="124" height="192">';
}

// "Jose Dela Cruz" -> "JD" for avatar circles.
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0];
}

function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}
