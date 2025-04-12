<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取比賽ID
$match_id = $_GET['id'] ?? null;

if (!$match_id) {
    header('Location: list.php?error=' . urlencode('未指定比賽ID'));
    exit;
}

// 獲取比賽信息
$stmt = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name,
           g.group_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN team_groups g ON m.group_id = g.group_id
    WHERE m.match_id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: list.php?error=' . urlencode('找不到指定比賽'));
    exit;
}

$pageTitle = '比賽詳情';
include __DIR__ . '/../../includes/header.php';
?>

<div class="view-section">
    <h2>比賽詳情</h2>
    
    <div class="match-card">
        <div class="match-header">
            <span class="match-number"><?= htmlspecialchars($match['match_number']) ?></span>
            <span class="match-type">
                <?php
                if ($match['group_id']) {
                    echo htmlspecialchars($match['group_name']) . '組';
                } elseif ($match['tournament_stage']) {
                    $stages = [
                        'round_of_16' => '16強賽',
                        'quarter_final' => '1/4決賽',
                        'semi_final' => '半決賽',
                        'final' => '決賽',
                        'third_place' => '季軍賽'
                    ];
                    echo $stages[$match['tournament_stage']] ?? '淘汰賽';
                } else {
                    echo '友誼賽';
                }
                ?>
            </span>
        </div>
        
        <div class="match-teams">
            <div class="team team1">
                <div class="team-name"><?= htmlspecialchars($match['team1_name']) ?></div>
                <div class="team-score"><?= ($match['match_status'] !== 'pending') ? $match['team1_score'] : '-' ?></div>
            </div>
            <div class="vs">VS</div>
            <div class="team team2">
                <div class="team-name"><?= htmlspecialchars($match['team2_name']) ?></div>
                <div class="team-score"><?= ($match['match_status'] !== 'pending') ? $match['team2_score'] : '-' ?></div>
            </div>
        </div>
        
        <div class="match-details">
            <div class="detail-item">
                <span class="label">比賽時間:</span>
                <span class="value"><?= htmlspecialchars($match['match_date']) ?></span>
            </div>
            <div class="detail-item">
                <span class="label">比賽時長:</span>
                <span class="value"><?= htmlspecialchars($match['match_time']) ?> 分鐘</span>
            </div>
            <div class="detail-item">
                <span class="label">比賽狀態:</span>
                <span class="value status-<?= $match['match_status'] ?>">
                    <?php
                    switch ($match['match_status']) {
                        case 'pending':
                            echo '待開始';
                            break;
                        case 'active':
                            echo '進行中';
                            break;
                        case 'overtime':
                            echo '加時賽';
                            break;
                        case 'completed':
                            echo '已完成';
                            break;
                    }
                    ?>
                </span>
            </div>
            <?php if ($match['match_status'] === 'completed'): ?>
                <div class="detail-item">
                    <span class="label">勝者:</span>
                    <span class="value">
                        <?php
                        if ($match['winner_id'] === $match['team1_id']) {
                            echo htmlspecialchars($match['team1_name']);
                        } elseif ($match['winner_id'] === $match['team2_id']) {
                            echo htmlspecialchars($match['team2_name']);
                        } else {
                            echo '平局';
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="match-actions">
            <?php if ($match['match_status'] === 'pending'): ?>
                <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button primary">進入比賽</a>
                <a href="edit.php?id=<?= $match['match_id'] ?>" class="button">編輯比賽</a>
                <a href="delete.php?id=<?= $match['match_id'] ?>" class="button delete">刪除比賽</a>
            <?php elseif ($match['match_status'] === 'active' || $match['match_status'] === 'overtime'): ?>
                <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button primary">繼續比賽</a>
            <?php endif; ?>
            <a href="list.php" class="button secondary">返回列表</a>
        </div>
    </div>
</div>

<style>
    .view-section {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .match-card {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .match-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .match-number {
        font-weight: bold;
        font-size: 1.2em;
    }
    
    .match-type {
        background-color: #f8f9fa;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.9em;
    }
    
    .match-teams {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
    }
    
    .team {
        flex: 1;
        text-align: center;
    }
    
    .team-name {
        font-size: 1.5em;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .team-score {
        font-size: 2em;
        font-weight: bold;
        color: #333;
    }
    
    .vs {
        margin: 0 20px;
        font-size: 1.2em;
        color: #777;
    }
    
    .match-details {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .detail-item {
        display: flex;
        margin-bottom: 10px;
    }
    
    .detail-item:last-child {
        margin-bottom: 0;
    }
    
    .label {
        width: 120px;
        font-weight: bold;
        color: #555;
    }
    
    .value {
        flex: 1;
    }
    
    .status-pending {
        color: #6c757d;
    }
    
    .status-active {
        color: #007bff;
        font-weight: bold;
    }
    
    .status-overtime {
        color: #fd7e14;
        font-weight: bold;
    }
    
    .status-completed {
        color: #28a745;
    }
    
    .match-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 30px;
    }
    
    .button {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        background-color: #007bff;
        color: white;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    
    .button:hover {
        background-color: #0069d9;
    }
    
    .button.primary {
        background-color: #28a745;
    }
    
    .button.primary:hover {
        background-color: #218838;
    }
    
    .button.secondary {
        background-color: #6c757d;
    }
    
    .button.secondary:hover {
        background-color: #5a6268;
    }
    
    .button.delete {
        background-color: #dc3545;
    }
    
    .button.delete:hover {
        background-color: #c82333;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>