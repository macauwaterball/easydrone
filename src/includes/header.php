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
        <nav class="navigation">
            <a href="/modules/createteam/list.php" class="nav-link">隊伍管理</a>
            <a href="/modules/creategroup/list.php" class="nav-link">小組管理</a>
            <a href="/modules/creatematches/list.php" class="nav-link">比賽管理</a>
            <a href="/modules/static/view.php" class="nav-link">統計資料</a>
            <a href="/auth/logout.php" class="nav-link logout">登出</a>
        </nav>
        <?php endif; ?>