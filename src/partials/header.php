<?php
$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Library Borrowing System';
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | csieDBTeam14</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/index.php">圖書館借閱管理</a>
    <nav class="nav">
        <?php if ($currentUser): ?>
            <a href="/index.php">首頁</a>
            <a href="/books.php">書籍查詢</a>
            <a href="/mobile_scan.php">掃描借還</a>
            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="/admin.php">管理後台</a>
                <a href="/reports.php">報表</a>
            <?php endif; ?>
            <span class="user-chip"><?= h($currentUser['username']) ?> · <?= h(role_label($currentUser['role'])) ?></span>
            <a href="/logout.php">登出</a>
        <?php else: ?>
            <a href="/login.php">登入</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">
    <?php foreach (consume_flashes() as $message): ?>
        <div class="flash <?= h($message['type']) ?>"><?= h($message['message']) ?></div>
    <?php endforeach; ?>
