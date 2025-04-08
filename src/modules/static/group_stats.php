<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

// 準備小組統計數據
$group_stats = [];

foreach ($groups as $group) {
    $group_id = $group['group_id'];
    $group_name = $group['group_name'];
    
    // 獲取小組隊伍數
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $team_count = $stmt->fetchColumn();
    
    // 獲取小組比賽數
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $match_count = $stmt->fetchColumn();
    
    // 獲取已完成比賽數
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE group_id = ? AND match_status = 'completed'");
    $stmt->execute([$group_id]);
    $completed_match_count = $stmt->fetchColumn();
    
    // 計算小組積分榜
    $standings = [];
    if ($team_count > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                t.team_id,
                t.team_name,
                COUNT(DISTINCT m.match_id) as played,
                SUM(CASE WHEN m.winner_team_id = t.team_id THEN 1 ELSE 0 END) as won,
                SUM(CASE WHEN m.winner_team_id IS NULL AND m.match_status = 'completed' THEN 1 ELSE 0 END) as drawn,
                SUM(CASE WHEN m.winner_team_id IS NOT NULL AND m.winner_team_id != t.team_id AND m.match_status = 'completed' THEN 1 ELSE 0 END) as lost,
                SUM(CASE WHEN m.team1_id = t.team_id THEN m.team1_score ELSE m.team2_score END) as goals_for,
                SUM(CASE WHEN m.team1_id = t.team_id THEN m.team2_score ELSE m.team1_score END) as goals_against
            FROM 
                teams t
            LEFT JOIN 
                matches m ON (m.team1_id = t.team_id OR m.team2_id = t.team_id) AND m.match_status = 'completed'
            WHERE 
                t.group_id = ?
            GROUP BY 
                t.team_id
            ORDER BY 
                SUM(CASE WHEN m.winner_team_id = t.team_id THEN 3 WHEN m.winner_team_id IS NULL AND m.match_status = 'completed' THEN 1 ELSE 0 END) DESC,
                (SUM(CASE WHEN m.team1_id = t.team_id THEN m.team1_score ELSE m.team2_score END) - SUM(CASE WHEN m.team1_id = t.team_id THEN m.team2_score ELSE m.team1_score END)) DESC
        ");
        $stmt->execute([$group_id]);
        $standings = $stmt->fetchAll();
    }
    
    $group_stats[] = [
        'group_id' => $group_id,
        'group_name' => $group_name,
        'team_count' => $team_count,
        'match_count' => $match_count,
        'completed_match_count' => $completed_match_count,
        'standings' => $standings
    ];
}

$pageTitle = '小組統計';
include __DIR__ . '/../../includes/header.php';
?>

<div class="stats-section">
    <h2>小組統計</h2>
    
    <?php if (count($group_stats) > 0): ?>
        <?php foreach ($group_stats as $stat): ?>
            <div class="group-stat-box">
                <h3>小組 <?= htmlspecialchars($stat['group_name']) ?></h3>
                <div class="stat-summary">
                    <p><strong>隊伍數：</strong> <?= $stat['team_count'] ?></p>
                    <p><strong>比賽數：</strong> <?= $stat['match_count'] ?> (已完成: <?= $stat['completed_match_count'] ?>)</p>
                </div>
                
                <?php if (count($stat['standings']) > 0): ?>
                    <h4>積分榜</h4>
                    <table class="data-table standings">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>隊伍</th>
                                <th>比賽</th>
                                <th>勝</th>
                                <th>平</th>
                                <th>負</th>
                                <th>進球</th>
                                <th>失球</th>
                                <th>淨勝球</th>
                                <th>積分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; ?>
                            <?php foreach ($stat['standings'] as $team): ?>
                                <?php 
                                    $points = ($team['won'] * 3) + $team['drawn'];
                                    $goal_diff = $team['goals_for'] - $team['goals_against'];
                                ?>
                                <tr>
                                    <td><?= $rank++ ?></td>
                                    <td><?= htmlspecialchars($team['team_name']) ?></td>
                                    <td><?= $team['played'] ?></td>
                                    <td><?= $team['won'] ?></td>
                                    <td><?= $team['drawn'] ?></td>
                                    <td><?= $team['lost'] ?></td>
                                    <td><?= $team['goals_for'] ?></td>
                                    <td><?= $team['goals_against'] ?></td>
                                    <td><?= $goal_diff > 0 ? '+' . $goal_diff : $goal_diff ?></td>
                                    <td><strong><?= $points ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">暫無比賽數據</p>
                <?php endif; ?>
                
                <div class="action-links">
                    <a href="../../modules/creategroup/view.php?id=<?= $stat['group_id'] ?>" class="button small">查看小組詳情</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-data">暫無小組數據</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>