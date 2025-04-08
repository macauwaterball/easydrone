<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

$pageTitle = '小組列表';
include __DIR__ . '/../../includes/header.php';
?>

<div class="list-section">
    <h2>現有小組</h2>
    
    <div class="action-links">
        <a href="create.php" class="button">創建新小組</a>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>小組名稱</th>
                <th>創建時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($groups) > 0): ?>
                <?php foreach ($groups as $group): ?>
                <tr>
                    <td><?= htmlspecialchars($group['group_name']) ?></td>
                    <td><?= htmlspecialchars($group['created_at']) ?></td>
                    <td class="actions">
                        <a href="view.php?id=<?= $group['group_id'] ?>" class="button small">查看</a>
                        <a href="delete.php?id=<?= $group['group_id'] ?>" class="button small delete" onclick="return confirm('確定要刪除此小組嗎？')">刪除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="no-data">暫無小組資料</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>