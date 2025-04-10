<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$match_id = $_GET['id'] ?? 0;

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
    header('Location: list.php');
    exit;
}

$pageTitle = '比賽詳情：' . $match['team1_name'] . ' vs ' . $match['team2_name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="detail-section">
    <h2>比賽詳情</h2>
    
    <div class="match-info">
        <div class="match-header">
            <div class="team-vs">
                <div class="team team1">
                    <h3><?= htmlspecialchars($match['team1_name']) ?></h3>
                    <?php if ($match['match_status'] !== 'pending'): ?>
                        <div class="score"><?= $match['team1_score'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="versus">VS</div>
                
                <div class="team team2">
                    <h3><?= htmlspecialchars($match['team2_name']) ?></h3>
                    <?php if ($match['match_status'] !== 'pending'): ?>
                        <div class="score"><?= $match['team2_score'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($match['winner_id']) && $match['winner_id']): ?>
                <div class="winner-badge">
                    <?php
                    if ($match['winner_id'] == $match['team1_id']) {
                        echo htmlspecialchars($match['team1_name']);
                    } else {
                        echo htmlspecialchars($match['team2_name']);
                    }
                    ?> 獲勝
                    <?= isset($match['referee_decision']) && $match['referee_decision'] ? '（裁判决定）' : '' ?>
                </div>
            <?php elseif ($match['match_status'] === 'completed'): ?>
                <div class="draw-badge">平局</div>
            <?php endif; ?>
        </div>
        
        <div class="match-details">
            <p><strong>比賽編號：</strong> <?= htmlspecialchars($match['match_number']) ?></p>
            
            <?php if ($match['group_id']): ?>
                <p><strong>小組：</strong> <a href="/modules/creategroup/view.php?id=<?= $match['group_id'] ?>"><?= htmlspecialchars($match['group_name']) ?></a></p>
            <?php else: ?>
                <p><strong>比賽類型：</strong> <?= $match['match_type'] ? htmlspecialchars($match['match_type']) : '淘汰賽' ?></p>
            <?php endif; ?>
            
            <p><strong>比賽時間：</strong> <?= htmlspecialchars($match['match_date']) ?></p>
            
            <p><strong>狀態：</strong> 
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
            </p>
            
            <?php if ($match['match_status'] !== 'pending'): ?>
                <p><strong>比分：</strong> <?= $match['team1_score'] ?> : <?= $match['team2_score'] ?></p>
                
                <?php if ($match['team1_fouls'] > 0 || $match['team2_fouls'] > 0): ?>
                    <p><strong>犯規：</strong> <?= $match['team1_fouls'] ?> : <?= $match['team2_fouls'] ?></p>
                <?php endif; ?>
                
                <?php if ($match['start_time']): ?>
                    <p><strong>開始時間：</strong> <?= htmlspecialchars($match['start_time']) ?></p>
                <?php endif; ?>
                
                <?php if ($match['end_time']): ?>
                    <p><strong>結束時間：</strong> <?= htmlspecialchars($match['end_time']) ?></p>
                <?php endif; ?>
                
                <?php if ($match['match_status'] === 'overtime' || ($match['match_status'] === 'completed' && $match['overtime_time'])): ?>
                    <p><strong>加時賽：</strong> <?= $match['overtime_time'] ?> 分鐘</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="action-links">
        <?php if ($match['match_status'] === 'pending'): ?>
            <a href="/modules/competition/play.php?match_id=<?= $match_id ?>" class="button primary">進入比賽</a>
        <?php elseif ($match['match_status'] === 'active' || $match['match_status'] === 'overtime'): ?>
            <a href="/modules/competition/play.php?match_id=<?= $match_id ?>" class="button primary">繼續比賽</a>
        <?php endif; ?>
        
        <a href="edit.php?id=<?= $match_id ?>" class="button">編輯比賽</a>
        <a href="list.php" class="button">返回比賽列表</a>
        
        <?php if ($match['group_id']): ?>
            <a href="/modules/creategroup/view.php?id=<?= $match['group_id'] ?>" class="button">返回小組</a>
        <?php endif; ?>
    </div>
</div>

<style>
    .match-info {
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .match-header {
        margin-bottom: 20px;
        position: relative;
    }
    
    .team-vs {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .team {
        flex: 1;
        text-align: center;
    }
    
    .team h3 {
        margin-top: 0;
        color: #333;
    }
    
    .versus {
        font-size: 1.5rem;
        font-weight: bold;
        margin: 0 20px;
        color: #666;
    }
    
    .score {
        font-size: 2rem;
        font-weight: bold;
        color: #007bff;
        margin: 10px 0;
    }
    
    .winner-badge, .draw-badge {
        text-align: center;
        padding: 8px;
        border-radius: 4px;
        margin-top: 15px;
        font-weight: bold;
    }
    
    .winner-badge {
        background-color: #d4edda;
        color: #155724;
    }
    
    .draw-badge {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .match-details {
        background-color: #fff;
        border-radius: 4px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .match-details p {
        margin: 8px 0;
    }
    
    .button.primary {
        background-color: #28a745;
    }
    
    .button.primary:hover {
        background-color: #218838;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>