<?php
require __DIR__ . '/config.php';

require_admin();

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

        $stmt = db()->prepare(
            'INSERT INTO Y114_book (isbn, title, author, category_id, publication_year)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$isbn, $title, $author, $categoryId, $year]);

        flash('success', '書籍已新增。');
        redirect_to('/admin.php');
    } catch (Throwable $exception) {
        flash('error', '新增失敗：' . $exception->getMessage());
    }
}

$categories = db()->query('SELECT * FROM Y114_category ORDER BY name')->fetchAll();

$pageTitle = '新增書籍';
require __DIR__ . '/partials/header.php';
?>

<section class="page-heading">
    <div>
        <h1>新增書籍</h1>
        <p class="muted">建立新的館藏書籍資料。</p>
    </div>
    <a class="button secondary" href="/admin.php">返回管理後台</a>
</section>

<section class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label>
            ISBN
            <input name="isbn" value="<?= h($_POST['isbn'] ?? '') ?>">
        </label>
        <label>
            書名
            <input name="title" value="<?= h($_POST['title'] ?? '') ?>" required>
        </label>
        <label>
            作者
            <input name="author" value="<?= h($_POST['author'] ?? '') ?>" required>
        </label>
        <label>
            分類
            <select name="category_id" required>
                <option value="">請選擇分類</option>
                <?php foreach ($categories as $category): ?>
                    <?php $selected = (int) ($_POST['category_id'] ?? 0) === (int) $category['category_id']; ?>
                    <option value="<?= (int) $category['category_id'] ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            出版年份
            <input name="publication_year" type="number" min="1" max="<?= (int) date('Y') + 1 ?>" value="<?= h($_POST['publication_year'] ?? '') ?>">
        </label>
        <div class="form-actions">
            <button type="submit">新增書籍</button>
            <a class="button secondary" href="/admin.php">取消</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
