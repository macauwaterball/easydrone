<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$group_id = $_GET['id'] ?? 0;

// 獲取小組信息
$stmt = $pdo->prepare("SELECT * FROM team_groups WHERE group_id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: list.php');
    exit;
}

// 處理編輯小組信息
if (isset($_POST['edit_group'])) {
    $group_name = trim($_POST['group_name']);
    $max_teams = (int)$_POST['max_teams'];
    
    if (!empty($group_name) && $max_teams > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE team_groups SET group_name = ?, max_teams = ? WHERE group_id = ?");
            $stmt->execute([$group_name, $max_teams, $group_id]);
            
            // 更新當前頁面的小組信息
            $group['group_name'] = $group_name;
            $group['max_teams'] = $max_teams;
            
            $success_message = "小組信息更新成功！";
        } catch (PDOException $e) {
            $error_message = "更新小組失敗: " . $e->getMessage();
        }
    } else {
        $error_message = "請填寫所有必填字段";
    }
}

// 處理添加隊伍到小組的操作
if (isset($_POST['add_team']) && isset($_POST['team_id'])) {
    $team_id = $_POST['team_id'];
    
    try {
        // 開始事務
        $pdo->beginTransaction();
        
        // 更新隊伍的小組
        $stmt = $pdo->prepare("UPDATE teams SET group_id = ? WHERE team_id = ?");
        $stmt->execute([$group_id, $team_id]);
        
        // 檢查是否已有積分記錄
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_standings WHERE group_id = ? AND team_id = ?");
        $stmt->execute([$group_id, $team_id]);
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // 添加積分記錄
            $stmt = $pdo->prepare("INSERT INTO group_standings (group_id, team_id) VALUES (?, ?)");
            $stmt->execute([$group_id, $team_id]);
        }
        
        // 更新小組比賽
        updateGroupMatches($pdo, $group_id);
        
        // 提交事務
        $pdo->commit();
        
        $success_message = "隊伍已成功添加到小組！比賽安排已更新。";
    } catch (PDOException $e) {
        // 回滾事務
        $pdo->rollBack();
        $error_message = "添加隊伍失敗: " . $e->getMessage();
    }
}

// 處理從小組移除隊伍的操作
if (isset($_GET['remove_team']) && isset($_GET['team_id'])) {
    $team_id = $_GET['team_id'];
    
    try {
        // 開始事務
        $pdo->beginTransaction();
        
        // 從小組積分表中刪除
        $stmt = $pdo->prepare("DELETE FROM group_standings WHERE group_id = ? AND team_id = ?");
        $stmt->execute([$group_id, $team_id]);
        
        // 更新隊伍，移除小組關聯
        $stmt = $pdo->prepare("UPDATE teams SET group_id = NULL WHERE team_id = ? AND group_id = ?");
        $stmt->execute([$team_id, $group_id]);
        
        // 刪除涉及該隊伍的比賽
        $stmt = $pdo->prepare("DELETE FROM matches WHERE group_id = ? AND (team1_id = ? OR team2_id = ?)");
        $stmt->execute([$group_id, $team_id, $team_id]);
        
        // 更新小組比賽
        updateGroupMatches($pdo, $group_id);
        
        // 提交事務
        $pdo->commit();
        
        $success_message = "隊伍已從小組中移除！比賽安排已更新。";
    } catch (PDOException $e) {
        // 回滾事務
        $pdo->rollBack();
        $error_message = "移除隊伍失敗: " . $e->getMessage();
    }
}

