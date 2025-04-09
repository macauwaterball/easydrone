<?php require_once __DIR__ . '/session_check.php'; ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '無人機足球計分系統' ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($extraStyles)): ?>
        <?php foreach ($extraStyles as $style): ?>
            <link rel="stylesheet" href="<?= $style ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <h1>無人機足球計分系統</h1>
        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
        <nav class="main-nav">
            <ul>
                <li><a href="/index.php">首頁</a></li>
                <li><a href="/modules/creategroup/list.php">小組管理</a></li>
                <li><a href="/modules/createteam/list.php">隊伍管理</a></li>
                <li><a href="/modules/creatematches/list.php">比賽管理</a></li>
                <li><a href="/modules/static/group_stats.php">小組統計</a></li>
                <li><a href="/auth/logout.php">登出</a></li>
            </ul>
        </nav>
        <?php endif; ?>