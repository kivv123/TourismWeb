<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('tourism_session');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/functions.php';
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function logged_in(): bool
{
    return isset($_SESSION['user']['user_id']);
}
function require_login(): void
{
    if (!logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('Unauthorized access.');
    }
    // First-login flow: a hotel still using its temporary password is
    // redirected to the change-password page from every protected page.
    enforce_password_change();
}
function dashboard_path(string $role): string
{
    return $role . '/dashboard.php';
}
function refresh_session_user(int $id): void
{
    $s = db()->prepare('SELECT user_id,name,email,role,status,must_change_password FROM users WHERE user_id=?');
    $s->execute([$id]);
    if ($u = $s->fetch()) $_SESSION['user'] = $u;
}
function must_change_password(): bool
{
    return !empty($_SESSION['user']['must_change_password']);
}
function enforce_password_change(): void
{
    if (!must_change_password()) {
        return;
    }

    // The change-password page must never redirect to itself.
    $script = strtolower(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
    if ($script === 'change-password.php') {
        return;
    }

    // Only hotel accounts receive temporary passwords from the admin.
    if ((current_user()['role'] ?? '') === 'hotel') {
        redirect('hotel/change-password.php');
    }
}