<?php require_once __DIR__ . '/session_check.php'; ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>無人機足球比賽管理系統</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* 導航欄優化樣式 */
        .main-nav {
            background: linear-gradient(to right, #2c3e50, #3498db);
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            border-radius: 0 0 8px 8px;
        }
        
        .main-nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            justify-content: center;
        }
        
        .main-nav li {
            margin: 0;
            position: relative;
        }
        
        .main-nav a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
            border-bottom: 3px solid transparent;
        }
        
        .main-nav a:hover {
            background-color: rgba(255,255,255,0.1);
            border-bottom: 3px solid #f1c40f;
        }
        
        .main-nav a.active {
            background-color: rgba(255,255,255,0.15);
            border-bottom: 3px solid #f1c40f;
        }
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .main-nav ul {
                flex-direction: column;
            }
            
            .main-nav a {
                padding: 12px 15px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <h1>無人機足球比賽管理系統</h1>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="/index.php" <?= strpos($_SERVER['REQUEST_URI'], '/index.php') !== false || $_SERVER['REQUEST_URI'] == '/' ? 'class="active"' : '' ?>>首頁</a></li>
                <li><a href="/modules/creategroup/list.php" <?= strpos($_SERVER['REQUEST_URI'], '/modules/creategroup/') !== false ? 'class="active"' : '' ?>>小組管理</a></li>
                <li><a href="/modules/createteam/list.php" <?= strpos($_SERVER['REQUEST_URI'], '/modules/createteam/') !== false ? 'class="active"' : '' ?>>隊伍管理</a></li>
                <li><a href="/modules/creatematches/list.php" <?= strpos($_SERVER['REQUEST_URI'], '/modules/creatematches/') !== false ? 'class="active"' : '' ?>>比賽管理</a></li>
                <li><a href="/modules/static/group_stats.php" <?= strpos($_SERVER['REQUEST_URI'], '/modules/static/group_stats.php') !== false ? 'class="active"' : '' ?>>小組統計</a></li>
                <li><a href="/auth/logout.php">登出</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="container">
        <!-- 頁面內容將在這裡 -->