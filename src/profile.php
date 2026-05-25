<?php
require __DIR__ . '/config.php';

$user = require_login();

$profileForm = [
    'username' => $user['username'] ?? '',
    'student_no' => $user['student_no'] ?? '',
    'name' => $user['student_name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'department' => $user['department'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_profile') {
            $profileForm['username'] = trim((string) ($_POST['username'] ?? ''));
            $profileForm['name'] = trim((string) ($_POST['name'] ?? ''));
            $profileForm['email'] = trim((string) ($_POST['email'] ?? ''));
            $profileForm['phone'] = trim((string) ($_POST['phone'] ?? ''));
            $profileForm['department'] = trim((string) ($_POST['department'] ?? ''));

            if ($profileForm['username'] === '') {
                throw new RuntimeException('帳號不可空白。');
            }

            if ((int) ($user['student_id'] ?? 0) > 0) {
                if ($profileForm['name'] === '' || $profileForm['email'] === '') {
                    throw new RuntimeException('姓名與 Email 為必填。');
                }

                if (!filter_var($profileForm['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Email 格式不正確。');
                }
            }

            $pdo = db();
            $pdo->beginTransaction();

            $userStmt = $pdo->prepare('UPDATE Y114_user SET username = ? WHERE user_id = ?');
            $userStmt->execute([$profileForm['username'], (int) $user['user_id']]);

            if ((int) ($user['student_id'] ?? 0) > 0) {
                $studentStmt = $pdo->prepare(
                    'UPDATE Y114_student
                     SET name = ?, email = ?, phone = ?, department = ?
                     WHERE student_id = ?'
                );
                $studentStmt->execute([
                    $profileForm['name'],
                    $profileForm['email'],
                    optional_text('phone'),
                    optional_text('department'),
                    (int) $user['student_id'],
                ]);
            }

            $pdo->commit();
            flash('success', '個人資料已更新。');
            redirect_to('/profile.php');
        }

        if ($action === 'update_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

            if (!password_verify($currentPassword, $user['password_hash'])) {
                throw new RuntimeException('目前密碼不正確。');
            }

            if (strlen($newPassword) < 6) {
                throw new RuntimeException('新密碼至少需要 6 個字元。');
            }

            if ($newPassword !== $newPasswordConfirm) {
                throw new RuntimeException('兩次輸入的新密碼不一致。');
            }

            $stmt = db()->prepare('UPDATE Y114_user SET password_hash = ? WHERE user_id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['user_id']]);

            flash('success', '密碼已更新。');
            redirect_to('/profile.php');
        }

        throw new RuntimeException('操作不正確。');
    } catch (PDOException $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception->getCode() === '23000') {
            flash('error', '帳號或 Email 已被使用。');
        } else {
            flash('error', '更新失敗：' . $exception->getMessage());
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    }
}

$pageTitle = '個人資料';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1>個人資料</h1>
        <p class="muted">更新聯絡資料、帳號名稱與登入密碼。</p>
    </div>
    <a class="button secondary" href="/index.php">返回首頁</a>
</section>

<section class="grid profile-layout">
    <div class="card">
        <h2>基本資料</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <label>
                帳號
                <input name="username" value="<?= h($profileForm['username']) ?>" autocomplete="username" required>
            </label>
            <label class="readonly-field">
                身分
                <input value="<?= h(role_label($user['role'])) ?>" disabled>
            </label>
            <?php if ((int) ($user['student_id'] ?? 0) > 0): ?>
                <label class="readonly-field">
                    學號
                    <input value="<?= h($profileForm['student_no']) ?>" disabled>
                </label>
                <label>
                    姓名
                    <input name="name" value="<?= h($profileForm['name']) ?>" autocomplete="name" required>
                </label>
                <label>
                    Email
                    <input name="email" type="email" value="<?= h($profileForm['email']) ?>" autocomplete="email" required>
                </label>
                <label>
                    電話
                    <input name="phone" value="<?= h($profileForm['phone']) ?>" autocomplete="tel">
                </label>
                <label>
                    系所
                    <input name="department" value="<?= h($profileForm['department']) ?>">
                </label>
            <?php else: ?>
                <p class="form-note muted">此帳號未連結學生資料，因此此處只可修改帳號名稱與密碼。</p>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit">儲存資料</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>修改密碼</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_password">
            <label>
                目前密碼
                <input name="current_password" type="password" autocomplete="current-password" required>
            </label>
            <label>
                新密碼
                <input name="new_password" type="password" autocomplete="new-password" minlength="6" required>
            </label>
            <label>
                確認新密碼
                <input name="new_password_confirm" type="password" autocomplete="new-password" minlength="6" required>
            </label>
            <button type="submit">更新密碼</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
