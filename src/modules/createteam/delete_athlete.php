<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$athlete_id = $_GET['id'] ?? 0;
$team_id = $_GET['team_id'] ?? 0;

// 檢查隊員是否存在
$stmt = $pdo->prepare("SELECT * FROM athletes WHERE athlete_id = ?");
$stmt->execute([$athlete_id]);
$athlete = $stmt->fetch();

if (!$athlete) {
    header('Location: list.php');
    exit;
}

try {
    // 刪除隊員
    $stmt = $pdo->prepare("DELETE FROM athletes WHERE athlete_id = ?");
    $stmt->execute([$athlete_id]);
    
    // 重定向回隊伍詳情頁
    header("Location: view.php?id=$team_id");
    exit;
} catch (PDOException $e) {
    $error = "刪除失敗: " . $e->getMessage();
    
    $pageTitle = '刪除隊員';
    include __DIR__ . '/../../includes/header.php';
    ?>
    
    <div class="error-section">
        <h2>刪除隊員失敗</h2>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <div class="action-links">
            <a href="view.php?id=<?= $team_id ?>" class="button">返回隊伍詳情</a>
        </div>
    </div>
    
    <?php
    include __DIR__ . '/../../includes/footer.php';
}
?>