<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$tournament_stage = $_GET['stage'] ?? 'all';
$message = '';
$error = '';

// 獲取所有淘汰賽比賽
$query = "
    SELECT m.match_id, m.match_number, m.team1_id, m.team2_id, 
           m.team1_score, m.team2_score, m.match_status, m.tournament_stage,
           m.winner_id, m.match_date, kb.round_number, kb.position_in_round,
           kb.next_match_id, t1.team_name as team1_name, t1.team_color as team1_color,
           t2.team_name as team2_name, t2.team_color as team2_color,
           t1.is_virtual as team1_virtual, t2.is_virtual as team2_virtual
    FROM matches m
    JOIN knockout_brackets kb ON m.match_id = kb.match_id
    LEFT JOIN teams t1 ON m.team1_id = t1.team_id
    LEFT JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.match_type = 'knockout'
";

if ($tournament_stage != 'all') {
    $query .= " AND m.tournament_stage = :stage";
}

$query .= " ORDER BY kb.round_number, kb.position_in_round";

$stmt = $pdo->prepare($query);

if ($tournament_stage != 'all') {
    $stmt->bindParam(':stage', $tournament_stage);
}

$stmt->execute();
$matches = $stmt->fetchAll();

// 按輪次和位置組織比賽
$rounds = [];
foreach ($matches as $match) {
    $round = $match['round_number'];
    if (!isset($rounds[$round])) {
        $rounds[$round] = [];
    }
    $rounds[$round][$match['position_in_round']] = $match;
}

// 獲取所有淘汰賽階段
$stmt = $pdo->query("
    SELECT DISTINCT tournament_stage 
    FROM matches 
    WHERE match_type = 'knockout'
    ORDER BY CASE 
        WHEN tournament_stage = 'round_of_16' THEN 1
        WHEN tournament_stage = 'quarter_final' THEN 2
        WHEN tournament_stage = 'semi_final' THEN 3
        WHEN tournament_stage = 'final' THEN 4
        WHEN tournament_stage = 'third_place' THEN 5
        ELSE 6
    END
");
$stages = $stmt->fetchAll();

$pageTitle = '淘汰賽圖表';
include __DIR__ . '/../../includes/header.php';
?>

<div class="bracket-container">
    <h2>淘汰賽圖表</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <div class="stage-filter">
        <form method="GET" action="">
            <label for="stage">選擇階段:</label>
            <select name="stage" id="stage" onchange="this.form.submit()">
                <option value="all" <?= $tournament_stage == 'all' ? 'selected' : '' ?>>所有階段</option>
                <?php foreach ($stages as $stage): ?>
                    <option value="<?= $stage['tournament_stage'] ?>" <?= $tournament_stage == $stage['tournament_stage'] ? 'selected' : '' ?>>
                        <?= getStageDisplayName($stage['tournament_stage']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <div class="tournament-bracket">
        <?php
        // 計算最大輪次
        $max_round = !empty($rounds) ? max(array_keys($rounds)) : 0;
        
        for ($round = 1; $round <= $max_round; $round++): 
            $round_matches = $rounds[$round] ?? [];
            $round_name = getRoundName($round, $max_round);
        ?>
            <div class="round">
                <h3 class="round-title"><?= $round_name ?></h3>
                <div class="matches">
                    <?php 
                    if (!empty($round_matches)):
                        ksort($round_matches); // 按位置排序
                        foreach ($round_matches as $position => $match): 
                            $team1_name = $match['team1_name'] ?? '待定';
                            $team2_name = $match['team2_name'] ?? '待定';
                            $team1_color = $match['team1_color'] ?? '#FFFFFF';
                            $team2_color = $match['team2_color'] ?? '#FFFFFF';
                            $team1_virtual = $match['team1_virtual'] ?? 0;
                            $team2_virtual = $match['team2_virtual'] ?? 0;
                            
                            // 決定獲勝者
                            $winner_id = $match['winner_id'];
                            $team1_winner = ($winner_id == $match['team1_id']);
                            $team2_winner = ($winner_id == $match['team2_id']);
                            
                            // 虛擬隊伍樣式
                            $team1_class = $team1_virtual ? 'virtual-team' : '';
                            $team2_class = $team2_virtual ? 'virtual-team' : '';
                            
                            // 獲勝者樣式
                            if ($match['match_status'] == 'completed') {
                                $team1_class .= $team1_winner ? ' winner' : '';
                                $team2_class .= $team2_winner ? ' winner' : '';
                            }
                    ?>
                        <div class="match" data-match-id="<?= $match['match_id'] ?>">
                            <div class="match-details">
                                <span class="match-number"><?= htmlspecialchars($match['match_number']) ?></span>
                                <span class="match-date"><?= date('m/d H:i', strtotime($match['match_date'])) ?></span>
                            </div>
                            <div class="team <?= $team1_class ?>" style="border-left: 4px solid <?= $team1_color ?>;">
                                <span class="team-name"><?= htmlspecialchars($team1_name) ?></span>
                                <span class="team-score"><?= $match['team1_score'] ?></span>
                            </div>
                            <div class="team <?= $team2_class ?>" style="border-left: 4px solid <?= $team2_color ?>;">
                                <span class="team-name"><?= htmlspecialchars($team2_name) ?></span>
                                <span class="team-score"><?= $match['team2_score'] ?></span>
                            </div>
                        </div>
                    <?php 
                        endforeach; 
                    else:
                    ?>
                        <div class="no-matches">沒有比賽</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    
    <!-- 在 bracket-actions div 中添加處理按鈕 -->
    <div class="bracket-actions">
        <a href="../creatematches/list.php" class="button">返回比賽列表</a>
        <a href="process_knockout.php?process=true" class="button">處理晉級隊伍</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 點擊比賽跳轉到比賽詳情頁面
    const matches = document.querySelectorAll('.match');
    matches.forEach(match => {
        match.addEventListener('click', function() {
            const matchId = this.getAttribute('data-match-id');
            window.location.href = '../creatematches/view.php?id=' + matchId;
        });
    });
});
</script>

