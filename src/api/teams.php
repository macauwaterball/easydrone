<?php
require_once __DIR__ . '/../config/database.php';

// 設置響應頭為JSON
header('Content-Type: application/json');

// 獲取數據庫連接
$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取請求參數
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

try {
    if ($group_id) {
        // 獲取特定小組的隊伍
        $stmt = $pdo->prepare("SELECT * FROM teams WHERE group_id = ? ORDER BY team_name");
        $stmt->execute([$group_id]);
    } else {
        // 獲取所有隊伍
        $stmt = $pdo->query("SELECT * FROM teams ORDER BY team_name");
    }
    
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 返回JSON格式的隊伍數據
    echo json_encode($teams);
} catch (Exception $e) {
    // 返回錯誤信息
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}