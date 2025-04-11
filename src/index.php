<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取最近的比賽
$stmt = $pdo->query("
    SELECT m.*, 
    t1.team_name as team1_name, 
    t2.team_name as team2_name,
    g.group_name
    FROM matches m
    LEFT JOIN teams t1 ON m.team1_id = t1.team_id
    LEFT JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN team_groups g ON m.group_id = g.group_id
    ORDER BY m.match_date DESC
    LIMIT 5
");
$recent_matches = $stmt->fetchAll();

// 獲取小組數量
$stmt = $pdo->query("SELECT COUNT(*) FROM team_groups");
$group_count = $stmt->fetchColumn();

// 獲取隊伍數量
$stmt = $pdo->query("SELECT COUNT(*) FROM teams");
$team_count = $stmt->fetchColumn();

// 獲取比賽數量
$stmt = $pdo->query("SELECT COUNT(*) FROM matches");
$match_count = $stmt->fetchColumn();

$pageTitle = '首頁';
include __DIR__ . '/includes/header.php';
?>

<div class="dashboard">
    <h2>系統概覽</h2>
    
    <div class="stats-container">
        <div class="stat-box">
            <h3>小組</h3>
            <p class="stat-number"><?= $group_count ?></p>
            <a href="/modules/creategroup/list.php" class="button small">管理小組</a>
        </div>
        
        <div class="stat-box">
            <h3>隊伍</h3>
            <p class="stat-number"><?= $team_count ?></p>
            <a href="/modules/createteam/list.php" class="button small">管理隊伍</a>
        </div>
        
        <div class="stat-box">
            <h3>比賽</h3>
            <p class="stat-number"><?= $match_count ?></p>
            <a href="/modules/creatematches/list.php" class="button small">管理比賽</a>
        </div>
    </div>
    
    <h2>最近比賽</h2>
    <?php if (count($recent_matches) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>場次</th>
                    <th>小組</th>
                    <th>隊伍</th>
                    <th>比分</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_matches as $match): ?>
                <tr>
                    <td><?= htmlspecialchars($match['match_number']) ?></td>
                    <td><?= htmlspecialchars($match['group_name'] ?? '無') ?></td>
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
                    <td class="actions">
                        <?php if ($match['match_status'] === 'pending'): ?>
                            <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button small">進入比賽</a>
                        <?php elseif ($match['match_status'] === 'completed'): ?>
                            <a href="/modules/creatematches/view.php?id=<?= $match['match_id'] ?>" class="button small">查看結果</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">暫無比賽記錄</p>
    <?php endif; ?>
    
    <div class="action-links">
        <a href="/modules/creatematches/create.php" class="button">創建新比賽</a>
        <a href="/modules/creatematches/list.php" class="button">查看所有比賽</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>