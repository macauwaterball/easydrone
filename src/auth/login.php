<?php
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../config/database.php';

// 如果已經登入，重定向到首頁
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['password'] === $password) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['admin_id'];
            $_SESSION['admin_username'] = $user['username'];
            
            header('Location: /index.php');
            exit;
        } else {
            $error = '用戶名或密碼錯誤';
        }
    } else {
        $error = '請填寫所有欄位';
    }
}

$pageTitle = '管理員登入';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - 無人機足球系統</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h1>無人機足球系統</h1>
        <h2>管理員登入</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="username">用戶名</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">密碼</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">登入</button>
        </form>
    </div>
</body>
</html>