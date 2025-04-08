<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$group_id = $_GET['id'] ?? 0;

// 獲取小組信息
$stmt = $pdo->prepare("SELECT * FROM team_groups WHERE group_id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: list.php');
    exit;
}

// 獲取該小組的隊伍
$stmt = $pdo->prepare("SELECT * FROM teams WHERE group_id = ? ORDER BY team_name");
$stmt->execute([$group_id]);
$teams = $stmt->fetchAll();

// 獲取該小組的比賽
$stmt = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.group_id = ?
    ORDER BY m.match_number
");
$stmt->execute([$group_id]);
$matches = $stmt->fetchAll();

$pageTitle = '小組詳情：' . $group['group_name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="detail-section">
    <h2>小組詳情：<?= htmlspecialchars($group['group_name']) ?></h2>
    
    <div class="group-info">
        <p><strong>創建時間：</strong> <?= htmlspecialchars($group['created_at']) ?></p>
    </div>
    
    <h3>小組隊伍</h3>
    <?php if (count($teams) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>隊伍名稱</th>
                    <th>創建時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                <tr>
                    <td><?= htmlspecialchars($team['team_name']) ?></td>
                    <td><?= htmlspecialchars($team['created_at']) ?></td>
                    <td>
                        <a href="../../modules/createteam/view.php?id=<?= $team['team_id'] ?>" class="button small">查看隊伍</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">此小組暫無隊伍</p>
    <?php endif; ?>
    
    <h3>小組比賽</h3>
    <?php if (count($matches) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>場次</th>
                    <th>隊伍</th>
                    <th>比分</th>
                    <th>狀態</th>
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
                        <?= htmlspecialchars($match['team1_score']) ?> : <?= htmlspecialchars($match['team2_score']) ?>
                    </td>
                    <td>
                        <?php
                        $status = '';
                        switch ($match['match_status']) {
                            case 'pending':
                                $status = '待開始';
                                break;
                            case 'active':
                                $status = '進行中';
                                break;
                            case 'completed':
                                $status = '已完成';
                                break;
                            case 'overtime':
                                $status = '加時賽';
                                break;
                        }
                        echo htmlspecialchars($status);
                        ?>
                    </td>
                    <td>
                        <?php if ($match['match_status'] === 'pending'): ?>
                            <a href="../../modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button small">進入比賽</a>
                        <?php elseif ($match['match_status'] === 'completed'): ?>
                            <a href="../../modules/creatematches/view.php?id=<?= $match['match_id'] ?>" class="button small">查看結果</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">此小組暫無比賽</p>
    <?php endif; ?>
    
    <div class="action-links">
        <a href="list.php" class="button">返回小組列表</a>
        <a href="../../modules/creatematches/create.php?group_id=<?= $group_id ?>" class="button">為此小組創建比賽</a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>