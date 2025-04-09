<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$athlete_id = $_GET['id'] ?? 0;

// 獲取隊員信息
$stmt = $pdo->prepare("SELECT a.*, t.team_name FROM athletes a JOIN teams t ON a.team_id = t.team_id WHERE a.athlete_id = ?");
$stmt->execute([$athlete_id]);
$athlete = $stmt->fetch();

if (!$athlete) {
    header('Location: list.php');
    exit;
}

$team_id = $athlete['team_id'];

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $jersey_number = (int)$_POST['jersey_number'];
    $position = $_POST['position'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!empty($name) && $jersey_number > 0) {
        try {
            // 檢查球衣號碼是否已被其他隊員使用
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM athletes WHERE team_id = ? AND jersey_number = ? AND athlete_id != ?");
            $stmt->execute([$team_id, $jersey_number, $athlete_id]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                $error = "此球衣號碼已被其他隊員使用";
            } else {
                // 更新隊員
                $stmt = $pdo->prepare("UPDATE athletes SET name = ?, jersey_number = ?, position = ?, is_active = ? WHERE athlete_id = ?");
                $stmt->execute([$name, $jersey_number, $position, $is_active, $athlete_id]);
                
                $message = "隊員信息更新成功！";
            }
        } catch (PDOException $e) {
            $error = "更新失敗: " . $e->getMessage();
        }
    } else {
        $error = "請填寫所有必填字段";
    }
}

$pageTitle = '編輯隊員';
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-section">
    <h2>編輯隊員 - <?= htmlspecialchars($athlete['name']) ?></h2>
    <p><strong>所屬隊伍：</strong> <?= htmlspecialchars($athlete['team_name']) ?></p>
    
    <?php if (isset($message)): ?>
        <div class="success-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-form">
        <div class="form-group">
            <label for="name">姓名：</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($athlete['name']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="jersey_number">球衣號碼：</label>
            <input type="number" id="jersey_number" name="jersey_number" min="1" max="99" value="<?= $athlete['jersey_number'] ?>" required>
        </div>
        
        <div class="form-group">
            <label for="position">位置：</label>
            <select id="position" name="position" required>
                <option value="attacker" <?= $athlete['position'] == 'attacker' ? 'selected' : '' ?>>進攻手</option>
                <option value="defender" <?= $athlete['position'] == 'defender' ? 'selected' : '' ?>>防守員</option>
                <option value="substitute" <?= $athlete['position'] == 'substitute' ? 'selected' : '' ?>>替補</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="is_active">
                <input type="checkbox" id="is_active" name="is_active" <?= $athlete['is_active'] ? 'checked' : '' ?>>
                活躍狀態
            </label>
        </div>
        
        <button type="submit">更新隊員</button>
    </form>
</div>

<div class="action-links">
    <a href="view.php?id=<?= $team_id ?>" class="button">返回隊伍詳情</a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>