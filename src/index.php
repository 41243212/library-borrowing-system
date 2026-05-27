<?php
require __DIR__ . '/config.php';

$user = require_login();

$stats = null;
$overdueCount = 0;

if ($user['role'] === 'admin') {
    $stats = db()->query(
        "SELECT
            COUNT(*) AS total_books,
            SUM(status = 'available') AS available_books,
            SUM(status = 'borrowed') AS borrowed_books,
            SUM(status = 'removed') AS removed_books
         FROM Y114_book"
    )->fetch();

    $overdueCount = db()->query(
        "SELECT COUNT(*) AS total
         FROM Y114_borrow_record
         WHERE status = 'borrowed' AND due_date < CURRENT_DATE"
    )->fetch()['total'];
}

if ($user['role'] === 'reader') {
    $activeStmt = db()->prepare(
        "SELECT r.*, b.title, b.author, DATEDIFF(CURRENT_DATE, r.due_date) AS overdue_days
         FROM Y114_borrow_record r
         JOIN Y114_book b ON b.book_id = r.book_id
         WHERE r.user_id = ? AND r.status = 'borrowed'
         ORDER BY r.due_date ASC"
    );
    $activeStmt->execute([(int) $user['user_id']]);
    $activeLoans = $activeStmt->fetchAll();

    $historyStmt = db()->prepare(
        "SELECT r.*, b.title, b.author
         FROM Y114_borrow_record r
         JOIN Y114_book b ON b.book_id = r.book_id
         WHERE r.user_id = ?
         ORDER BY r.borrow_date DESC, r.record_id DESC
         LIMIT 8"
    );
    $historyStmt->execute([(int) $user['user_id']]);
    $history = $historyStmt->fetchAll();
} else {
    $activeLoans = db()->query(
        "SELECT r.*, b.title, u.username, s.name AS student_name
         FROM Y114_borrow_record r
         JOIN Y114_book b ON b.book_id = r.book_id
         JOIN Y114_user u ON u.user_id = r.user_id
         LEFT JOIN Y114_student s ON s.student_id = u.student_id
         WHERE r.status = 'borrowed'
         ORDER BY r.due_date ASC
         LIMIT 10"
    )->fetchAll();
    $history = [];
}

$pageTitle = '首頁';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1><?= $user['role'] === 'admin' ? '管理總覽' : '我的借閱' ?></h1>
        <p class="muted">
            <?= $user['role'] === 'admin'
                ? '檢視館藏、借閱狀態與系統資料。'
                : '查詢目前借閱、歸還書籍與借閱歷史。' ?>
        </p>
    </div>
    <a class="button" href="/books.php">書籍查詢</a>
</section>

<?php if ($user['role'] === 'admin' && $stats): ?>
    <section class="grid stats">
        <div class="card">
            <span class="muted">館藏總數</span>
            <span class="stat-value"><?= (int) $stats['total_books'] ?></span>
        </div>
        <div class="card">
            <span class="muted">可借閱</span>
            <span class="stat-value"><?= (int) $stats['available_books'] ?></span>
        </div>
        <div class="card">
            <span class="muted">已借出</span>
            <span class="stat-value"><?= (int) $stats['borrowed_books'] ?></span>
        </div>
        <div class="card">
            <span class="muted">逾期未還</span>
            <span class="stat-value"><?= (int) $overdueCount ?></span>
        </div>
    </section>
<?php endif; ?>

<section class="card" style="margin-top: 18px;">
    <h2><?= $user['role'] === 'admin' ? '目前借閱狀態' : '目前借閱中' ?></h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>書名</th>
                <?php if ($user['role'] === 'admin'): ?><th>讀者</th><?php endif; ?>
                <th>借閱日</th>
                <th>應還日</th>
                <th>狀態</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$activeLoans): ?>
                <tr><td colspan="<?= $user['role'] === 'admin' ? 6 : 5 ?>">目前沒有借閱中的書籍。</td></tr>
            <?php endif; ?>
            <?php foreach ($activeLoans as $loan): ?>
                <?php $isOverdue = $loan['due_date'] < date('Y-m-d'); ?>
                <tr>
                    <td><?= h($loan['title']) ?></td>
                    <?php if ($user['role'] === 'admin'): ?>
                        <td><?= h($loan['student_name'] ?: $loan['username']) ?></td>
                    <?php endif; ?>
                    <td><?= h($loan['borrow_date']) ?></td>
                    <td><?= h($loan['due_date']) ?></td>
                    <td>
                        <span class="badge <?= $isOverdue ? 'removed' : 'borrowed' ?>">
                            <?= $isOverdue ? '逾期' : '借閱中' ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" action="/return.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="record_id" value="<?= (int) $loan['record_id'] ?>">
                            <button type="submit" class="secondary">歸還</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($user['role'] === 'reader'): ?>
    <section class="card" style="margin-top: 18px;">
        <h2>借閱紀錄</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>書名</th>
                    <th>借閱日</th>
                    <th>應還日</th>
                    <th>歸還日</th>
                    <th>狀態</th>
                    <th>罰款</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr><td colspan="6">尚無借閱紀錄。</td></tr>
                <?php endif; ?>
                <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?= h($record['title']) ?></td>
                        <td><?= h($record['borrow_date']) ?></td>
                        <td><?= h($record['due_date']) ?></td>
                        <td><?= h($record['return_date'] ?? '-') ?></td>
                        <td><span class="badge <?= h($record['status']) ?>"><?= h(status_label($record['status'])) ?></span></td>
                        <td>$<?= number_format((float) $record['fine_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
