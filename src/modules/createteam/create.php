<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_name = trim($_POST['team_name']);
    $group_id = !empty($_POST['group_id']) ? $_POST['group_id'] : null;

    if (!empty($team_name)) {
        try {
            // 創建隊伍
            $stmt = $pdo->prepare("INSERT INTO teams (team_name, group_id) VALUES (?, ?)");
            $stmt->execute([$team_name, $group_id]);
            $team_id = $pdo->lastInsertId();
            $message = "隊伍 '$team_name' 創建成功！";
            
            // 如果分配了小組，更新小組積分表
            if ($group_id) {
                $stmt = $pdo->prepare("INSERT INTO group_standings (group_id, team_id) VALUES (?, ?)");
                $stmt->execute([$group_id, $team_id]);
            }
            
            // 重定向到隊伍詳情頁
            header("Location: view.php?id=$team_id");
            exit;
        } catch (PDOException $e) {
            $error = "創建失敗: " . $e->getMessage();
        }
    } else {
        $error = "請填寫隊伍名稱";
    }
}

$pageTitle = '創建隊伍';
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-section">
    <h2>創建新隊伍</h2>
    <?php if (isset($message)): ?>
        <div class="success-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="create-form">
        <div class="form-group">
            <label for="team_name">隊伍名稱：</label>
            <input type="text" id="team_name" name="team_name" required>
        </div>
        
        <div class="form-group">
            <label for="group_id">所屬小組（可選）：</label>
            <select id="group_id" name="group_id">
                <option value="">-- 不分配小組 --</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit">創建隊伍</button>
    </form>
</div>

<div class="action-links">
    <a href="list.php" class="button">返回隊伍列表</a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>