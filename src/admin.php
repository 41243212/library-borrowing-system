<?php
require __DIR__ . '/config.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('分類名稱不可空白。');
            }
            $stmt = db()->prepare('INSERT INTO Y114_category (name) VALUES (?)');
            $stmt->execute([$name]);
            flash('success', '分類已新增。');
        }

        if ($action === 'remove_book' || $action === 'restore_book') {
            $bookId = (int) ($_POST['book_id'] ?? 0);

            if ($action === 'remove_book') {
                $activeStmt = db()->prepare("SELECT COUNT(*) FROM Y114_borrow_record WHERE book_id = ? AND status = 'borrowed'");
                $activeStmt->execute([$bookId]);
                if ((int) $activeStmt->fetchColumn() > 0) {
                    throw new RuntimeException('此書仍在借閱中，無法下架。');
                }
                $status = 'removed';
                $message = '書籍已下架。';
            } else {
                $status = 'available';
                $message = '書籍已恢復可借閱。';
            }

            $stmt = db()->prepare('UPDATE Y114_book SET status = ? WHERE book_id = ?');
            $stmt->execute([$status, $bookId]);
            flash('success', $message);
        }

        if ($action === 'save_reader') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $studentNo = trim((string) ($_POST['student_no'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = optional_text('phone');
            $department = optional_text('department');
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $status = (string) ($_POST['status'] ?? 'active');

            if ($studentNo === '' || $name === '' || $email === '' || $username === '') {
                throw new RuntimeException('學號、姓名、Email 與帳號為必填。');
            }

            if (!in_array($status, ['active', 'disabled'], true)) {
                throw new RuntimeException('帳號狀態不正確。');
            }

            $pdo = db();
            $pdo->beginTransaction();

            if ($userId > 0) {
                $existingStmt = $pdo->prepare("SELECT * FROM Y114_user WHERE user_id = ? AND role = 'reader' FOR UPDATE");
                $existingStmt->execute([$userId]);
                $existing = $existingStmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('找不到讀者帳號。');
                }

                $studentStmt = $pdo->prepare(
                    'UPDATE Y114_student
                     SET student_no = ?, name = ?, email = ?, phone = ?, department = ?
                     WHERE student_id = ?'
                );
                $studentStmt->execute([$studentNo, $name, $email, $phone, $department, (int) $existing['student_id']]);

                $userSql = 'UPDATE Y114_user SET username = ?, status = ?';
                $params = [$username, $status];
                if ($password !== '') {
                    $userSql .= ', password_hash = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $userSql .= ' WHERE user_id = ?';
                $params[] = $userId;
                $userStmt = $pdo->prepare($userSql);
                $userStmt->execute($params);

                $pdo->commit();
                flash('success', '讀者資料已更新。');
            } else {
                if ($password === '') {
                    throw new RuntimeException('新增讀者必須設定密碼。');
                }

                $studentStmt = $pdo->prepare(
                    'INSERT INTO Y114_student (student_no, name, email, phone, department)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $studentStmt->execute([$studentNo, $name, $email, $phone, $department]);
                $studentId = (int) $pdo->lastInsertId();

                $userStmt = $pdo->prepare(
                    "INSERT INTO Y114_user (student_id, username, password_hash, role, status)
                     VALUES (?, ?, ?, 'reader', ?)"
                );
                $userStmt->execute([$studentId, $username, password_hash($password, PASSWORD_DEFAULT), $status]);

                $pdo->commit();
                flash('success', '讀者帳號已新增。');
            }
        }
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', '處理失敗：' . $exception->getMessage());
    }

    redirect_to('/admin.php');
}

$books = db()->query(
    "SELECT b.*, c.name AS category_name
     FROM Y114_book b
     JOIN Y114_category c ON c.category_id = b.category_id
     ORDER BY b.status = 'removed', b.title"
)->fetchAll();

$readers = db()->query(
    "SELECT u.*, s.student_no, s.name, s.email, s.phone, s.department
     FROM Y114_user u
     JOIN Y114_student s ON s.student_id = u.student_id
     WHERE u.role = 'reader'
     ORDER BY s.student_no"
)->fetchAll();

$activeLoans = db()->query(
    "SELECT r.*, b.title, u.username, s.name AS student_name
     FROM Y114_borrow_record r
     JOIN Y114_book b ON b.book_id = r.book_id
     JOIN Y114_user u ON u.user_id = r.user_id
     LEFT JOIN Y114_student s ON s.student_id = u.student_id
     WHERE r.status = 'borrowed'
     ORDER BY r.due_date ASC"
)->fetchAll();

$pageTitle = '管理後台';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1>管理後台</h1>
        <p class="muted">管理館藏、分類、讀者帳號與借閱狀態。</p>
    </div>
    <a class="button" href="/reports.php">查看報表</a>
</section>

<section class="grid two-column">
    <div class="card">
        <h2>書籍管理</h2>
        <p class="muted">新增書籍與修改書籍已分開處理。要修改既有書籍，請從下方書籍清單直接進入該書的修改頁。</p>
        <div class="inline-actions">
            <a class="button" href="/book_new.php">新增書籍</a>
        </div>
    </div>

    <div class="card">
        <h2>新增分類</h2>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_category">
            <label>
                分類名稱
                <input name="name" required>
            </label>
            <button type="submit">新增分類</button>
        </form>
    </div>
</section>

<section class="card" style="margin-top: 18px;">
    <h2>書籍管理</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>書名</th>
                <th>作者</th>
                <th>分類</th>
                <th>狀態</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?= h($book['title']) ?></td>
                    <td><?= h($book['author']) ?></td>
                    <td><?= h($book['category_name']) ?></td>
                    <td><span class="badge <?= h($book['status']) ?>"><?= h(status_label($book['status'])) ?></span></td>
                    <td>
                        <div class="inline-actions">
                            <a class="button secondary" href="/book_edit.php?book_id=<?= (int) $book['book_id'] ?>">修改</a>
                        <?php if ($book['status'] === 'removed'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="restore_book">
                                <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                                <button type="submit" class="secondary">恢復</button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_book">
                                <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                                <button type="submit" class="danger">下架</button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card" style="margin-top: 18px;">
    <h2>讀者管理</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_reader">
        <label>
            修改讀者
            <select name="user_id">
                <option value="0">新增讀者</option>
                <?php foreach ($readers as $reader): ?>
                    <option value="<?= (int) $reader['user_id'] ?>"><?= h($reader['student_no'] . ' ' . $reader['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            學號
            <input name="student_no" required>
        </label>
        <label>
            姓名
            <input name="name" required>
        </label>
        <label>
            Email
            <input name="email" type="email" required>
        </label>
        <label>
            電話
            <input name="phone">
        </label>
        <label>
            系所
            <input name="department">
        </label>
        <label>
            帳號
            <input name="username" required>
        </label>
        <label>
            密碼
            <input name="password" type="password">
        </label>
        <label>
            狀態
            <select name="status">
                <option value="active">啟用</option>
                <option value="disabled">停用</option>
            </select>
        </label>
        <button type="submit">儲存讀者</button>
    </form>

    <div class="table-wrap" style="margin-top: 16px;">
        <table>
            <thead>
            <tr>
                <th>學號</th>
                <th>姓名</th>
                <th>帳號</th>
                <th>Email</th>
                <th>狀態</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($readers as $reader): ?>
                <tr>
                    <td><?= h($reader['student_no']) ?></td>
                    <td><?= h($reader['name']) ?></td>
                    <td><?= h($reader['username']) ?></td>
                    <td><?= h($reader['email']) ?></td>
                    <td><span class="badge <?= h($reader['status']) ?>"><?= h(status_label($reader['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card" style="margin-top: 18px;">
    <h2>借閱管理</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>書名</th>
                <th>讀者</th>
                <th>借閱日</th>
                <th>應還日</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$activeLoans): ?>
                <tr><td colspan="5">目前沒有借閱中的書籍。</td></tr>
            <?php endif; ?>
            <?php foreach ($activeLoans as $loan): ?>
                <tr>
                    <td><?= h($loan['title']) ?></td>
                    <td><?= h($loan['student_name'] ?: $loan['username']) ?></td>
                    <td><?= h($loan['borrow_date']) ?></td>
                    <td><?= h($loan['due_date']) ?></td>
                    <td>
                        <form method="post" action="/return.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="record_id" value="<?= (int) $loan['record_id'] ?>">
                            <button type="submit" class="secondary">強制歸還</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
