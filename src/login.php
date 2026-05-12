<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect_to('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM Y114_user WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        flash('success', '登入成功。');
        redirect_to('/index.php');
    }

    flash('error', '帳號或密碼錯誤，或帳號已停用。');
}

$pageTitle = '登入';
require __DIR__ . '/partials/header.php';
?>

<section class="card login-panel">
    <h1>登入系統</h1>
    <p class="muted">Demo 管理員：admin / admin123；Demo 讀者：reader / reader123</p>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <label>
            帳號
            <input name="username" autocomplete="username" required>
        </label>
        <label>
            密碼
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button type="submit">登入</button>
    </form>
    <p class="auth-switch muted">還沒有帳號？<a href="/register.php">註冊讀者帳號</a></p>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
