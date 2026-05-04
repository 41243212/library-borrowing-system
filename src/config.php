<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Taipei');

const LOAN_LIMIT = 3;
const LOAN_DAYS = 14;
const FINE_PER_DAY = 5;

function env_value(string $key, string $default): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('DB_HOST', 'db');
    $port = env_value('DB_PORT', '3306');
    $database = env_value('DB_DATABASE', 'csieDBTeam14');
    $username = env_value('DB_USERNAME', 'csie_user');
    $password = env_value('DB_PASSWORD', 'csiePassword14');

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function optional_text(string $key, ?array $source = null): ?string
{
    $source ??= $_POST;
    $value = trim((string) ($source[$key] ?? ''));
    return $value === '' ? null : $value;
}

function redirect_to(string $path): never
{
    header("Location: {$path}");
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        flash('error', '表單驗證失敗，請重新送出。');
        redirect_to('/index.php');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_user(): ?array
{
    static $cached = false;

    if ($cached !== false) {
        return $cached;
    }

    if (empty($_SESSION['user_id'])) {
        $cached = null;
        return null;
    }

    $stmt = db()->prepare(
        "SELECT u.*, s.student_no, s.name AS student_name, s.email, s.phone, s.department
         FROM Y114_user u
         LEFT JOIN Y114_student s ON s.student_id = u.student_id
         WHERE u.user_id = ?"
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        session_destroy();
        $cached = null;
        return null;
    }

    $cached = $user;
    return $cached;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', '請先登入。');
        redirect_to('/login.php');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('error', '此功能限管理員使用。');
        redirect_to('/index.php');
    }

    return $user;
}

function status_label(string $status): string
{
    return [
        'available' => '可借閱',
        'borrowed' => '已借出',
        'removed' => '已下架',
        'returned' => '已歸還',
        'active' => '啟用',
        'disabled' => '停用',
    ][$status] ?? $status;
}

function role_label(string $role): string
{
    return $role === 'admin' ? '管理員' : '讀者';
}
