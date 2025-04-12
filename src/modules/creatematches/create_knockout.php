<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有隊伍
$stmt = $pdo->query("
    SELECT t.*, g.group_name 
    FROM teams t 
    LEFT JOIN team_groups g ON t.group_id = g.group_id 
    WHERE (t.is_virtual = 0 OR t.is_virtual IS NULL)
    ORDER BY g.group_name, t.team_name
");
$teams = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_teams = $_POST['teams'] ?? [];
    $tournament_stage = $_POST['tournament_stage'] ?? '';
    $match_date = $_POST['match_date'] ?? null;
    $match_time = $_POST['match_time'] ?? 12; // 默認12分鐘
    $match_interval = $_POST['match_interval'] ?? 12; // 默認間隔12分鐘
    $tournament_name = $_POST['tournament_name'] ?? '淘汰賽';
    $auto_fill = isset($_POST['auto_fill']) && $_POST['auto_fill'] == 1;
    
    if (count($selected_teams) < 2) {
        $error = '請至少選擇2支隊伍';
    } elseif (!$tournament_stage) {
        $error = '請選擇淘汰賽階段';
    } elseif (!$match_date) {
        $error = '請選擇比賽日期';
    } else {
        try {
            // 開始事務
            $pdo->beginTransaction();
            
            // 確定需要的隊伍數量
            $required_teams = 0;
            if ($tournament_stage == 'round_of_16') {
                $required_teams = 16;
            } elseif ($tournament_stage == 'quarter_final') {
                $required_teams = 8;
            } elseif ($tournament_stage == 'semi_final') {
                $required_teams = 4;
            } elseif ($tournament_stage == 'final' || $tournament_stage == 'third_place') {
                $required_teams = 2;
            }
            
            $team_count = count($selected_teams);
            
            // 如果選擇自動填充且隊伍數量不足
            $virtual_teams = [];
            if ($auto_fill && $team_count < $required_teams) {
                $virtual_count = $required_teams - $team_count;
                
                // 創建虛擬隊伍
                for ($i = 1; $i <= $virtual_count; $i++) {
                    $stmt = $pdo->prepare("
                        INSERT INTO teams (team_name, team_color, is_virtual) 
                        VALUES (?, ?, 1)
                    ");
                    $virtual_name = "輸空-" . $i;
                    $stmt->execute([$virtual_name, '#CCCCCC']);
                    $virtual_team_id = $pdo->lastInsertId();
                    $virtual_teams[] = $virtual_team_id;
                }
                
                // 合併實際隊伍和虛擬隊伍
                $all_tournament_teams = array_merge($selected_teams, $virtual_teams);
                
                // 打亂隊伍順序
                shuffle($all_tournament_teams);
                
                // 重新排列，確保虛擬隊伍在左右兩側
                if (!empty($virtual_teams)) {
                    $real_teams = array_diff($all_tournament_teams, $virtual_teams);
                    $half_virtual = ceil(count($virtual_teams) / 2);
                    
                    // 左側虛擬隊伍
                    $left_virtual = array_slice($virtual_teams, 0, $half_virtual);
                    
                    // 右側虛擬隊伍
                    $right_virtual = array_slice($virtual_teams, $half_virtual);
                    
                    // 實際隊伍
                    $middle_teams = $real_teams;
                    
                    // 重新組合
                    $all_tournament_teams = array_merge($left_virtual, $middle_teams, $right_virtual);
                }
            } else {
                // 如果不自動填充，或者隊伍數量已經足夠
                $all_tournament_teams = $selected_teams;
                
                // 檢查隊伍數量是否為2的冪次方
                if (!$auto_fill && (($team_count & ($team_count - 1)) !== 0 || $team_count > $required_teams)) {
                    $error = "淘汰賽隊伍數量必須為 $required_teams 支或使用自動填充功能";
                    throw new Exception($error);
                }
                
                // 打亂隊伍順序
                shuffle($all_tournament_teams);
            }
            
            // 計算比賽時間間隔
            $base_date = new DateTime($match_date);
            $base_date->setTime(9, 0); // 設置為上午9點
            
            // 創建淘汰賽對陣
            $match_count = 0;
            $matches = [];
            
            for ($i = 0; $i < count($all_tournament_teams); $i += 2) {
                if ($i + 1 >= count($all_tournament_teams)) {
                    break; // 避免數組越界
                }
                
                $team1_id = $all_tournament_teams[$i];
                $team2_id = $all_tournament_teams[$i + 1];
                
                // 計算比賽時間
                $match_datetime = clone $base_date;
                $match_datetime->modify('+' . ($match_count * $match_interval) . ' minutes');
                
                // 生成比賽編號
                $match_number = $tournament_name . '-' . ($match_count + 1);
                
                // 檢查是否有虛擬隊伍
                $is_virtual_match = in_array($team1_id, $virtual_teams) || in_array($team2_id, $virtual_teams);
                $match_status = 'pending';
                $winner_id = null;
                
                // 如果有虛擬隊伍，自動設置另一支隊伍為獲勝者
                if ($is_virtual_match) {
                    $match_status = 'completed';
                    if (in_array($team1_id, $virtual_teams)) {
                        $winner_id = $team2_id;
                    } else {
                        $winner_id = $team1_id;
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO matches (
                        match_number, team1_id, team2_id, 
                        match_date, match_time, match_status, match_type, tournament_stage, winner_id
                    ) VALUES (?, ?, ?, ?, ?, ?, 'knockout', ?, ?)
                ");
                
                $stmt->execute([
                    $match_number,
                    $team1_id,
                    $team2_id,
                    $match_datetime->format('Y-m-d H:i:s'),
                    $match_time,
                    $match_status,
                    $tournament_stage,
                    $winner_id
                ]);
                
                $match_id = $pdo->lastInsertId();
                $matches[] = [
                    'match_id' => $match_id,
                    'team1_id' => $team1_id,
                    'team2_id' => $team2_id,
                    'winner_id' => $winner_id
                ];
                
                // 如果是虛擬比賽，自動設置比分
                if ($is_virtual_match) {
                    $team1_score = 0;
                    $team2_score = 0;
                    
                    if ($winner_id == $team1_id) {
                        $team1_score = 3;
                    } else {
                        $team2_score = 3;
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE matches 
                        SET team1_score = ?, team2_score = ?
                        WHERE match_id = ?
                    ");
                    $stmt->execute([$team1_score, $team2_score, $match_id]);
                }
                
                $match_count++;
            }
            
            // 創建下一輪比賽（如果需要）
            $next_stage = '';
            $next_match_count = 0;
            
            if ($tournament_stage == 'round_of_16') {
                $next_stage = 'quarter_final';
                $next_match_count = 4;
            } elseif ($tournament_stage == 'quarter_final') {
                $next_stage = 'semi_final';
                $next_match_count = 2;
            } elseif ($tournament_stage == 'semi_final') {
                $next_stage = 'final';
                $next_match_count = 1;
            }
            
            if (!empty($next_stage)) {
                // 計算下一輪比賽的時間（第一輪結束後1小時）
                $next_round_datetime = clone $base_date;
                $next_round_datetime->modify('+1 hour');
                
                for ($i = 0; $i < $next_match_count; $i++) {
                    $match_count++;
                    $match_number = $tournament_name . '-' . $match_count;
                    
                    // 計算比賽時間
                    $match_datetime = clone $next_round_datetime;
                    $match_datetime->modify('+' . ($i * $match_interval) . ' minutes');
                    
                    // 創建下一輪比賽
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (
                            match_number, team1_id, team2_id, match_date, match_time,
                            match_status, match_type, tournament_stage
                        ) VALUES (?, NULL, NULL, ?, ?, ?, 'knockout', ?)
                    ");
                    $stmt->execute([
                        $match_number,
                        $match_datetime->format('Y-m-d H:i:s'),
                        $match_time,
                        'pending',
                        $next_stage
                    ]);
                }
                
                // 如果是半決賽，還需要創建季軍賽
                if ($next_stage == 'final') {
                    $match_count++;
                    $match_number = $tournament_name . '-' . $match_count . '(季軍賽)';
                    
                    // 計算季軍賽時間（決賽前30分鐘）
                    $third_place_datetime = clone $next_round_datetime;
                    $third_place_datetime->modify('+' . ($next_match_count * $match_interval) . ' minutes');
                    
                    // 創建季軍賽
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (
                            match_number, team1_id, team2_id, match_date, match_time,
                            match_status, match_type, tournament_stage
                        ) VALUES (?, NULL, NULL, ?, ?, ?, 'knockout', ?)
                    ");
                    $stmt->execute([
                        $match_number,
                        $third_place_datetime->format('Y-m-d H:i:s'),
                        $match_time,
                        'pending',
                        'third_place'
                    ]);
                }
            }
            
            // 提交事務
            // 在創建比賽後，添加以下代碼來創建淘汰賽結構
            // 在 $pdo->commit(); 之前添加
            
            // 創建淘汰賽結構
            $round_number = 1;
            if ($tournament_stage == 'round_of_16') {
                $round_number = 1;
            } elseif ($tournament_stage == 'quarter_final') {
                $round_number = 2;
            } elseif ($tournament_stage == 'semi_final') {
                $round_number = 3;
            } elseif ($tournament_stage == 'final') {
                $round_number = 4;
            } elseif ($tournament_stage == 'third_place') {
                $round_number = 4;
            }
            
            // 創建淘汰賽結構記錄
            for ($i = 0; $i < count($matches); $i++) {
                $position_in_round = $i + 1;
                $next_match_id = null;
                
                // 計算下一輪的比賽ID
                if (!empty($next_stage)) {
                    $next_match_index = floor($i / 2);
                    if (isset($next_matches[$next_match_index])) {
                        $next_match_id = $next_matches[$next_match_index]['match_id'];
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO knockout_brackets (
                        tournament_id, match_id, round_number, position_in_round, next_match_id
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    null, // 暫時不關聯到特定的tournament_id
                    $matches[$i]['match_id'],
                    $round_number,
                    $position_in_round,
                    $next_match_id
                ]);
            }
            
            // 如果創建了下一輪比賽，也為它們創建淘汰賽結構
            if (!empty($next_stage)) {
                $next_round_number = $round_number + 1;
                
                for ($i = 0; $i < $next_match_count; $i++) {
                    $position_in_round = $i + 1;
                    $next_match_id = null;
                    
                    // 如果是半決賽，下一場是決賽
                    if ($next_stage == 'semi_final' && $i < 2) {
                        $next_match_id = $next_matches[$next_match_count]['match_id']; // 決賽ID
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO knockout_brackets (
                            tournament_id, match_id, round_number, position_in_round, next_match_id
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        null,
                        $next_matches[$i]['match_id'],
                        $next_round_number,
                        $position_in_round,
                        $next_match_id
                    ]);
                }
                
                // 如果創建了季軍賽
                if ($next_stage == 'final') {
                    $stmt = $pdo->prepare("
                        INSERT INTO knockout_brackets (
                            tournament_id, match_id, round_number, position_in_round, next_match_id, is_winner_bracket
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        null,
                        $next_matches[$next_match_count]['match_id'], // 季軍賽ID
                        $next_round_number,
                        3, // 位置3表示季軍賽
                        null,
                        false // 不是勝者組
                    ]);
                }
            }
            $pdo->commit();
            
            $message = "成功創建 $match_count 場淘汰賽";
            
            // 重定向到比賽列表頁面
            header("Location: list.php?message=" . urlencode($message));
            exit;
        } catch (Exception $e) {
            // 回滾事務
            $pdo->rollBack();
            $error = '創建比賽失敗: ' . $e->getMessage();
        }
    }
}

$pageTitle = '創建淘汰賽';
include __DIR__ . '/../../includes/header.php';
?>

<div class="create-section">
    <h2>創建淘汰賽</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="tournament_name">淘汰賽名稱:</label>
            <input type="text" name="tournament_name" id="tournament_name" required value="淘汰賽">
        </div>
        
        <div class="form-group">
            <label for="tournament_stage">淘汰賽階段:</label>
            <select name="tournament_stage" id="tournament_stage" required>
                <option value="">-- 選擇階段 --</option>
                <option value="round_of_16">16強賽</option>
                <option value="quarter_final">1/4決賽</option>
                <option value="semi_final">半決賽</option>
                <option value="final">決賽</option>
                <option value="third_place">季軍賽</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="auto_fill">自動填充虛擬隊伍:</label>
            <div class="checkbox-wrapper">
                <input type="checkbox" name="auto_fill" id="auto_fill" value="1" checked>
                <label for="auto_fill">啟用自動填充（不足的隊伍將使用虛擬隊伍填充）</label>
            </div>
            <small>啟用後，系統會自動創建虛擬隊伍（輸空）並安排在左右兩側</small>
        </div>
        
        <div class="form-group">
            <label>選擇參賽隊伍:</label>
            <div class="team-selection">
                <?php
                $current_group = null;
                foreach ($teams as $team):
                    if ($current_group !== $team['group_name']):
                        if ($current_group !== null): 
                            echo '</div>'; // 關閉上一個小組的div
                        endif;
                        $current_group = $team['group_name'];
                        echo '<div class="team-group">';
                        echo '<h4>' . ($current_group ? htmlspecialchars($current_group) . '組' : '未分組') . '</h4>';
                    endif;
                ?>
                    <div class="team-checkbox">
                        <input type="checkbox" name="teams[]" id="team_<?= $team['team_id'] ?>" value="<?= $team['team_id'] ?>">
                        <label for="team_<?= $team['team_id'] ?>"><?= htmlspecialchars($team['team_name']) ?></label>
                    </div>
                <?php 
                endforeach;
                if ($current_group !== null): 
                    echo '</div>'; // 關閉最後一個小組的div
                endif;
                ?>
            </div>
            <div class="team-selection-controls">
                <button type="button" id="select-all" class="button small">全選</button>
                <button type="button" id="deselect-all" class="button small secondary">取消全選</button>
                <span id="selected-count">已選擇: 0 隊</span>
            </div>
        </div>
        
        <div class="form-group">
            <label for="match_date">比賽開始日期:</label>
            <input type="date" name="match_date" id="match_date" required value="<?= date('Y-m-d') ?>">
        </div>
        
        <div class="form-group">
            <label for="match_time">每場比賽時長(分鐘):</label>
            <input type="number" name="match_time" id="match_time" min="1" max="60" value="12" required>
        </div>
        
        <div class="form-group">
            <label for="match_interval">比賽間隔時間(分鐘):</label>
            <input type="number" name="match_interval" id="match_interval" min="10" max="120" value="12" required>
            <small>建議至少12分鐘，每小時可安排5場比賽</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button">創建淘汰賽</button>
            <a href="list.php" class="button secondary">返回列表</a>
        </div>
    </form>
    
    <div class="info-box">
        <h3>淘汰賽說明</h3>
        <ul>
            <li>啟用自動填充後，系統會自動創建虛擬隊伍（輸空）填充至所需數量</li>
            <li>虛擬隊伍將被安排在左右兩側，實際隊伍將被安排在中間位置</li>
            <li>與虛擬隊伍的比賽將自動完成，實際隊伍自動晉級</li>
            <li>系統會自動創建下一輪比賽，包括決賽和季軍賽</li>
            <li>每場比賽默認時長為12分鐘，符合每小時5場比賽的要求</li>
            <li>請確保選擇的隊伍數量與淘汰賽階段相匹配：
                <ul>
                    <li>16強賽：最多16支隊伍</li>
                    <li>1/4決賽：最多8支隊伍</li>
                    <li>半決賽：最多4支隊伍</li>
                    <li>決賽/季軍賽：2支隊伍</li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const teamCheckboxes = document.querySelectorAll('input[name="teams[]"]');
    const selectAllBtn = document.getElementById('select-all');
    const deselectAllBtn = document.getElementById('deselect-all');
    const selectedCountSpan = document.getElementById('selected-count');
    const tournamentStageSelect = document.getElementById('tournament_stage');
    
    // 更新已選擇隊伍數量
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('input[name="teams[]"]:checked').length;
        selectedCountSpan.textContent = `已選擇: ${selectedCount} 隊`;
        
        // 根據選擇的隊伍數量自動選擇淘汰賽階段
        if (selectedCount <= 16 && selectedCount > 8) {
            tournamentStageSelect.value = 'round_of_16';
        } else if (selectedCount <= 8 && selectedCount > 4) {
            tournamentStageSelect.value = 'quarter_final';
        } else if (selectedCount <= 4 && selectedCount > 2) {
            tournamentStageSelect.value = 'semi_final';
        } else if (selectedCount == 2) {
            tournamentStageSelect.value = 'final';
        }
    }
    
    // 全選按鈕
    selectAllBtn.addEventListener('click', function() {
        teamCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        // 初始化計數
        updateSelectedCount();
    });
    
    // 取消全選按鈕
    deselectAllBtn.addEventListener('click', function() {
        teamCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        // 初始化計數
        updateSelectedCount();
    });
    
    // 監聽每個複選框的變化
    teamCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // 初始化計數
    updateSelectedCount();
});
</script>
    
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
    
    .checkbox-wrapper {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }
    
    .checkbox-wrapper input[type="checkbox"] {
        width: auto;
        margin-right: 8px;
    }
    
    .checkbox-wrapper label {
        display: inline;
        font-weight: normal;
    }
    
    .team-selection {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 10px;
    }
    
    .team-group {
        margin-bottom: 15px;
    }
    
    .team-group h4 {
        margin-top: 0;
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eee;
    }
    
    .team-checkbox {
        display: inline-block;
        margin-right: 15px;
        margin-bottom: 8px;
    }
    
    .team-selection-controls {
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    #selected-count {
        margin-left: auto;
        font-weight: bold;
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
    
    .button.small {
        padding: 5px 10px;
        font-size: 14px;
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