// 添加更新小組比賽的函數
function updateGroupMatches($pdo, $group_id) {
    // 獲取小組中的所有隊伍
    $stmt = $pdo->prepare("SELECT team_id FROM teams WHERE group_id = ? ORDER BY team_id");
    $stmt->execute([$group_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 如果隊伍數量小於2，則刪除所有比賽
    if (count($teams) < 2) {
        $stmt = $pdo->prepare("DELETE FROM matches WHERE group_id = ?");
        $stmt->execute([$group_id]);
        return;
    }
    
    // 獲取現有的比賽
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE group_id = ? AND match_status = 'pending'");
    $stmt->execute([$group_id]);
    $existing_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 刪除所有待開始的比賽
    $stmt = $pdo->prepare("DELETE FROM matches WHERE group_id = ? AND match_status = 'pending'");
    $stmt->execute([$group_id]);
    
    // 創建所有可能的隊伍組合
    $match_number = 1;
    
    // 獲取已完成或進行中的最大場次號
    $stmt = $pdo->prepare("SELECT MAX(match_number) FROM matches WHERE group_id = ? AND match_status != 'pending'");
    $stmt->execute([$group_id]);
    $max_match_number = $stmt->fetchColumn();
    
    if ($max_match_number) {
        $match_number = $max_match_number + 1;
    }
    
    // 創建循環賽制的比賽
    for ($i = 0; $i < count($teams); $i++) {
        for ($j = $i + 1; $j < count($teams); $j++) {
            $team1_id = $teams[$i];
            $team2_id = $teams[$j];
            
            // 檢查是否已有這兩支隊伍的比賽（包括已完成和進行中的）
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM matches 
                WHERE group_id = ? 
                AND ((team1_id = ? AND team2_id = ?) OR (team1_id = ? AND team2_id = ?))
                AND match_status != 'pending'
            ");
            $stmt->execute([$group_id, $team1_id, $team2_id, $team2_id, $team1_id]);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                // 創建新比賽
                $match_date = date('Y-m-d H:i:s', strtotime('+' . $match_number . ' days'));
                
                $stmt = $pdo->prepare("
                    INSERT INTO matches (group_id, team1_id, team2_id, match_date, match_status, match_number)
                    VALUES (?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([$group_id, $team1_id, $team2_id, $match_date, $match_number]);
                
                $match_number++;
            }
        }
    }
}

// 獲取該小組的隊伍
$stmt = $pdo->prepare("SELECT * FROM teams WHERE group_id = ? ORDER BY team_name");
$stmt->execute([$group_id]);
$teams = $stmt->fetchAll();

// 獲取未分配小組的隊伍
$stmt = $pdo->prepare("SELECT * FROM teams WHERE group_id IS NULL ORDER BY team_name");
$stmt->execute();
$available_teams = $stmt->fetchAll();

// 獲取該小組的比賽
$stmt = $pdo->prepare("
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    WHERE m.group_id = ?
    ORDER BY m.match_date
");
$stmt->execute([$group_id]);
$matches = $stmt->fetchAll();

// 計算小組中的隊伍數量
$team_count = count($teams);
$max_teams = $group['max_teams'];

$pageTitle = '小組詳情：' . $group['group_name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="detail-section">
    <h2>小組詳情：<?= htmlspecialchars($group['group_name']) ?></h2>
    
    <?php if (isset($success_message)): ?>
        <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    
    <div class="group-info">
        <p><strong>創建時間：</strong> <?= htmlspecialchars($group['created_at']) ?></p>
        <p><strong>隊伍數量：</strong> <?= $team_count ?> / <?= $max_teams ?></p>
        
        <!-- 添加編輯小組按鈕 -->
        <button class="button" onclick="toggleEditForm()">編輯小組信息</button>
    </div>
    
    <!-- 添加小組編輯表單 -->
    <div id="edit-group-form" style="display: none;" class="edit-form-container">
        <h3>編輯小組信息</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="group_name">小組名稱：</label>
                <input type="text" id="group_name" name="group_name" value="<?= htmlspecialchars($group['group_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="max_teams">最大隊伍數：</label>
                <input type="number" id="max_teams" name="max_teams" min="2" max="16" value="<?= $group['max_teams'] ?>" required>
                <?php if ($team_count > 0): ?>
                    <p class="note">注意：當前小組已有 <?= $team_count ?> 支隊伍，設置的最大隊伍數不應小於此數值。</p>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="edit_group" class="button">保存更改</button>
                <button type="button" onclick="toggleEditForm()" class="button secondary">取消</button>
            </div>
        </form>
    </div>
    
    <div class="tabs">
        <button class="tab-button active" onclick="openTab(event, 'teams')">小組隊伍</button>
        <button class="tab-button" onclick="openTab(event, 'matches')">小組比賽</button>
        <button class="tab-button" onclick="openTab(event, 'standings')">小組積分</button>
    </div>
    
    <div id="teams" class="tab-content" style="display: block;">
        <h3>小組隊伍</h3>
        
        <?php if ($team_count < $max_teams && count($available_teams) > 0): ?>
            <div class="add-team-form">
                <h4>添加隊伍到小組</h4>
                <form method="POST" action="">
                    <select name="team_id" required>
                        <option value="">-- 選擇隊伍 --</option>
                        <?php foreach ($available_teams as $team): ?>
                            <option value="<?= $team['team_id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_team" class="button small">添加到小組</button>
                </form>
            </div>
        <?php elseif ($team_count >= $max_teams): ?>
            <p class="info-message">此小組已達到最大隊伍數量 (<?= $max_teams ?>)。</p>
        <?php elseif (count($available_teams) == 0): ?>
            <p class="info-message">沒有可添加的隊伍。請先<a href="/modules/createteam/create.php">創建新隊伍</a>。</p>
        <?php endif; ?>
        
        <?php if (count($teams) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>隊伍名稱</th>
                        <th>創建時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><?= htmlspecialchars($team['team_name']) ?></td>
                        <td><?= htmlspecialchars($team['created_at']) ?></td>
                        <td class="actions">
                            <a href="/modules/createteam/view.php?id=<?= $team['team_id'] ?>" class="button small">查看</a>
                            <a href="/modules/createteam/edit.php?id=<?= $team['team_id'] ?>" class="button small">編輯</a>
                            <a href="?id=<?= $group_id ?>&remove_team=1&team_id=<?= $team['team_id'] ?>" class="button small delete" onclick="return confirm('確定要從小組中移除此隊伍嗎？')">移除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">此小組暫無隊伍</p>
        <?php endif; ?>
    </div>
    
    <div id="matches" class="tab-content">
        <h3>小組比賽</h3>
        
        <div class="action-links">
            <a href="../createteam/create.php?group_id=<?= $group_id ?>" class="button">添加隊伍</a>
        </div>
    </div>
    
    <div id="matches" class="tab-pane">
        <!-- 比賽列表內容 -->
        <h3>小組比賽</h3>
        
        <?php
        // 獲取小組比賽
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   t1.team_name as team1_name, 
                   t2.team_name as team2_name
            FROM matches m
            JOIN teams t1 ON m.team1_id = t1.team_id
            JOIN teams t2 ON m.team2_id = t2.team_id
            WHERE m.group_id = ?
            ORDER BY m.match_date
        ");
        $stmt->execute([$group_id]);
        $matches = $stmt->fetchAll();
        ?>
        
        <?php if (count($matches) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>場次</th>
                        <th>對陣</th>
                        <th>比分</th>
                        <th>狀態</th>
                        <th>比賽時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?= htmlspecialchars($match['match_number']) ?></td>
                        <td>
                            <?= htmlspecialchars($match['team1_name']) ?> vs <?= htmlspecialchars($match['team2_name']) ?>
                        </td>
                        <td>
                            <?php if ($match['match_status'] !== 'pending'): ?>
                                <?= htmlspecialchars($match['team1_score']) ?> : <?= htmlspecialchars($match['team2_score']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
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
                        </td>
                        <td><?= htmlspecialchars($match['match_date']) ?></td>
                        <td class="actions">
                            <a href="../creatematches/view.php?id=<?= $match['match_id'] ?>" class="button small">查看</a>
                            
                            <?php if ($match['match_status'] === 'pending'): ?>
                                <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button small primary">進入比賽</a>
                            <?php elseif ($match['match_status'] === 'active' || $match['match_status'] === 'overtime'): ?>
                                <a href="/modules/competition/play.php?match_id=<?= $match['match_id'] ?>" class="button small primary">繼續比賽</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">暫無比賽數據</p>
        <?php endif; ?>
        
        <div class="action-links">
            <a href="../creatematches/create.php?group_id=<?= $group_id ?>" class="button">添加單場比賽</a>
            <a href="../creatematches/create_group_matches.php?group_id=<?= $group_id ?>" class="button primary">為此小組創建循環賽</a>
        </div>
    </div>
    
    <div id="standings" class="tab-pane">
        <!-- 積分榜內容 -->
        <!-- 現有積分榜代碼 -->
    </div>
</div>

<style>
    .tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }
    
    .tab-button {
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        border-bottom: none;
        padding: 10px 20px;
        cursor: pointer;
        margin-right: 5px;
        border-radius: 5px 5px 0 0;
        transition: all 0.3s ease;
    }
    
    .tab-button:hover {
        background-color: #e9ecef;
    }
    
    .tab-button.active {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
    }
    
    .tab-content {
        display: none;
        padding: 20px;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 5px 5px;
    }
    
    .add-team-form {
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
        border: 1px solid #ddd;
    }
    
    .add-team-form h4 {
        margin-top: 0;
        margin-bottom: 10px;
    }
    
    .add-team-form select {
        padding: 8px;
        margin-right: 10px;
        border-radius: 4px;
        border: 1px solid #ced4da;
    }
    
    .info-message {
        padding: 10px;
        background-color: #e2f0fd;
        border-left: 4px solid #007bff;
        margin-bottom: 20px;
    }
    
    .info-message a {
        color: #007bff;
        text-decoration: underline;
    }
</style>

<script>
    function openTab(evt, tabName) {
        var i, tabContent, tabButtons;
        
        // 隱藏所有標籤內容
        tabContent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabContent.length; i++) {
            tabContent[i].style.display = "none";
        }
        
        // 移除所有標籤按鈕的 "active" 類
        tabButtons = document.getElementsByClassName("tab-button");
        for (i = 0; i < tabButtons.length; i++) {
            tabButtons[i].className = tabButtons[i].className.replace(" active", "");
        }
        
        // 顯示當前標籤內容並添加 "active" 類到按鈕
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }
    
    // 添加編輯表單顯示/隱藏函數
    function toggleEditForm() {
        var form = document.getElementById('edit-group-form');
        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>