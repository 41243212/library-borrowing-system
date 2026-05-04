<?php
declare(strict_types=1);

function normalize_barcode(string $barcode): string
{
    return preg_replace('/[^0-9Xx]/', '', $barcode) ?? '';
}

function find_book_by_barcode(string $barcode): ?array
{
    $normalized = normalize_barcode($barcode);
    if ($normalized === '') {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT b.*, c.name AS category_name
         FROM Y114_book b
         JOIN Y114_category c ON c.category_id = b.category_id
         WHERE REPLACE(REPLACE(UPPER(COALESCE(b.isbn, '')), '-', ''), ' ', '') = UPPER(?)
            OR b.isbn = ?
         LIMIT 1"
    );
    $stmt->execute([$normalized, trim($barcode)]);
    $book = $stmt->fetch();

    return $book ?: null;
}

function borrow_book_for_user(int $bookId, array $user): array
{
    if ($user['role'] !== 'reader') {
        throw new RuntimeException('只有讀者帳號可以借閱書籍。');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $bookStmt = $pdo->prepare('SELECT * FROM Y114_book WHERE book_id = ? FOR UPDATE');
        $bookStmt->execute([$bookId]);
        $book = $bookStmt->fetch();

        if (!$book || $book['status'] !== 'available') {
            throw new RuntimeException('此書目前無法借閱。');
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Y114_borrow_record WHERE user_id = ? AND status = 'borrowed'");
        $countStmt->execute([(int) $user['user_id']]);
        if ((int) $countStmt->fetchColumn() >= LOAN_LIMIT) {
            throw new RuntimeException('已達借閱上限，請先歸還書籍。');
        }

        $insert = $pdo->prepare(
            "INSERT INTO Y114_borrow_record (book_id, user_id, borrow_date, due_date, status, active_book_id)
             VALUES (?, ?, CURRENT_DATE, CURRENT_DATE + INTERVAL " . LOAN_DAYS . " DAY, 'borrowed', ?)"
        );
        $insert->execute([$bookId, (int) $user['user_id'], $bookId]);

        $update = $pdo->prepare("UPDATE Y114_book SET status = 'borrowed' WHERE book_id = ?");
        $update->execute([$bookId]);

        $pdo->commit();

        $dueDate = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
        return [
            'book' => $book,
            'due_date' => $dueDate,
            'message' => "借閱成功，請於 {$dueDate} 前歸還。",
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function find_active_loan_for_book(int $bookId, array $user): ?array
{
    $params = [$bookId];
    $userCondition = '';

    if ($user['role'] !== 'admin') {
        $userCondition = 'AND r.user_id = ?';
        $params[] = (int) $user['user_id'];
    }

    $stmt = db()->prepare(
        "SELECT r.*, b.title, b.author, b.isbn,
                GREATEST(DATEDIFF(CURRENT_DATE, r.due_date), 0) AS overdue_days
         FROM Y114_borrow_record r
         JOIN Y114_book b ON b.book_id = r.book_id
         WHERE r.book_id = ?
           AND r.status = 'borrowed'
           {$userCondition}
         ORDER BY r.borrow_date ASC, r.record_id ASC
         LIMIT 1"
    );
    $stmt->execute($params);
    $loan = $stmt->fetch();

    return $loan ?: null;
}

function return_record_for_user(int $recordId, array $user): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT r.*, b.title, b.author, b.isbn,
                    GREATEST(DATEDIFF(CURRENT_DATE, r.due_date), 0) AS overdue_days
             FROM Y114_borrow_record r
             JOIN Y114_book b ON b.book_id = r.book_id
             WHERE r.record_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$recordId]);
        $loan = $stmt->fetch();

        if (!$loan || $loan['status'] !== 'borrowed') {
            throw new RuntimeException('找不到可歸還的借閱紀錄。');
        }

        if ($user['role'] !== 'admin' && (int) $loan['user_id'] !== (int) $user['user_id']) {
            throw new RuntimeException('你只能歸還自己的借閱紀錄。');
        }

        $overdueDays = (int) $loan['overdue_days'];
        $fine = $overdueDays * FINE_PER_DAY;

        $updateRecord = $pdo->prepare(
            "UPDATE Y114_borrow_record
             SET return_date = CURRENT_DATE, status = 'returned', fine_amount = ?, active_book_id = NULL
             WHERE record_id = ?"
        );
        $updateRecord->execute([$fine, $recordId]);

        $updateBook = $pdo->prepare("UPDATE Y114_book SET status = 'available' WHERE book_id = ?");
        $updateBook->execute([(int) $loan['book_id']]);

        $pdo->commit();

        $message = '已歸還「' . $loan['title'] . '」。';
        if ($overdueDays > 0) {
            $message .= " 逾期 {$overdueDays} 天，罰款 \${$fine}。";
        }

        return [
            'loan' => $loan,
            'fine' => $fine,
            'overdue_days' => $overdueDays,
            'message' => $message,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
