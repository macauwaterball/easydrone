<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取比賽ID
$match_id = $_GET['id'] ?? null;

if (!$match_id) {
    header('Location: list.php?error=' . urlencode('未指定比賽ID'));
    exit;
}

// 獲取比賽信息
$stmt = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.match_id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: list.php?error=' . urlencode('找不到指定比賽'));
    exit;
}

// 檢查比賽是否可以刪除
if ($match['match_status'] !== 'pending') {
    header('Location: list.php?error=' . urlencode('只能刪除待開始的比賽'));
    exit;
}

$error = '';

// 處理刪除請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // 開始事務
        $pdo->beginTransaction();
        
        // 刪除比賽
        $stmt = $pdo->prepare("DELETE FROM matches WHERE match_id = ?");
        $stmt->execute([$match_id]);
        
        // 提交事務
        $pdo->commit();
        
        // 重定向到比賽列表頁面
        header("Location: list.php?message=" . urlencode('比賽已成功刪除'));
        exit;
    } catch (Exception $e) {
        // 回滾事務
        $pdo->rollBack();
        $error = '刪除比賽失敗: ' . $e->getMessage();
    }
}

$pageTitle = '刪除比賽';
include __DIR__ . '/../../includes/header.php';
?>

<div class="delete-section">
    <h2>刪除比賽</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="confirmation-box">
        <h3>確定要刪除以下比賽嗎？</h3>
        
        <div class="match-info">
            <p><strong>比賽編號:</strong> <?= htmlspecialchars($match['match_number']) ?></p>
            <p><strong>對陣:</strong> <?= htmlspecialchars($match['team1_name']) ?> vs <?= htmlspecialchars($match['team2_name']) ?></p>
            <p><strong>比賽時間:</strong> <?= htmlspecialchars($match['match_date']) ?></p>
            <p><strong>比賽狀態:</strong> 待開始</p>
        </div>
        
        <div class="warning">
            <p>⚠️ 警告：此操作無法撤銷！</p>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="confirm_delete" value="1">
            
            <div class="form-actions">
                <button type="submit" class="button delete">確認刪除</button>
                <a href="list.php" class="button secondary">取消</a>
            </div>
        </form>
    </div>
</div>

<style>
    .delete-section {
        max-width: 600px;
        margin: 0 auto;
    }
    
    .confirmation-box {
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .match-info {
        margin: 20px 0;
        padding: 15px;
        background-color: #fff;
        border: 1px solid #eee;
        border-radius: 4px;
    }
    
    .match-info p {
        margin: 8px 0;
    }
    
    .warning {
        margin: 20px 0;
        padding: 10px;
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
        border-radius: 4px;
        text-align: center;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 20px;
    }
    
    .button.delete {
        background-color: #dc3545;
    }
    
    .button.delete:hover {
        background-color: #c82333;
    }
    
    .button.secondary {
        background-color: #6c757d;
    }
    
    .button.secondary:hover {
        background-color: #5a6268;
    }
    
    .alert {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>