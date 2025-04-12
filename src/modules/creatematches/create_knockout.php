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
    ORDER BY g.group_name, t.team_name
");
$teams = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_teams = $_POST['teams'] ?? [];
    $tournament_stage = $_POST['tournament_stage'] ?? '';
    $match_date = $_POST['match_date'] ?? null;
    $match_time = $_POST['match_time'] ?? 10; // 默認10分鐘
    $match_interval = $_POST['match_interval'] ?? 30; // 默認間隔30分鐘
    
    if (count($selected_teams) < 2) {
        $error = '請至少選擇2支隊伍';
    } elseif (!$tournament_stage) {
        $error = '請選擇淘汰賽階段';
    } elseif (!$match_date) {
        $error = '請選擇比賽日期';
    } else {
        try {
            // 檢查隊伍數量是否為2的冪次方
            $team_count = count($selected_teams);
            $is_power_of_two = ($team_count & ($team_count - 1)) === 0;
            
            if (!$is_power_of_two) {
                $error = '淘汰賽隊伍數量必須為2的冪次方(2, 4, 8, 16等)';
            } else {
                // 開始事務
                $pdo->beginTransaction();
                
                // 打亂隊伍順序
                shuffle($selected_teams);
                
                // 計算比賽時間間隔
                $base_date = new DateTime($match_date);
                $interval = new DateInterval('PT' . $match_interval . 'M'); // 間隔分鐘
                
                // 創建淘汰賽對陣
                $match_count = 0;
                for ($i = 0; $i < $team_count; $i += 2) {
                    $team1_id = $selected_teams[$i];
                    $team2_id = $selected_teams[$i + 1];
                    
                    // 計算比賽時間
                    $match_datetime = clone $base_date;
                    if ($match_count > 0) {
                        $match_datetime->add(new DateInterval('PT' . ($match_interval * $match_count) . 'M'));
                    }
                    
                    // 生成比賽編號
                    $match_number = strtoupper(substr($tournament_stage, 0, 2)) . '-' . ($match_count + 1);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (
                            match_number, team1_id, team2_id, 
                            match_date, match_time, match_status, match_type, tournament_stage
                        ) VALUES (?, ?, ?, ?, ?, 'pending', 'knockout', ?)
                    ");
                    
                    $stmt->execute([
                        $match_number,
                        $team1_id,
                        $team2_id,
                        $match_datetime->format('Y-m-d H:i:s'),
                        $match_time,
                        $tournament_stage
                    ]);
                    
                    $match_count++;
                }
                
                // 提交事務
                $pdo->commit();
                
                $message = "成功創建 $match_count 場淘汰賽";
                
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
            <button type="submit" class="button">創建淘汰賽</button>
            <a href="list.php" class="button secondary">返回列表</a>
        </div>
    </form>
    
    <div class="info-box">
        <h3>淘汰賽說明</h3>
        <ul>
            <li>淘汰賽隊伍數量必須為2的冪次方(2, 4, 8, 16等)</li>
            <li>系統會隨機打亂隊伍順序，生成對陣表</li>
            <li>每場比賽的勝者將晉級下一輪</li>
            <li>請確保選擇的隊伍數量與淘汰賽階段相匹配：
                <ul>
                    <li>16強賽：16支隊伍</li>
                    <li>1/4決賽：8支隊伍</li>
                    <li>半決賽：4支隊伍</li>
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
        if (selectedCount === 16) {
            tournamentStageSelect.value = 'round_of_16';
        } else if (selectedCount === 8) {
            tournamentStageSelect.value = 'quarter_final';
        } else if (selectedCount === 4) {
            tournamentStageSelect.value = 'semi_final';
        } else if (selectedCount === 2) {
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