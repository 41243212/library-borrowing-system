<?php
require __DIR__ . '/config.php';
require __DIR__ . '/library_actions.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/books.php');
}

verify_csrf();

if ($user['role'] !== 'reader') {
    flash('error', '只有讀者帳號可以借閱書籍。');
    redirect_to('/books.php');
}

try {
    $result = borrow_book_for_user((int) ($_POST['book_id'] ?? 0), $user);
    flash('success', $result['message']);
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}

redirect_to('/books.php');
