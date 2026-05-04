<?php
require __DIR__ . '/config.php';
require __DIR__ . '/library_actions.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/index.php');
}

verify_csrf();

try {
    $result = return_record_for_user((int) ($_POST['record_id'] ?? 0), $user);
    flash('success', $result['message']);
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}

redirect_to('/index.php');
