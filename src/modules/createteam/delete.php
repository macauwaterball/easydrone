<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$team_id = $_GET['id'] ?? 0;

// 檢查隊伍是否存在
$stmt = $pdo->prepare("SELECT * FROM teams WHERE team_id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: list.php');
    exit;
}

try {
    // 開始事務
    $pdo->beginTransaction();
    
    // 刪除隊伍相關的積分記錄
    $stmt = $pdo->prepare("DELETE FROM group_standings WHERE team_id = ?");
    $stmt->execute([$team_id]);
    
    // 刪除隊伍
    $stmt = $pdo->prepare("DELETE FROM teams WHERE team_id = ?");
    $stmt->execute([$team_id]);
    
    // 提交事務
    $pdo->commit();
    
    header('Location: list.php');
    exit;
} catch (PDOException $e) {
    // 回滾事務
    $pdo->rollBack();
    
    $error = "刪除失敗: " . $e->getMessage();
    
    $pageTitle = '刪除隊伍';
    include __DIR__ . '/../../includes/header.php';
    ?>
    
    <div class="error-section">
        <h2>刪除隊伍失敗</h2>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <div class="action-links">
            <a href="list.php" class="button">返回隊伍列表</a>
        </div>
    </div>
    
    <?php
    include __DIR__ . '/../../includes/footer.php';
}
?>