<!-- 修改 style 標籤內的樣式 -->
<style>
.tournament-bracket {
    display: flex;
    overflow-x: auto;
    padding: 40px 20px;
    align-items: center;
    min-height: 800px;
    gap: 50px;
}

.round {
    flex: 0 0 300px;
    display: flex;
    flex-direction: column;
    position: relative;
}

.matches {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
    height: 100%;
}

.match {
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
    padding: 10px;
    position: relative;
    z-index: 1;
    width: 100%;
}

/* 修改連接線樣式 */
.match::after {
    content: '';
    position: absolute;
    right: -50px;
    top: 50%;
    width: 50px;
    height: 2px;
    background-color: #ddd;
}

.match::before {
    content: '';
    position: absolute;
    right: -50px;
    top: 50%;
    height: 0;
    width: 2px;
    background-color: #ddd;
}

/* 為每一輪設置不同的間距 */
.round:nth-child(1) .matches {
    padding: 0;
}

.round:nth-child(2) .matches {
    padding: 60px 0;
}

.round:nth-child(3) .matches {
    padding: 120px 0;
}

.round:nth-child(4) .matches {
    padding: 240px 0;
}

/* 為相鄰的比賽添加連接線 */
.match:not(:last-child)::before {
    content: '';
    position: absolute;
    right: -50px;
    top: 50%;
    height: calc(100% + 40px);
    width: 2px;
    background-color: #ddd;
}

/* 其他樣式保持不變 */
.team {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 10px;
    border-left: 4px solid transparent;
    margin: 5px 0;
}

.team-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.team-score {
    font-weight: bold;
    margin-left: 10px;
}

.round-title {
    text-align: center;
    margin-bottom: 20px;
    font-size: 18px;
    color: #333;
}

/* 添加一些輔助樣式來改善視覺效果 */
.bracket-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    overflow-x: auto;
}

/* 響應式設計調整 */
@media (max-width: 768px) {
    .tournament-bracket {
        gap: 30px;
    }
    
    .round {
        flex: 0 0 250px;
    }
    
    .match::after {
        width: 30px;
        right: -30px;
    }
    
    .match::before {
        right: -30px;
    }
}
</style>

<?php
// 輔助函數：獲取階段顯示名稱
function getStageDisplayName($stage) {
    switch ($stage) {
        case 'round_of_16':
            return '16強賽';
        case 'quarter_final':
            return '1/4決賽';
        case 'semi_final':
            return '半決賽';
        case 'final':
            return '決賽';
        case 'third_place':
            return '季軍賽';
        default:
            return $stage;
    }
}

// 輔助函數：獲取輪次名稱
function getRoundName($round, $max_round) {
    if ($max_round == 4) {
        // 16強賽制
        switch ($round) {
            case 1: return '16強賽';
            case 2: return '1/4決賽';
            case 3: return '半決賽';
            case 4: return '決賽/季軍賽';
            default: return "第{$round}輪";
        }
    } elseif ($max_round == 3) {
        // 8強賽制
        switch ($round) {
            case 1: return '1/4決賽';
            case 2: return '半決賽';
            case 3: return '決賽/季軍賽';
            default: return "第{$round}輪";
        }
    } elseif ($max_round == 2) {
        // 4強賽制
        switch ($round) {
            case 1: return '半決賽';
            case 2: return '決賽/季軍賽';
            default: return "第{$round}輪";
        }
    } else {
        return "第{$round}輪";
    }
}
?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
