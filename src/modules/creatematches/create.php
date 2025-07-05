<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

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
    $team1_id = $_POST['team1_id'] ?? null;
    $team2_id = $_POST['team2_id'] ?? null;
    $group_id = $_POST['group_id'] ?? null;
    $match_number = $_POST['match_number'] ?? '';
    $match_date = $_POST['match_date'] ?? null;
    $match_time = $_POST['match_time'] ?? 10; // 默認10分鐘
    $match_type = $_POST['match_type'] ?? 'friendly';
    $tournament_stage = $_POST['tournament_stage'] ?? null;
    
    if (!$team1_id || !$team2_id) {
        $error = '請選擇兩支參賽隊伍';
    } elseif ($team1_id === $team2_id) {
        $error = '不能選擇相同的隊伍';
    } elseif (!$match_date) {
        $error = '請選擇比賽日期';
    } else {
        try {
            // 開始事務
            $pdo->beginTransaction();
            
            // 如果沒有指定比賽編號，自動生成
            if (empty($match_number)) {
                if ($match_type === 'group' && $group_id) {
                    // 獲取小組名稱
                    $stmt = $pdo->prepare("SELECT group_name FROM team_groups WHERE group_id = ?");
                    $stmt->execute([$group_id]);
                    $group_name = $stmt->fetchColumn();
                    
                    // 獲取該小組的比賽數量
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE group_id = ?");
                    $stmt->execute([$group_id]);
                    $match_count = $stmt->fetchColumn() + 1;
                    
                    $match_number = $group_name . '-' . $match_count;
                } elseif ($match_type === 'knockout' && $tournament_stage) {
                    // 獲取該階段的比賽數量
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE tournament_stage = ?");
                    $stmt->execute([$tournament_stage]);
                    $match_count = $stmt->fetchColumn() + 1;
                    
                    $match_number = strtoupper(substr($tournament_stage, 0, 2)) . '-' . $match_count;
                } else {
                    // 友誼賽
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE match_type = 'friendly'");
                    $stmt->execute();
                    $match_count = $stmt->fetchColumn() + 1;
                    
                    $match_number = 'FR-' . $match_count;
                }
            }
            
            // 準備SQL語句
            $sql = "
                INSERT INTO matches (
                    match_number, team1_id, team2_id, 
                    match_date, match_time, match_status, match_type
            ";
            
            $params = [
                $match_number,
                $team1_id,
                $team2_id,
                $match_date,
                $match_time,
                'pending',
                $match_type
            ];
            
            // 根據比賽類型添加額外字段
            if ($match_type === 'group' && $group_id) {
                $sql .= ", group_id";
                $params[] = $group_id;
            } elseif ($match_type === 'knockout' && $tournament_stage) {
                $sql .= ", tournament_stage";
                $params[] = $tournament_stage;
            }
            
            $sql .= ") VALUES (" . str_repeat("?, ", count($params) - 1) . "?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // 提交事務
            $pdo->commit();
            
            $message = "成功創建比賽: $match_number";
            
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

$pageTitle = '創建單場比賽';
include __DIR__ . '/../../includes/header.php';
?>

<div class="create-section">
    <h2>創建單場比賽</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="team1_id">隊伍1:</label>
                <select name="team1_id" id="team1_id" required>
                    <option value="">-- 選擇隊伍 --</option>
                    <?php
                    $current_group = null;
                    foreach ($teams as $team):
                        if ($current_group !== $team['group_name']):
                            if ($current_group !== null): 
                                echo '</optgroup>';
                            endif;
                            $current_group = $team['group_name'];
                            echo '<optgroup label="' . ($current_group ? htmlspecialchars($current_group) . '組' : '未分組') . '">';
                        endif;
                    ?>
                        <option value="<?= $team['team_id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                    <?php 
                    endforeach;
                    if ($current_group !== null): 
                        echo '</optgroup>';
                    endif;
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="team2_id">隊伍2:</label>
                <select name="team2_id" id="team2_id" required>
                    <option value="">-- 選擇隊伍 --</option>
                    <?php
                    $current_group = null;
                    foreach ($teams as $team):
                        if ($current_group !== $team['group_name']):
                            if ($current_group !== null): 
                                echo '</optgroup>';
                            endif;
                            $current_group = $team['group_name'];
                            echo '<optgroup label="' . ($current_group ? htmlspecialchars($current_group) . '組' : '未分組') . '">';
                        endif;
                    ?>
                        <option value="<?= $team['team_id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                    <?php 
                    endforeach;
                    if ($current_group !== null): 
                        echo '</optgroup>';
                    endif;
                    ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="match_type">比賽類型:</label>
            <select name="match_type" id="match_type" required>
                <option value="friendly">友誼賽</option>
                <option value="group">小組賽</option>
                <option value="knockout">淘汰賽</option>
            </select>
        </div>
        
        <div class="form-group group-select" style="display: <?= ($selected_group_id ? 'block' : 'none') ?>;">
            <label for="group_id">選擇小組:</label>
            <select name="group_id" id="group_id">
                <option value="">-- 選擇小組 --</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['group_id'] ?>" <?= ($selected_group_id == $group['group_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['group_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group knockout-select" style="display: none;">
            <label for="tournament_stage">淘汰賽階段:</label>
            <select name="tournament_stage" id="tournament_stage">
                <option value="">-- 選擇階段 --</option>
                <option value="round_of_16">16強賽</option>
                <option value="quarter_final">1/4決賽</option>
                <option value="semi_final">半決賽</option>
                <option value="final">決賽</option>
                <option value="third_place">季軍賽</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="match_number">比賽編號 (可選):</label>
            <input type="text" name="match_number" id="match_number" placeholder="留空將自動生成">
            <small>如果留空，系統將根據比賽類型自動生成編號</small>
        </div>
        
        <div class="form-group">
            <label for="match_date">比賽日期時間:</label>
            <input type="datetime-local" name="match_date" id="match_date" required value="<?= date('Y-m-d\TH:i') ?>">
        </div>
        
        <div class="form-group">
            <label for="match_time">比賽時長(分鐘):</label>
            <input type="number" name="match_time" id="match_time" min="1" max="60" value="10" required>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button">創建比賽</button>
            <a href="list.php" class="button secondary">返回列表</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const matchTypeSelect = document.getElementById('match_type');
    const groupSelect = document.querySelector('.group-select');
    const knockoutSelect = document.querySelector('.knockout-select');
    const team1Select = document.getElementById('team1_id');
    const team2Select = document.getElementById('team2_id');
    const groupIdSelect = document.getElementById('group_id');
    
    // 監聽比賽類型變化
    matchTypeSelect.addEventListener('change', function() {
        if (this.value === 'group') {
            groupSelect.style.display = 'block';
            knockoutSelect.style.display = 'none';
        } else if (this.value === 'knockout') {
            groupSelect.style.display = 'none';
            knockoutSelect.style.display = 'block';
        } else {
            groupSelect.style.display = 'none';
            knockoutSelect.style.display = 'none';
        }
    });
    
    // 當選擇小組時，自動篩選該小組的隊伍
    groupIdSelect.addEventListener('change', function() {
        const selectedGroupId = this.value;
        
        // 重置隊伍選擇
        team1Select.value = '';
        team2Select.value = '';
        
        // 如果沒有選擇小組，顯示所有隊伍
        if (!selectedGroupId) {
            Array.from(team1Select.options).forEach(option => {
                option.style.display = '';
            });
            Array.from(team2Select.options).forEach(option => {
                option.style.display = '';
            });
            return;
        }
        
        // 獲取該小組的所有隊伍
        fetch(`../../api/teams.php?group_id=${selectedGroupId}`)
            .then(response => response.json())
            .then(teams => {
                const teamIds = teams.map(team => team.team_id);
                
                // 篩選隊伍1選擇框
                Array.from(team1Select.options).forEach(option => {
                    if (option.value === '' || teamIds.includes(parseInt(option.value))) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // 篩選隊伍2選擇框
                Array.from(team2Select.options).forEach(option => {
                    if (option.value === '' || teamIds.includes(parseInt(option.value))) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
            })
            .catch(error => console.error('獲取隊伍數據失敗:', error));
    });
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
    
    .form-row {
        display: flex;
        gap: 20px;
    }
    
    .form-row .form-group {
        flex: 1;
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