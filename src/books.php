<?php
require __DIR__ . '/config.php';

$user = require_login();

$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category_id'] ?? 0);

$categories = db()->query('SELECT * FROM Y114_category ORDER BY name')->fetchAll();

$conditions = ["b.status <> 'removed'"];
$params = [];

if ($q !== '') {
    $conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR c.name LIKE ? OR b.isbn LIKE ?)";
    $keyword = "%{$q}%";
    array_push($params, $keyword, $keyword, $keyword, $keyword);
}

if ($categoryId > 0) {
    $conditions[] = 'b.category_id = ?';
    $params[] = $categoryId;
}

$sql = "SELECT b.*, c.name AS category_name
        FROM Y114_book b
        JOIN Y114_category c ON c.category_id = b.category_id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY b.title ASC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$activeLoanCount = 0;
if ($user['role'] === 'reader') {
    $countStmt = db()->prepare("SELECT COUNT(*) FROM Y114_borrow_record WHERE user_id = ? AND status = 'borrowed'");
    $countStmt->execute([(int) $user['user_id']]);
    $activeLoanCount = (int) $countStmt->fetchColumn();
}

$pageTitle = '書籍查詢';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1>書籍查詢</h1>
        <p class="muted">可依書名、作者、分類或 ISBN 搜尋，並查看目前可借閱狀態。</p>
    </div>
    <?php if ($user['role'] === 'reader'): ?>
        <span class="badge warning">目前借閱 <?= $activeLoanCount ?> / <?= LOAN_LIMIT ?> 本</span>
    <?php endif; ?>
</section>

<section class="card">
    <form method="get" class="toolbar">
        <label>
            關鍵字
            <input name="q" value="<?= h($q) ?>" placeholder="書名、作者、分類、ISBN">
        </label>
        <label>
            分類
            <select name="category_id">
                <option value="0">全部分類</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>" <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">搜尋</button>
        <a class="button secondary" href="/books.php">清除</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ISBN</th>
                <th>書名</th>
                <th>作者</th>
                <th>分類</th>
                <th>年份</th>
                <th>狀態</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$books): ?>
                <tr><td colspan="7">查無書籍。</td></tr>
            <?php endif; ?>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?= h($book['isbn'] ?? '-') ?></td>
                    <td><?= h($book['title']) ?></td>
                    <td><?= h($book['author']) ?></td>
                    <td><?= h($book['category_name']) ?></td>
                    <td><?= h((string) ($book['publication_year'] ?? '-')) ?></td>
                    <td><span class="badge <?= h($book['status']) ?>"><?= h(status_label($book['status'])) ?></span></td>
                    <td>
                        <?php if ($user['role'] === 'reader' && $book['status'] === 'available'): ?>
                            <form method="post" action="/borrow.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                                <button type="submit" <?= $activeLoanCount >= LOAN_LIMIT ? 'disabled' : '' ?>>借閱</button>
                            </form>
                        <?php elseif ($book['status'] === 'borrowed'): ?>
                            <span class="muted">不可借</span>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
