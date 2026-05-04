<?php
require __DIR__ . '/config.php';

require_admin();

$popularBooks = db()->query(
    "SELECT b.title, b.author, COUNT(r.record_id) AS borrow_count
     FROM Y114_book b
     LEFT JOIN Y114_borrow_record r ON r.book_id = b.book_id
     GROUP BY b.book_id, b.title, b.author
     ORDER BY borrow_count DESC, b.title ASC
     LIMIT 10"
)->fetchAll();

$categoryUsage = db()->query(
    "SELECT c.name, COUNT(DISTINCT b.book_id) AS book_count, COUNT(r.record_id) AS borrow_count
     FROM Y114_category c
     LEFT JOIN Y114_book b ON b.category_id = c.category_id
     LEFT JOIN Y114_borrow_record r ON r.book_id = b.book_id
     GROUP BY c.category_id, c.name
     ORDER BY borrow_count DESC, c.name ASC"
)->fetchAll();

$overdueRows = db()->query(
    "SELECT r.record_id, r.borrow_date, r.due_date,
            DATEDIFF(CURRENT_DATE, r.due_date) AS overdue_days,
            b.title, u.username, s.name AS student_name
     FROM Y114_borrow_record r
     JOIN Y114_book b ON b.book_id = r.book_id
     JOIN Y114_user u ON u.user_id = r.user_id
     LEFT JOIN Y114_student s ON s.student_id = u.student_id
     WHERE r.status = 'borrowed' AND r.due_date < CURRENT_DATE
     ORDER BY r.due_date ASC"
)->fetchAll();

$monthlyRows = db()->query(
    "SELECT DATE_FORMAT(borrow_date, '%Y-%m') AS month, COUNT(*) AS borrow_count
     FROM Y114_borrow_record
     GROUP BY DATE_FORMAT(borrow_date, '%Y-%m')
     ORDER BY month DESC
     LIMIT 12"
)->fetchAll();

$pageTitle = '報表';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1>報表</h1>
        <p class="muted">熱門書籍、分類使用率、逾期未還與月借閱統計。</p>
    </div>
    <a class="button secondary" href="/admin.php">返回管理後台</a>
</section>

<section class="grid two-column">
    <div class="card">
        <h2>借閱排行榜</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>書名</th>
                    <th>作者</th>
                    <th>借閱次數</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($popularBooks as $row): ?>
                    <tr>
                        <td><?= h($row['title']) ?></td>
                        <td><?= h($row['author']) ?></td>
                        <td><?= (int) $row['borrow_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>月借閱統計</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>月份</th>
                    <th>借閱次數</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($monthlyRows as $row): ?>
                    <tr>
                        <td><?= h($row['month']) ?></td>
                        <td><?= (int) $row['borrow_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card" style="margin-top: 18px;">
    <h2>書籍使用率統計</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>分類</th>
                <th>館藏數</th>
                <th>借閱次數</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categoryUsage as $row): ?>
                <tr>
                    <td><?= h($row['name']) ?></td>
                    <td><?= (int) $row['book_count'] ?></td>
                    <td><?= (int) $row['borrow_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card" style="margin-top: 18px;">
    <h2>逾期統計報表</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>書名</th>
                <th>讀者</th>
                <th>借閱日</th>
                <th>應還日</th>
                <th>逾期天數</th>
                <th>預估罰款</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$overdueRows): ?>
                <tr><td colspan="6">目前沒有逾期未還書籍。</td></tr>
            <?php endif; ?>
            <?php foreach ($overdueRows as $row): ?>
                <?php $fine = (int) $row['overdue_days'] * FINE_PER_DAY; ?>
                <tr>
                    <td><?= h($row['title']) ?></td>
                    <td><?= h($row['student_name'] ?: $row['username']) ?></td>
                    <td><?= h($row['borrow_date']) ?></td>
                    <td><?= h($row['due_date']) ?></td>
                    <td><?= (int) $row['overdue_days'] ?></td>
                    <td>$<?= number_format($fine, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
