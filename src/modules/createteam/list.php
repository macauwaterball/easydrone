<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有隊伍
$stmt = $pdo->query("
    SELECT t.*, g.group_name 
    FROM teams t
    LEFT JOIN team_groups g ON t.group_id = g.group_id
    ORDER BY t.team_name
");
$teams = $stmt->fetchAll();

$pageTitle = '隊伍列表';
include __DIR__ . '/../../includes/header.php';
?>

<div class="list-section">
    <h2>現有隊伍</h2>
    
    <div class="action-links">
        <a href="create.php" class="button">創建新隊伍</a>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>隊伍名稱</th>
                <th>所屬小組</th>
                <th>創建時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($teams) > 0): ?>
                <?php foreach ($teams as $team): ?>
                <tr>
                    <td><?= htmlspecialchars($team['team_name']) ?></td>
                    <td><?= $team['group_name'] ? htmlspecialchars($team['group_name']) : '未分配' ?></td>
                    <td><?= htmlspecialchars($team['created_at']) ?></td>
                    <td class="actions">
                        <a href="view.php?id=<?= $team['team_id'] ?>" class="button small">查看</a>
                        <a href="edit.php?id=<?= $team['team_id'] ?>" class="button small">編輯</a>
                        <a href="delete.php?id=<?= $team['team_id'] ?>" class="button small delete" onclick="return confirm('確定要刪除此隊伍嗎？')">刪除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="no-data">暫無隊伍資料</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>