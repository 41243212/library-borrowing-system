<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/library_actions.php';

header('Content-Type: application/json; charset=utf-8');

function scan_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function scan_payload(): array
{
    if ($_POST) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);

    return is_array($json) ? $json : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    scan_json(['ok' => false, 'message' => '請使用 POST。'], 405);
}

$user = current_user();
if (!$user) {
    scan_json(['ok' => false, 'message' => '請先登入。'], 401);
}

$payload = scan_payload();
$token = (string) ($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!hash_equals(csrf_token(), $token)) {
    scan_json(['ok' => false, 'message' => '表單驗證失敗，請重新整理頁面。'], 419);
}

$mode = (string) ($payload['mode'] ?? '');
$barcode = trim((string) ($payload['barcode'] ?? ''));

if (!in_array($mode, ['borrow', 'return'], true)) {
    scan_json(['ok' => false, 'message' => '操作模式不正確。'], 422);
}

$book = find_book_by_barcode($barcode);
if (!$book) {
    scan_json(['ok' => false, 'message' => '找不到此條碼對應的書籍。'], 404);
}

try {
    if ($mode === 'borrow') {
        $result = borrow_book_for_user((int) $book['book_id'], $user);
        scan_json([
            'ok' => true,
            'mode' => 'borrow',
            'message' => $result['message'],
            'book' => [
                'book_id' => (int) $book['book_id'],
                'isbn' => $book['isbn'],
                'title' => $book['title'],
                'author' => $book['author'],
                'status' => 'borrowed',
                'status_label' => status_label('borrowed'),
                'due_date' => $result['due_date'],
            ],
        ]);
    }

    $loan = find_active_loan_for_book((int) $book['book_id'], $user);
    if (!$loan) {
        throw new RuntimeException('找不到可歸還的借閱紀錄。');
    }

    $result = return_record_for_user((int) $loan['record_id'], $user);
    scan_json([
        'ok' => true,
        'mode' => 'return',
        'message' => $result['message'],
        'book' => [
            'book_id' => (int) $book['book_id'],
            'isbn' => $book['isbn'],
            'title' => $book['title'],
            'author' => $book['author'],
            'status' => 'available',
            'status_label' => status_label('available'),
            'fine' => $result['fine'],
            'overdue_days' => $result['overdue_days'],
        ],
    ]);
} catch (Throwable $exception) {
    scan_json([
        'ok' => false,
        'mode' => $mode,
        'message' => $exception->getMessage(),
        'book' => [
            'book_id' => (int) $book['book_id'],
            'isbn' => $book['isbn'],
            'title' => $book['title'],
            'author' => $book['author'],
            'status' => $book['status'],
            'status_label' => status_label((string) $book['status']),
        ],
    ], 422);
}
