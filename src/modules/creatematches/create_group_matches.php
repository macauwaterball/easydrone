<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

// 檢查是否有預選的小組
$selected_group_id = $_GET['group_id'] ?? null;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = $_POST['group_id'] ?? null;
    $match_date = $_POST['match_date'] ?? null;
    $match_time = $_POST['match_time'] ?? 10; // 默認10分鐘
    $match_interval = $_POST['match_interval'] ?? 30; // 默認間隔30分鐘
    
    if (!$group_id || !$match_date) {
        $error = '請選擇小組和比賽日期';
    } else {
        try {
            // 獲取小組內的所有隊伍
            $stmt = $pdo->prepare("SELECT * FROM teams WHERE group_id = ? ORDER BY team_id");
            $stmt->execute([$group_id]);
            $teams = $stmt->fetchAll();
            
            if (count($teams) < 2) {
                $error = '該小組隊伍數量不足，無法創建循環賽';
            } else {
                // 開始事務
                $pdo->beginTransaction();
                
                // 生成循環賽對陣
                $matches = generateRoundRobinMatches($teams);
                
                // 獲取小組名稱
                $stmt = $pdo->prepare("SELECT group_name FROM team_groups WHERE group_id = ?");
                $stmt->execute([$group_id]);
                $group_name = $stmt->fetchColumn();
                
                // 計算比賽時間間隔
                $base_date = new DateTime($match_date);
                $interval = new DateInterval('PT' . $match_interval . 'M'); // 間隔分鐘
                
                // 插入比賽記錄
                $match_count = 0;
                foreach ($matches as $index => $match) {
                    $match_number = $group_name . '-' . ($index + 1);
                    $match_datetime = clone $base_date;
                    
                    // 添加間隔時間
                    if ($index > 0) {
                        $match_datetime->add(new DateInterval('PT' . ($match_interval * $index) . 'M'));
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (
                            match_number, group_id, team1_id, team2_id, 
                            match_date, match_time, match_status, match_type
                        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'group')
                    ");
                    
                    $stmt->execute([
                        $match_number,
                        $group_id,
                        $match['team1_id'],
                        $match['team2_id'],
                        $match_datetime->format('Y-m-d H:i:s'),
                        $match_time
                    ]);
                    
                    $match_count++;
                }
                
                // 提交事務
                $pdo->commit();
                
                $message = "成功創建 $match_count 場小組循環賽";
                
                // 重定向到比賽列表頁面
                header("Location: list.php?message=" . urlencode($message));
                exit;
            }
        } catch (Exception $e) {
            // 回滾事務
            $pdo->rollBack();
            $error = '創建比賽失敗: ' . $e->getMessage();
        }
    }
}

