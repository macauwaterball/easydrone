<?php
require_once __DIR__ . '/../includes/session_check.php';

// 清除所有會話變量
$_SESSION = array();

// 如果要徹底銷毀會話，還要刪除會話cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 最後，銷毀會話
session_destroy();

// 重定向到登入頁面
header('Location: /auth/login.php');
exit;