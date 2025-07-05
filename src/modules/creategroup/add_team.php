<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取小組ID
$group_id = $_GET['group_id'] ?? null;

if (!$group_id) {
    header('Location: index.php?error=' . urlencode('未指定小組ID'));
    exit;
}

// 獲取小組信息
$stmt = $pdo->prepare("SELECT * FROM team_groups WHERE group_id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: index.php?error=' . urlencode('找不到指定小組'));
    exit;
}

// 獲取小組內的隊伍數量
$stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE group_id = ?");
$stmt->execute([$group_id]);
$team_count = $stmt->fetchColumn();

// 檢查小組是否已滿
if ($team_count >= $group['max_teams']) {
    header('Location: view.php?id=' . $group_id . '&error=' . urlencode('該小組已達到最大隊伍數量'));
    exit;
}

// 獲取未分組的隊伍
$stmt = $pdo->prepare("SELECT * FROM teams WHERE group_id IS NULL ORDER BY team_name");
$stmt->execute();
$available_teams = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_id = $_POST['team_id'] ?? null;
    
    if (!$team_id) {
        $error = '請選擇隊伍';
    } else {
        // 獲取隊伍名稱
        $stmt = $pdo->prepare("SELECT team_name FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $team_name = $stmt->fetchColumn();
        
        // 將隊伍添加到小組
        $stmt = $pdo->prepare("UPDATE teams SET group_id = ? WHERE team_id = ?");
        $stmt->execute([$group_id, $team_id]);
        
        // 在成功添加隊伍後，檢查是否需要創建比賽
        if ($stmt->rowCount() > 0) {
            // 獲取小組中的所有隊伍
            $stmt = $pdo->prepare("SELECT team_id FROM teams WHERE group_id = ?");
            $stmt->execute([$group_id]);
            $teams = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 如果小組中有多於一支隊伍，則為新隊伍創建比賽
            if (count($teams) > 1) {
                // 獲取新添加的隊伍ID
                $new_team_id = $team_id;
                
                // 獲取所有小組的比賽（不僅僅是當前小組）
                $stmt = $pdo->prepare("
                    SELECT m.match_id, m.team1_id, m.team2_id, m.match_date, m.group_id 
                    FROM matches m
                    WHERE m.match_status = 'pending'
                    ORDER BY m.match_date
                ");
                $stmt->execute();
                $all_matches = $stmt->fetchAll();
                
                // 獲取所有小組信息
                $stmt = $pdo->prepare("SELECT group_id, group_name FROM team_groups ORDER BY group_name");
                $stmt->execute();
                $all_groups = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // 創建比賽時間表，記錄每個時間段的比賽數量
                $time_slots = [];
                
                // 填充現有比賽的時間表
                foreach ($all_matches as $match) {
                    $match_date = new DateTime($match['match_date']);
                    $time_key = $match_date->format('Y-m-d H:i');
                    
                    if (!isset($time_slots[$time_key])) {
                        $time_slots[$time_key] = [
                            'count' => 0,
                            'groups' => []
                        ];
                    }
                    
                    $time_slots[$time_key]['count']++;
                    if ($match['group_id']) {
                        $time_slots[$time_key]['groups'][$match['group_id']] = true;
                    }
                }
                
                // 創建隊伍比賽時間表，記錄每支隊伍的比賽時間和對手
                $team_schedule = [];
                foreach ($teams as $t) {
                    $team_schedule[$t] = [
                        'times' => [], // 比賽時間
                        'opponents' => [] // 對手ID
                    ];
                }
                
                // 填充當前小組現有比賽的隊伍時間表
                $stmt = $pdo->prepare("
                    SELECT team1_id, team2_id, match_date 
                    FROM matches 
                    WHERE group_id = ? 
                    ORDER BY match_date
                ");
                $stmt->execute([$group_id]);
                $existing_matches = $stmt->fetchAll();
                
                foreach ($existing_matches as $match) {
                    $match_date = new DateTime($match['match_date']);
                    
                    // 記錄隊伍1的比賽
                    if (isset($team_schedule[$match['team1_id']])) {
                        $team_schedule[$match['team1_id']]['times'][] = $match_date;
                        $team_schedule[$match['team1_id']]['opponents'][] = $match['team2_id'];
                    }
                    
                    // 記錄隊伍2的比賽
                    if (isset($team_schedule[$match['team2_id']])) {
                        $team_schedule[$match['team2_id']]['times'][] = $match_date;
                        $team_schedule[$match['team2_id']]['opponents'][] = $match['team1_id'];
                    }
                }
                
                // 為新隊伍創建與其他隊伍的比賽
                $other_teams = array_diff($teams, [$new_team_id]);
                
                // 獲取小組名稱
                $group_name = $all_groups[$group_id] ?? "Group-$group_id";
                
                // 獲取該小組的比賽數量
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE group_id = ?");
                $stmt->execute([$group_id]);
                $match_count = $stmt->fetchColumn();
                
                // 設置基準時間（當前時間）
                $base_date = new DateTime();
                $base_date->setTime(9, 0); // 設置為上午9點開始
                
                // 對其他隊伍進行排序，優先選擇比賽場次較少的隊伍
                usort($other_teams, function($a, $b) use ($team_schedule) {
                    return count($team_schedule[$a]['times']) - count($team_schedule[$b]['times']);
                });
                
                // 創建新比賽的時間安排
                $new_matches = [];
                
                foreach ($other_teams as $opponent_id) {
                    // 檢查是否已經有這兩支隊伍的比賽
                    $already_matched = false;
                    if (isset($team_schedule[$new_team_id]['opponents'])) {
                        foreach ($team_schedule[$new_team_id]['opponents'] as $existing_opponent) {
                            if ($existing_opponent == $opponent_id) {
                                $already_matched = true;
                                break;
                            }
                        }
                    }
                    
                    if ($already_matched) {
                        continue; // 跳過已經有比賽的對手
                    }
                    
                    $match_count++;
                    $match_number = $group_name . '-' . $match_count;
                    
                    // 尋找合適的比賽時間
                    $match_date = clone $base_date;
                    $found_slot = false;
                    
                    // 嘗試找到一個合適的時間段
                    for ($day = 0; $day < 7; $day++) { // 最多查找未來7天
                        $current_day = clone $match_date;
                        $current_day->modify("+$day days");
                        
                        for ($hour = 9; $hour < 21; $hour++) { // 9點到21點
                            $current_day->setTime($hour, 0);
                            
                            for ($minute = 0; $minute < 60; $minute += 12) { // 每12分鐘一場比賽
                                $current_day->setTime($hour, $minute);
                                $time_key = $current_day->format('Y-m-d H:i');
                                
                                // 檢查該時間段是否已有5場比賽
                                if (isset($time_slots[$time_key]) && $time_slots[$time_key]['count'] >= 5) {
                                    continue;
                                }
                                
                                // 檢查該時間段是否已有同組的比賽
                                if (isset($time_slots[$time_key]['groups'][$group_id])) {
                                    continue;
                                }
                                
                                // 檢查新隊伍是否在這個時間段附近有比賽
                                $has_nearby_match = false;
                                foreach ($team_schedule[$new_team_id]['times'] as $existing_time) {
                                    $diff = abs($current_day->getTimestamp() - $existing_time->getTimestamp()) / 60; // 分鐘差
                                    if ($diff < 36) { // 至少間隔36分鐘（3場比賽）
                                        $has_nearby_match = true;
                                        break;
                                    }
                                }
                                
                                if ($has_nearby_match) {
                                    continue;
                                }
                                
                                // 檢查對手是否在這個時間段附近有比賽
                                $has_nearby_match = false;
                                if (isset($team_schedule[$opponent_id]['times'])) {
                                    foreach ($team_schedule[$opponent_id]['times'] as $existing_time) {
                                        $diff = abs($current_day->getTimestamp() - $existing_time->getTimestamp()) / 60;
                                        if ($diff < 36) {
                                            $has_nearby_match = true;
                                            break;
                                        }
                                    }
                                }
                                
                                if ($has_nearby_match) {
                                    continue;
                                }
                                
                                // 找到合適的時間段
                                $match_date = clone $current_day;
                                $found_slot = true;
                                
                                // 更新時間槽信息
                                if (!isset($time_slots[$time_key])) {
                                    $time_slots[$time_key] = [
                                        'count' => 0,
                                        'groups' => []
                                    ];
                                }
                                $time_slots[$time_key]['count']++;
                                $time_slots[$time_key]['groups'][$group_id] = true;
                                
                                break 3; // 跳出所有循環
                            }
                        }
                    }
                    
                    // 如果沒有找到合適的時間段，使用默認時間
                    if (!$found_slot) {
                        $match_date = clone $base_date;
                        $match_date->modify('+' . $match_count . ' hours');
                    }
                    
                    // 添加到新比賽列表
                    $new_matches[] = [
                        'match_number' => $match_number,
                        'team1_id' => $new_team_id,
                        'team2_id' => $opponent_id,
                        'match_date' => $match_date->format('Y-m-d H:i:s')
                    ];
                    
                    // 更新隊伍比賽時間表
                    $team_schedule[$new_team_id]['times'][] = $match_date;
                    $team_schedule[$new_team_id]['opponents'][] = $opponent_id;
                    $team_schedule[$opponent_id]['times'][] = $match_date;
                    $team_schedule[$opponent_id]['opponents'][] = $new_team_id;
                }
                
                // 插入新比賽
                foreach ($new_matches as $match) {
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (
                            match_number, team1_id, team2_id, 
                            match_date, match_time, match_status, match_type, group_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $match['match_number'],
                        $match['team1_id'],
                        $match['team2_id'],
                        $match['match_date'],
                        12, // 每場比賽12分鐘
                        'pending',
                        'group',
                        $group_id
                    ]);
                }
            }
            
            // 重定向回小組頁面
            header("Location: view.php?id=$group_id&message=" . urlencode("隊伍 $team_name 已成功添加到小組"));
            exit;
        } else {
            $error = "添加隊伍失敗";
        }
    }
}

$pageTitle = '添加隊伍到小組';
include __DIR__ . '/../../includes/header.php';
?>

<div class="add-team-section">
    <h2>添加隊伍到 <?= htmlspecialchars($group['group_name']) ?> 組</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if (count($available_teams) > 0): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="team_id">選擇隊伍:</label>
                <select name="team_id" id="team_id" required>
                    <option value="">-- 選擇隊伍 --</option>
                    <?php foreach ($available_teams as $team): ?>
                        <option value="<?= $team['team_id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button">添加到小組</button>
                <a href="view.php?id=<?= $group_id ?>" class="button secondary">返回小組</a>
            </div>
        </form>
        
        <div class="info-box">
            <h3>注意事項</h3>
            <ul>
                <li>添加隊伍後，系統將自動創建該隊伍與小組內其他隊伍的比賽</li>
                <li>比賽將被安排在不同時間，避免隊伍連續比賽</li>
                <li>每場比賽默認時長為10分鐘</li>
                <li>添加後，隊伍將無法再參加其他小組的比賽</li>
            </ul>
        </div>
    <?php else: ?>
        <div class="no-data">
            <p>沒有可用的未分組隊伍</p>
            <a href="../createteam/create.php" class="button">創建新隊伍</a>
            <a href="view.php?id=<?= $group_id ?>" class="button secondary">返回小組</a>
        </div>
    <?php endif; ?>
</div>

<style>
    .add-team-section {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
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
    
    .info-box {
        margin-top: 30px;
        padding: 15px;
        background-color: #e9f5fe;
        border-radius: 4px;
    }
    
    .info-box h3 {
        margin-top: 0;
    }
    
    .no-data {
        text-align: center;
        padding: 30px;
        background-color: #f8f9fa;
        border-radius: 4px;
        margin-top: 20px;
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
    
    .button.secondary {
        background-color: #6c757d;
    }
    
    .button.secondary:hover {
        background-color: #5a6268;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>