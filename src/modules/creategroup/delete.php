<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$group_id = $_GET['id'] ?? 0;

if ($group_id) {
    try {
        // 開始事務
        $pdo->beginTransaction();
        
        // 檢查是否有比賽使用此小組
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $match_count = $stmt->fetchColumn();
        
        if ($match_count > 0) {
            throw new Exception("無法刪除：此小組有 $match_count 場比賽關聯");
        }
        
        // 先將該小組的隊伍的 group_id 設為 NULL
        $stmt = $pdo->prepare("UPDATE teams SET group_id = NULL WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        // 刪除小組
        $stmt = $pdo->prepare("DELETE FROM team_groups WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        // 提交事務
        $pdo->commit();
        
        $_SESSION['message'] = '小組刪除成功';
    } catch (Exception $e) {
        // 回滾事務
        $pdo->rollBack();
        $_SESSION['error'] = '刪除失敗: ' . $e->getMessage();
    }
}

// 重定向回列表頁
header('Location: list.php');
exit;