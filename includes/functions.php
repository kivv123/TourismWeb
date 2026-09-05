<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}
function money(float|string $amount): string
{
    return number_format((float)$amount, 0) . ' MMK';
}
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid form token. Please go back and try again.');
    }
}
function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}
function days_between(string $start, string $end): int
{
    return (int)((new DateTime($start))->diff(new DateTime($end))->format('%r%a'));
}
function status_badge(string $status): string
{
    $classes = ['Pending' => 'warning text-dark', 'Confirmed' => 'success', 'Completed' => 'primary', 'Cancelled' => 'danger', 'Paid' => 'success', 'Failed' => 'danger', 'Refunded' => 'secondary', 'Assigned' => 'info text-dark', 'Accepted' => 'primary', 'On the Way' => 'info text-dark', 'Picked Up' => 'info text-dark'];
    return '<span class="badge bg-' . ($classes[$status] ?? 'secondary') . '">' . e($status) . '</span>';
}
function upload_image(string $field, string $folder): ?string
{
    if (
        empty($_FILES[$field]['name']) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Image must not exceed 3 MB.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }

    $uploadDirectory = __DIR__ . '/../uploads/' . $folder . '/';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Unable to create the image upload folder.');
        }
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('The image upload folder is not writable.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDirectory . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save image.');
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function get_entity_id(string $role, int $userId): ?int
{
    $table = $role === 'customer' ? 'customers' : ($role === 'driver' ? 'drivers' : 'hotels');
    $col = substr($table, 0, -1) . '_id';
    $s = db()->prepare("SELECT $col FROM $table WHERE user_id=?");
    $s->execute([$userId]);
    return ($v = $s->fetchColumn()) ? (int)$v : null;
}