// 生成循環賽對陣表函數
function generateRoundRobinMatches($teams) {
    $matches = [];
    $team_count = count($teams);
    
    // 如果隊伍數量為奇數，添加一個空隊伍
    if ($team_count % 2 != 0) {
        $teams[] = ['team_id' => null, 'team_name' => 'BYE'];
        $team_count++;
    }
    
    // 創建隊伍ID數組
    $team_ids = array_column($teams, 'team_id');
    
    // 使用圓桌算法 (Circle method) 生成循環賽
    $rounds = $team_count - 1;
    $matches_per_round = $team_count / 2;
    
    // 固定一個隊伍，其他隊伍圍繞它旋轉
    $fixed_team = array_shift($team_ids);
    $rotating_teams = $team_ids;
    
    // 記錄每個隊伍的比賽場次
    $team_matches = array_fill_keys(array_column($teams, 'team_id'), []);
    
    // 生成每輪比賽
    for ($round = 0; $round < $rounds; $round++) {
        $round_matches = [];
        
        // 固定隊伍 vs 當前輪次對應的隊伍
        $opponent = $rotating_teams[0];
        if ($fixed_team !== null && $opponent !== null) {
            $round_matches[] = [
                'team1_id' => $fixed_team,
                'team2_id' => $opponent,
                'round' => $round + 1
            ];
            
            // 記錄這兩支隊伍在這一輪的比賽
            $team_matches[$fixed_team][] = $round + 1;
            $team_matches[$opponent][] = $round + 1;
        }
        
        // 其他隊伍的配對
        for ($i = 1; $i < $matches_per_round; $i++) {
            $team1 = $rotating_teams[$i];
            $team2 = $rotating_teams[$team_count - 2 - $i + 1];
            
            if ($team1 !== null && $team2 !== null) {
                $round_matches[] = [
                    'team1_id' => $team1,
                    'team2_id' => $team2,
                    'round' => $round + 1
                ];
                
                // 記錄這兩支隊伍在這一輪的比賽
                $team_matches[$team1][] = $round + 1;
                $team_matches[$team2][] = $round + 1;
            }
        }
        
        // 將本輪比賽添加到總比賽列表
        foreach ($round_matches as $match) {
            $matches[] = $match;
        }
        
        // 旋轉隊伍 (第一個元素保持不變)
        $first = array_shift($rotating_teams);
        array_push($rotating_teams, array_pop($rotating_teams));
        array_unshift($rotating_teams, $first);
    }
    
    // 重新排序比賽，避免隊伍連續比賽
    $optimized_matches = [];
    $current_round = 1;
    $max_round = $rounds;
    
    // 按輪次重新排序比賽
    while (count($optimized_matches) < count($matches)) {
        foreach ($matches as $match) {
            if ($match['round'] == $current_round) {
                // 移除輪次信息，只保留隊伍信息
                unset($match['round']);
                $optimized_matches[] = $match;
            }
        }
        $current_round = ($current_round % $max_round) + 1;
    }
    
    return $optimized_matches;
}

$pageTitle = '創建小組循環賽';
include __DIR__ . '/../../includes/header.php';
?>

<div class="create-section">
    <h2>創建小組循環賽</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="group_id">選擇小組:</label>
            <select name="group_id" id="group_id" required>
                <option value="">-- 選擇小組 --</option>
                <?php foreach ($groups as $group): ?>
                    <?php
                    // 獲取小組內的隊伍數量
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE group_id = ?");
                    $stmt->execute([$group['group_id']]);
                    $team_count = $stmt->fetchColumn();
                    ?>
                    <option value="<?= $group['group_id'] ?>" <?= ($selected_group_id == $group['group_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['group_name']) ?> (<?= $team_count ?>隊)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="match_date">比賽開始日期:</label>
            <input type="date" name="match_date" id="match_date" required value="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="form-group">
            <label for="match_time">每場比賽時長(分鐘):</label>
            <input type="number" name="match_time" id="match_time" min="1" max="60" value="10" required>
        </div>
        
        <div class="form-group">
            <label for="match_interval">比賽間隔時間(分鐘):</label>
            <input type="number" name="match_interval" id="match_interval" min="10" max="120" value="30" required>
            <small>建議至少30分鐘，避免隊伍連續比賽</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button">創建循環賽</button>
            <a href="list.php" class="button secondary">返回列表</a>
        </div>
    </form>
    
    <div class="info-box">
        <h3>小組循環賽說明</h3>
        <ul>
            <li>系統將自動為選定小組內的所有隊伍創建循環賽</li>
            <li>每支隊伍將與小組內其他所有隊伍進行一場比賽</li>
            <li>系統會自動安排比賽時間，避免隊伍連續比賽</li>
            <li>如果小組內隊伍數量為奇數，某些隊伍可能會有輪空</li>
        </ul>
    </div>
</div>

<style>
    .create-section {
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
    
    .form-group input,
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
    
    .button.secondary {
        background-color: #6c757d;
    }
    
    .button.secondary:hover {
        background-color: #5a6268;
    }
    
    small {
        display: block;
        color: #6c757d;
        margin-top: 5px;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>