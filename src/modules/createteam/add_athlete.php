<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$team_id = $_GET['team_id'] ?? 0;

// 檢查隊伍是否存在
$stmt = $pdo->prepare("SELECT * FROM teams WHERE team_id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: list.php');
    exit;
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $jersey_number = (int)$_POST['jersey_number'];
    $position = $_POST['position'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!empty($name) && $jersey_number > 0) {
        try {
            // 檢查球衣號碼是否已存在
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM athletes WHERE team_id = ? AND jersey_number = ?");
            $stmt->execute([$team_id, $jersey_number]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                $error = "此球衣號碼已被使用";
            } else {
                // 添加隊員
                $stmt = $pdo->prepare("INSERT INTO athletes (team_id, name, jersey_number, position, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$team_id, $name, $jersey_number, $position, $is_active]);
                
                $message = "隊員 '$name' 添加成功！";
                
                // 重定向回隊伍詳情頁
                header("Location: view.php?id=$team_id");
                exit;
            }
        } catch (PDOException $e) {
            $error = "添加失敗: " . $e->getMessage();
        }
    } else {
        $error = "請填寫所有必填字段";
    }
}

$pageTitle = '添加隊員';
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-section">
    <h2>添加隊員到 <?= htmlspecialchars($team['team_name']) ?></h2>
    <?php if (isset($message)): ?>
        <div class="success-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="create-form">
        <div class="form-group">
            <label for="name">姓名：</label>
            <input type="text" id="name" name="name" required>
        </div>
        
        <div class="form-group">
            <label for="jersey_number">球衣號碼：</label>
            <input type="number" id="jersey_number" name="jersey_number" min="1" max="99" required>
        </div>
        
        <div class="form-group">
            <label for="position">位置：</label>
            <select id="position" name="position" required>
                <option value="attacker">進攻手</option>
                <option value="defender">防守員</option>
                <option value="substitute">替補</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="is_active">
                <input type="checkbox" id="is_active" name="is_active" checked>
                活躍狀態
            </label>
        </div>
        
        <button type="submit">添加隊員</button>
    </form>
</div>

<div class="action-links">
    <a href="view.php?id=<?= $team_id ?>" class="button">返回隊伍詳情</a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>