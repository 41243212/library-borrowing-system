<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect_to('/index.php');
}

$form = [
    'student_no' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'department' => '',
    'username' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($form as $key => $_) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    try {
        if ($form['student_no'] === '' || $form['name'] === '' || $form['email'] === '' || $form['username'] === '') {
            throw new RuntimeException('學號、姓名、Email 與帳號為必填。');
        }

        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email 格式不正確。');
        }

        if (strlen($password) < 6) {
            throw new RuntimeException('密碼至少需要 6 個字元。');
        }

        if ($password !== $passwordConfirm) {
            throw new RuntimeException('兩次輸入的密碼不一致。');
        }

        $pdo = db();
        $pdo->beginTransaction();

        $studentStmt = $pdo->prepare(
            'INSERT INTO Y114_student (student_no, name, email, phone, department)
             VALUES (?, ?, ?, ?, ?)'
        );
        $studentStmt->execute([
            $form['student_no'],
            $form['name'],
            $form['email'],
            optional_text('phone'),
            optional_text('department'),
        ]);

        $studentId = (int) $pdo->lastInsertId();

        $userStmt = $pdo->prepare(
            "INSERT INTO Y114_user (student_id, username, password_hash, role, status)
             VALUES (?, ?, ?, 'reader', 'active')"
        );
        $userStmt->execute([
            $studentId,
            $form['username'],
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        $userId = (int) $pdo->lastInsertId();
        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        flash('success', '註冊成功，已自動登入。');
        redirect_to('/index.php');
    } catch (PDOException $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception->getCode() === '23000') {
            flash('error', '帳號、學號或 Email 已被使用。');
        } else {
            flash('error', '註冊失敗：' . $exception->getMessage());
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    }
}

$pageTitle = '註冊帳號';
require __DIR__ . '/partials/header.php';
?>

<section class="card register-panel">
    <div class="page-heading compact-heading">
        <div>
            <h1>註冊讀者帳號</h1>
            <p class="muted">建立讀者帳號後即可查詢、借閱與歸還書籍。</p>
        </div>
        <a class="button secondary" href="/login.php">返回登入</a>
    </div>

    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>
            學號
            <input name="student_no" value="<?= h($form['student_no']) ?>" autocomplete="off" required>
        </label>
        <label>
            姓名
            <input name="name" value="<?= h($form['name']) ?>" autocomplete="name" required>
        </label>
        <label>
            Email
            <input name="email" type="email" value="<?= h($form['email']) ?>" autocomplete="email" required>
        </label>
        <label>
            電話
            <input name="phone" value="<?= h($form['phone']) ?>" autocomplete="tel">
        </label>
        <label>
            系所
            <input name="department" value="<?= h($form['department']) ?>">
        </label>
        <label>
            帳號
            <input name="username" value="<?= h($form['username']) ?>" autocomplete="username" required>
        </label>
        <label>
            密碼
            <input name="password" type="password" autocomplete="new-password" minlength="6" required>
        </label>
        <label>
            確認密碼
            <input name="password_confirm" type="password" autocomplete="new-password" minlength="6" required>
        </label>
        <div class="form-actions">
            <button type="submit">建立帳號</button>
            <a class="button secondary" href="/login.php">取消</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
