<?php
require __DIR__ . '/config.php';

require_admin();

$bookId = filter_input(INPUT_GET, 'book_id', FILTER_VALIDATE_INT);
if (!$bookId || $bookId <= 0) {
    flash('error', '書籍編號不正確。');
    redirect_to('/admin.php');
}

$stmt = db()->prepare(
    "SELECT b.*, c.name AS category_name
     FROM Y114_book b
     JOIN Y114_category c ON c.category_id = b.category_id
     WHERE b.book_id = ?"
);
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    flash('error', '找不到書籍資料。');
    redirect_to('/admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $isbn = optional_text('isbn');
    $title = trim((string) ($_POST['title'] ?? ''));
    $author = trim((string) ($_POST['author'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $year = optional_text('publication_year');

    try {
        if ($title === '' || $author === '' || $categoryId <= 0) {
            throw new RuntimeException('書名、作者與分類為必填。');
        }

        $update = db()->prepare(
            'UPDATE Y114_book
             SET isbn = ?, title = ?, author = ?, category_id = ?, publication_year = ?
             WHERE book_id = ?'
        );
        $update->execute([$isbn, $title, $author, $categoryId, $year, $bookId]);

        flash('success', '書籍資料已更新。');
        redirect_to('/admin.php');
    } catch (Throwable $exception) {
        flash('error', '更新失敗：' . $exception->getMessage());
        $book = array_merge($book, [
            'isbn' => $isbn,
            'title' => $title,
            'author' => $author,
            'category_id' => $categoryId,
            'publication_year' => $year,
        ]);
    }
}

$categories = db()->query('SELECT * FROM Y114_category ORDER BY name')->fetchAll();

$pageTitle = '修改書籍';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1>修改書籍</h1>
        <p class="muted">目前修改：<?= h($book['title']) ?></p>
    </div>
    <a class="button secondary" href="/admin.php">返回管理後台</a>
</section>

<section class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>
            ISBN
            <input name="isbn" value="<?= h($book['isbn'] ?? '') ?>">
        </label>
        <label>
            書名
            <input name="title" value="<?= h($book['title']) ?>" required>
        </label>
        <label>
            作者
            <input name="author" value="<?= h($book['author']) ?>" required>
        </label>
        <label>
            分類
            <select name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <?php $selected = (int) $book['category_id'] === (int) $category['category_id']; ?>
                    <option value="<?= (int) $category['category_id'] ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            出版年份
            <input name="publication_year" type="number" min="1" max="<?= (int) date('Y') + 1 ?>" value="<?= h($book['publication_year'] ?? '') ?>">
        </label>
        <label>
            目前狀態
            <input value="<?= h(status_label($book['status'])) ?>" disabled>
        </label>
        <div class="form-actions">
            <button type="submit">儲存修改</button>
            <a class="button secondary" href="/admin.php">取消</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
