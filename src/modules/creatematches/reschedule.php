<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 獲取所有待開始的比賽
        $stmt = $pdo->prepare("
            SELECT m.match_id, m.team1_id, m.team2_id, m.match_number, m.group_id, m.match_type, g.group_name
            FROM matches m
            LEFT JOIN team_groups g ON m.group_id = g.group_id
            WHERE m.match_status = 'pending'
            ORDER BY m.group_id, m.match_id
        ");
        $stmt->execute();
        $all_matches = $stmt->fetchAll();
        
        // 獲取所有小組
        $stmt = $pdo->prepare("SELECT group_id, group_name FROM team_groups ORDER BY group_name");
        $stmt->execute();
        $all_groups = $stmt->fetchAll();
        
        // 按小組分類比賽
        $matches_by_group = [];
        foreach ($all_matches as $match) {
            $group_id = $match['group_id'] ?: 'knockout';
            if (!isset($matches_by_group[$group_id])) {
                $matches_by_group[$group_id] = [];
            }
            $matches_by_group[$group_id][] = $match;
        }
        
        // 創建隊伍列表
        $teams = [];
        foreach ($all_matches as $match) {
            if (!isset($teams[$match['team1_id']])) {
                $teams[$match['team1_id']] = [
                    'matches' => [],
                    'last_match_time' => null
                ];
            }
            if (!isset($teams[$match['team2_id']])) {
                $teams[$match['team2_id']] = [
                    'matches' => [],
                    'last_match_time' => null
                ];
            }
        }
        
        // 設置基準時間（當前時間）
        $base_date = new DateTime();
        $base_date->setTime(9, 0); // 設置為上午9點開始
        
        // 創建時間槽
        $time_slots = [];
        $current_slot = clone $base_date;
        
        // 為每個小時創建5個時間槽（每12分鐘一個）
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 9; $hour < 21; $hour++) {
                for ($slot = 0; $slot < 5; $slot++) {
                    $minute = $slot * 12;
                    $current_slot = clone $base_date;
                    $current_slot->modify("+$day days");
                    $current_slot->setTime($hour, $minute);
                    
                    $time_slots[] = [
                        'time' => clone $current_slot,
                        'group_id' => null,
                        'match_id' => null
                    ];
                }
            }
        }
        
        // 按小組輪流安排比賽
        $group_index = 0;
        $slot_index = 0;
        $scheduled_matches = 0;
        $total_matches = count($all_matches);
        
        while ($scheduled_matches < $total_matches) {
            // 獲取當前要處理的小組
            $group_ids = array_keys($matches_by_group);
            if (empty($group_ids)) break;
            
            $current_group_id = $group_ids[$group_index % count($group_ids)];
            $current_group_matches = &$matches_by_group[$current_group_id];
            
            if (empty($current_group_matches)) {
                // 如果該小組沒有比賽了，從列表中移除
                unset($matches_by_group[$current_group_id]);
                $group_index++;
                continue;
            }
            
            // 找到最適合的比賽
            $best_match_index = 0;
            $best_match_score = -1;
            
            for ($i = 0; $i < count($current_group_matches); $i++) {
                $match = $current_group_matches[$i];
                $team1_id = $match['team1_id'];
                $team2_id = $match['team2_id'];
                
                // 計算這場比賽的適合度分數
                $team1_last_time = $teams[$team1_id]['last_match_time'];
                $team2_last_time = $teams[$team2_id]['last_match_time'];
                
                $current_time = $time_slots[$slot_index]['time'];
                
                $score = 0;
                
                // 如果隊伍之前有比賽，計算時間間隔
                if ($team1_last_time) {
                    $diff = $current_time->getTimestamp() - $team1_last_time->getTimestamp();
                    $diff_minutes = $diff / 60;
                    $score += min($diff_minutes / 36, 10); // 最多加10分
                } else {
                    $score += 10; // 如果隊伍沒有之前的比賽，給予最高分
                }
                
                if ($team2_last_time) {
                    $diff = $current_time->getTimestamp() - $team2_last_time->getTimestamp();
                    $diff_minutes = $diff / 60;
                    $score += min($diff_minutes / 36, 10);
                } else {
                    $score += 10;
                }
                
                if ($score > $best_match_score) {
                    $best_match_score = $score;
                    $best_match_index = $i;
                }
            }
            
            // 安排最佳比賽
            $match = $current_group_matches[$best_match_index];
            $match_id = $match['match_id'];
            $team1_id = $match['team1_id'];
            $team2_id = $match['team2_id'];
            
            // 更新比賽時間
            $match_time = $time_slots[$slot_index]['time'];
            $stmt = $pdo->prepare("UPDATE matches SET match_date = ? WHERE match_id = ?");
            $stmt->execute([$match_time->format('Y-m-d H:i:s'), $match_id]);
            
            // 更新隊伍的最後比賽時間
            $teams[$team1_id]['last_match_time'] = $match_time;
            $teams[$team2_id]['last_match_time'] = $match_time;
            
            // 更新時間槽信息
            $time_slots[$slot_index]['group_id'] = $match['group_id'];
            $time_slots[$slot_index]['match_id'] = $match_id;
            
            // 從待安排列表中移除該比賽
            array_splice($current_group_matches, $best_match_index, 1);
            
            // 更新計數器
            $scheduled_matches++;
            $slot_index++;
            $group_index++;
        }
        
        $pdo->commit();
        $message = "所有比賽已成功重新安排！";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "重新安排比賽失敗: " . $e->getMessage();
    }
}

$pageTitle = '重新安排比賽';
include __DIR__ . '/../../includes/header.php';
?>

<div class="reschedule-section">
    <h2>重新安排比賽時間</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <div class="info-box">
        <h3>重新安排比賽說明</h3>
        <p>此功能將重新安排所有待開始的比賽時間，以確保：</p>
        <ul>
            <li>不同小組的比賽交替進行</li>
            <li>同一支隊伍不會連續比賽</li>
            <li>每小時最多安排5場比賽（每場12分鐘）</li>
            <li>同一小組的比賽不會在同一時間段進行</li>
        </ul>
        <p><strong>注意：</strong>此操作將修改所有待開始比賽的時間安排，請謹慎操作！</p>
    </div>
    
    <form method="POST" action="" class="reschedule-form">
        <div class="form-actions">
            <button type="submit" class="button primary">重新安排所有比賽</button>
            <a href="list.php" class="button secondary">返回比賽列表</a>
        </div>
    </form>
</div>

<style>
    .reschedule-section {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .info-box {
        margin: 20px 0;
        padding: 15px;
        background-color: #e9f5fe;
        border-radius: 4px;
    }
    
    .info-box h3 {
        margin-top: 0;
    }
    
    .form-actions {
        margin-top: 30px;
        display: flex;
        gap: 10px;
    }
    
    .alert {
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
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
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>