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

// 檢查比賽是否可以編輯
if ($match['match_status'] !== 'pending') {
    header('Location: list.php?error=' . urlencode('只能編輯待開始的比賽'));
    exit;
}

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
    $match_time = $_POST['match_time'] ?? 10;
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
            
            // 準備SQL語句
            $sql = "
                UPDATE matches SET
                    team1_id = ?,
                    team2_id = ?,
                    match_number = ?,
                    match_date = ?,
                    match_time = ?,
                    match_type = ?
            ";
            
            $params = [
                $team1_id,
                $team2_id,
                $match_number,
                $match_date,
                $match_time,
                $match_type
            ];
            
            // 根據比賽類型添加額外字段
            if ($match_type === 'group') {
                $sql .= ", group_id = ?";
                $params[] = $group_id;
                // 清除淘汰賽階段
                $sql .= ", tournament_stage = NULL";
            } elseif ($match_type === 'knockout') {
                $sql .= ", tournament_stage = ?";
                $params[] = $tournament_stage;
                // 清除小組ID
                $sql .= ", group_id = NULL";
            } else {
                // 友誼賽，清除小組ID和淘汰賽階段
                $sql .= ", group_id = NULL, tournament_stage = NULL";
            }
            
            $sql .= " WHERE match_id = ?";
            $params[] = $match_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // 提交事務
            $pdo->commit();
            
            $message = "成功更新比賽信息";
            
            // 重定向到比賽列表頁面
            header("Location: list.php?message=" . urlencode($message));
            exit;
        } catch (Exception $e) {
            // 回滾事務
            $pdo->rollBack();
            $error = '更新比賽失敗: ' . $e->getMessage();
        }
    }
}

// 格式化日期時間
$match_datetime = new DateTime($match['match_date']);
$formatted_datetime = $match_datetime->format('Y-m-d\TH:i');

$pageTitle = '編輯比賽';
include __DIR__ . '/../../includes/header.php';
?>

<div class="create-section">
    <h2>編輯比賽</h2>
    
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
                        <option value="<?= $team['team_id'] ?>" <?= ($match['team1_id'] == $team['team_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($team['team_name']) ?>
                        </option>
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
                        <option value="<?= $team['team_id'] ?>" <?= ($match['team2_id'] == $team['team_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($team['team_name']) ?>
                        </option>
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
                <option value="friendly" <?= ($match['match_type'] === 'friendly') ? 'selected' : '' ?>>友誼賽</option>
                <option value="group" <?= ($match['match_type'] === 'group') ? 'selected' : '' ?>>小組賽</option>
                <option value="knockout" <?= ($match['match_type'] === 'knockout') ? 'selected' : '' ?>>淘汰賽</option>
            </select>
        </div>
        
        <div class="form-group group-select" style="display: <?= ($match['group_id']) ? 'block' : 'none' ?>;">
            <label for="group_id">選擇小組:</label>
            <select name="group_id" id="group_id">
                <option value="">-- 選擇小組 --</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['group_id'] ?>" <?= ($match['group_id'] == $group['group_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['group_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group knockout-select" style="display: <?= ($match['tournament_stage']) ? 'block' : 'none' ?>;">
            <label for="tournament_stage">淘汰賽階段:</label>
            <select name="tournament_stage" id="tournament_stage">
                <option value="">-- 選擇階段 --</option>
                <option value="round_of_16" <?= ($match['tournament_stage'] === 'round_of_16') ? 'selected' : '' ?>>16強賽</option>
                <option value="quarter_final" <?= ($match['tournament_stage'] === 'quarter_final') ? 'selected' : '' ?>>1/4決賽</option>
                <option value="semi_final" <?= ($match['tournament_stage'] === 'semi_final') ? 'selected' : '' ?>>半決賽</option>
                <option value="final" <?= ($match['tournament_stage'] === 'final') ? 'selected' : '' ?>>決賽</option>
                <option value="third_place" <?= ($match['tournament_stage'] === 'third_place') ? 'selected' : '' ?>>季軍賽</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="match_number">比賽編號:</label>
            <input type="text" name="match_number" id="match_number" value="<?= htmlspecialchars($match['match_number']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="match_date">比賽日期時間:</label>
            <input type="datetime-local" name="match_date" id="match_date" required value="<?= $formatted_datetime ?>">
        </div>
        
        <div class="form-group">
            <label for="match_time">比賽時長(分鐘):</label>
            <input type="number" name="match_time" id="match_time" min="1" max="60" value="<?= $match['match_time'] ?>" required>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="button">保存修改</button>
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
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>