<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$team_id = $_GET['id'] ?? 0;

// 獲取隊伍信息
$stmt = $pdo->prepare("SELECT * FROM teams WHERE team_id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: list.php');
    exit;
}

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_name = trim($_POST['team_name']);
    $group_id = !empty($_POST['group_id']) ? $_POST['group_id'] : null;
    $old_group_id = $team['group_id'];

    if (!empty($team_name)) {
        try {
            // 更新隊伍
            $stmt = $pdo->prepare("UPDATE teams SET team_name = ?, group_id = ? WHERE team_id = ?");
            $stmt->execute([$team_name, $group_id, $team_id]);
            
            // 處理小組變更
            if ($group_id != $old_group_id) {
                // 如果之前有小組，刪除舊的積分記錄
                if ($old_group_id) {
                    $stmt = $pdo->prepare("DELETE FROM group_standings WHERE team_id = ? AND group_id = ?");
                    $stmt->execute([$team_id, $old_group_id]);
                }
                
                // 如果現在有小組，添加新的積分記錄
                if ($group_id) {
                    $stmt = $pdo->prepare("INSERT INTO group_standings (group_id, team_id) VALUES (?, ?)");
                    $stmt->execute([$group_id, $team_id]);
                }
            }
            
            $message = "隊伍更新成功！";
        } catch (PDOException $e) {
            $error = "更新失敗: " . $e->getMessage();
        }
    } else {
        $error = "請填寫隊伍名稱";
    }
}

$pageTitle = '編輯隊伍';
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-section">
    <h2>編輯隊伍</h2>
    <?php if (isset($message)): ?>
        <div class="success-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-form">
        <div class="form-group">
            <label for="team_name">隊伍名稱：</label>
            <input type="text" id="team_name" name="team_name" value="<?= htmlspecialchars($team['team_name']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="group_id">所屬小組（可選）：</label>
            <select id="group_id" name="group_id">
                <option value="">-- 不分配小組 --</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['group_id'] ?>" <?= $team['group_id'] == $group['group_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['group_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit">更新隊伍</button>
    </form>
</div>

<div class="action-links">
    <a href="view.php?id=<?= $team_id ?>" class="button">返回隊伍詳情</a>
    <a href="list.php" class="button">返回隊伍列表</a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>