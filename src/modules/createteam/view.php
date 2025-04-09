<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$team_id = $_GET['id'] ?? 0;

// 獲取隊伍信息
$stmt = $pdo->prepare("
    SELECT t.*, g.group_name 
    FROM teams t
    LEFT JOIN team_groups g ON t.group_id = g.group_id
    WHERE t.team_id = ?
");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: list.php');
    exit;
}

// 獲取隊伍成員
$stmt = $pdo->prepare("SELECT * FROM athletes WHERE team_id = ? ORDER BY jersey_number");
$stmt->execute([$team_id]);
$athletes = $stmt->fetchAll();

// 獲取該隊伍參與的比賽
$stmt = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name,
           g.group_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN team_groups g ON m.group_id = g.group_id
    WHERE m.team1_id = ? OR m.team2_id = ?
    ORDER BY m.match_date
");
$stmt->execute([$team_id, $team_id]);
$matches = $stmt->fetchAll();

$pageTitle = '隊伍詳情：' . $team['team_name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="detail-section">
    <h2>隊伍詳情：<?= htmlspecialchars($team['team_name']) ?></h2>
    
    <div class="team-info">
        <p><strong>所屬小組：</strong> <?= $team['group_name'] ? htmlspecialchars($team['group_name']) : '未分配' ?></p>
        <p><strong>創建時間：</strong> <?= htmlspecialchars($team['created_at']) ?></p>
    </div>
    
    <h3>隊伍成員</h3>
    <div class="action-links">
        <a href="add_athlete.php?team_id=<?= $team_id ?>" class="button small">添加隊員</a>
    </div>
    
    <?php if (count($athletes) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>球衣號碼</th>
                    <th>姓名</th>
                    <th>位置</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($athletes as $athlete): ?>
                <tr>
                    <td><?= htmlspecialchars($athlete['jersey_number']) ?></td>
                    <td><?= htmlspecialchars($athlete['name']) ?></td>
                    <td>
                        <?php
                        $position = '';
                        switch ($athlete['position']) {
                            case 'attacker':
                                $position = '進攻手';
                                break;
                            case 'defender':
                                $position = '防守員';
                                break;
                            case 'substitute':
                                $position = '替補';
                                break;
                        }
                        echo htmlspecialchars($position);
                        ?>
                    </td>
                    <td><?= $athlete['is_active'] ? '活躍' : '非活躍' ?></td>
                    <td>
                        <a href="edit_athlete.php?id=<?= $athlete['athlete_id'] ?>" class="button small">編輯</a>
                        <a href="delete_athlete.php?id=<?= $athlete['athlete_id'] ?>&team_id=<?= $team_id ?>" class="button small delete" onclick="return confirm('確定要刪除此隊員嗎？')">刪除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">此隊伍暫無隊員</p>
    <?php endif; ?>
    
    <h3>參與的比賽</h3>
    <?php if (count($matches) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>場次</th>
                    <th>小組</th>
                    <th>對陣</th>
                    <th>比分</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                <tr>
                    <td><?= htmlspecialchars($match['match_number']) ?></td>
                    <td><?= $match['group_name'] ? htmlspecialchars($match['group_name']) : '淘汰賽' ?></td>
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
        <p class="no-data">此隊伍暫無比賽</p>
    <?php endif; ?>
    
    <div class="action-links">
        <a href="list.php" class="button">返回隊伍列表</a>
        <a href="edit.php?id=<?= $team_id ?>" class="button">編輯隊伍</a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>