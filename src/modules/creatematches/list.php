<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有比賽
$stmt = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name,
           g.group_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN team_groups g ON m.group_id = g.group_id
    ORDER BY m.match_date DESC
");
$stmt->execute();
$matches = $stmt->fetchAll();

$pageTitle = '比賽列表';
include __DIR__ . '/../../includes/header.php';
?>

<div class="list-section">
    <h2>比賽列表</h2>
    
    <div class="action-links">
        <a href="create.php" class="button">創建新比賽</a>
    </div>
    
    <?php if (count($matches) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>場次</th>
                    <th>對陣</th>
                    <th>比分</th>
                    <th>類型</th>
                    <th>狀態</th>
                    <th>比賽時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                <tr>
                    <td><?= htmlspecialchars($match['match_number']) ?></td>
                    <td>
                        <?= htmlspecialchars($match['team1_name']) ?> vs <?= htmlspecialchars($match['team2_name']) ?>
                    </td>
                    <td>
                        <?php if ($match['match_status'] !== 'pending'): ?>
                            <?= htmlspecialchars($match['team1_score']) ?> : <?= htmlspecialchars($match['team2_score']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($match['group_id']): ?>
                            <a href="/modules/creategroup/view.php?id=<?= $match['group_id'] ?>">
                                <?= htmlspecialchars($match['group_name']) ?>
                            </a>
                        <?php else: ?>
                            <?= $match['match_type'] ? htmlspecialchars($match['match_type']) : '淘汰賽' ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $status_class = '';
                        switch ($match['match_status']) {
                            case 'pending':
                                echo '待開始';
                                $status_class = 'status-pending';
                                break;
                            case 'active':
                                echo '進行中';
                                $status_class = 'status-active';
                                break;
                            case 'overtime':
                                echo '加時賽';
                                $status_class = 'status-overtime';
                                break;
                            case 'completed':
                                echo '已完成';
                                $status_class = 'status-completed';
                                break;
                        }
                        ?>
                        <span class="status-indicator <?= $status_class ?>"></span>
                    </td>
                    <td><?= htmlspecialchars($match['match_date']) ?></td>
                    <td class="actions">
                        <a href="view.php?id=<?= $match['match_id'] ?>" class="button small">查看</a>
                        
                        <?php if ($match['match_status'] === 'pending'): ?>
                            <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button small primary">進入比賽</a>
                        <?php elseif ($match['match_status'] === 'active' || $match['match_status'] === 'overtime'): ?>
                            <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button small primary">繼續比賽</a>
                        <?php endif; ?>
                        
                        <?php if ($match['match_status'] === 'pending'): ?>
                            <a href="edit.php?id=<?= $match['match_id'] ?>" class="button small">編輯</a>
                            <a href="delete.php?id=<?= $match['match_id'] ?>" class="button small delete" onclick="return confirm('確定要刪除此比賽嗎？')">刪除</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">暫無比賽數據</p>
    <?php endif; ?>
</div>

<style>
    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-left: 5px;
    }
    
    .status-pending {
        background-color: #6c757d;
    }
    
    .status-active {
        background-color: #28a745;
        animation: pulse 1.5s infinite;
    }
    
    .status-overtime {
        background-color: #fd7e14;
        animation: pulse 1.5s infinite;
    }
    
    .status-completed {
        background-color: #007bff;
    }
    
    @keyframes pulse {
        0% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
        100% {
            opacity: 1;
        }
    }
    
    .button.primary {
        background-color: #28a745;
    }
    
    .button.primary:hover {
        background-color: #218838;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>