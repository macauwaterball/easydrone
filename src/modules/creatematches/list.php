<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 獲取消息和錯誤
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// 獲取篩選條件
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$filter_group = $_GET['group'] ?? 'all';

// 構建查詢
$sql = "
    SELECT m.*, 
           t1.team_name as team1_name, 
           t2.team_name as team2_name,
           g.group_name
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.team_id
    JOIN teams t2 ON m.team2_id = t2.team_id
    LEFT JOIN team_groups g ON m.group_id = g.group_id
    WHERE 1=1
";

$params = [];

// 應用篩選條件
if ($filter_type !== 'all') {
    $sql .= " AND m.match_type = ?";
    $params[] = $filter_type;
}

if ($filter_status !== 'all') {
    $sql .= " AND m.match_status = ?";
    $params[] = $filter_status;
}

if ($filter_group !== 'all' && $filter_group !== '') {
    $sql .= " AND m.group_id = ?";
    $params[] = $filter_group;
}

// 排序
$sql .= " ORDER BY m.match_date DESC";

// 執行查詢
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$matches = $stmt->fetchAll();

// 獲取所有小組
$stmt = $pdo->query("SELECT * FROM team_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

$pageTitle = '比賽列表';
include __DIR__ . '/../../includes/header.php';
?>

<div class="list-section">
    <h2>比賽列表</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <div class="filters">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="type">比賽類型:</label>
                <select name="type" id="type" onchange="this.form.submit()">
                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>全部</option>
                    <option value="friendly" <?= $filter_type === 'friendly' ? 'selected' : '' ?>>友誼賽</option>
                    <option value="group" <?= $filter_type === 'group' ? 'selected' : '' ?>>小組賽</option>
                    <option value="knockout" <?= $filter_type === 'knockout' ? 'selected' : '' ?>>淘汰賽</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status">比賽狀態:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>全部</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>待開始</option>
                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>進行中</option>
                    <option value="overtime" <?= $filter_status === 'overtime' ? 'selected' : '' ?>>加時賽</option>
                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>已完成</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="group">小組:</label>
                <select name="group" id="group" onchange="this.form.submit()">
                    <option value="all" <?= $filter_group === 'all' ? 'selected' : '' ?>>全部</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= $group['group_id'] ?>" <?= $filter_group == $group['group_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['group_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="button small">篩選</button>
            <a href="list.php" class="button small secondary">重置</a>
        </form>
    </div>
    
    <div class="action-links">
        <a href="create.php" class="button">創建單場比賽</a>
        <a href="create_group_matches.php" class="button">創建小組循環賽</a>
        <a href="create_knockout.php" class="button">創建淘汰賽</a>
    </div>
    
    <?php if (count($matches) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>場次</th>
                    <th>對陣</th>
                    <th>類型</th>
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
                        <?php
                        if ($match['group_id']) {
                            echo htmlspecialchars($match['group_name']) . '組';
                        } elseif ($match['tournament_stage']) {
                            $stages = [
                                'round_of_16' => '16強賽',
                                'quarter_final' => '1/4決賽',
                                'semi_final' => '半決賽',
                                'final' => '決賽',
                                'third_place' => '季軍賽'
                            ];
                            echo $stages[$match['tournament_stage']] ?? '淘汰賽';
                        } else {
                            echo '友誼賽';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($match['match_status'] !== 'pending'): ?>
                            <?= htmlspecialchars($match['team1_score']) ?> : <?= htmlspecialchars($match['team2_score']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-<?= $match['match_status'] ?>">
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
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($match['match_date']))) ?></td>
                    <td class="actions">
                        <a href="view.php?id=<?= $match['match_id'] ?>" class="button small">查看</a>
                        
                        <?php if ($match['match_status'] === 'pending'): ?>
                            <a href="edit.php?id=<?= $match['match_id'] ?>" class="button small">編輯</a>
                            <a href="delete.php?id=<?= $match['match_id'] ?>" class="button small delete">刪除</a>
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
        <div class="no-data">
            <p>暫無比賽數據</p>
        </div>
    <?php endif; ?>
</div>

<style>
    .list-section {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .filters {
        margin: 20px 0;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 4px;
    }
    
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 0.9em;
    }
    
    .filter-group select {
        padding: 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        min-width: 150px;
    }
    
    .action-links {
        margin: 20px 0;
        display: flex;
        gap: 10px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    .data-table th {
        background-color: #f8f9fa;
        font-weight: bold;
    }
    
    .data-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    
    .data-table tr:hover {
        background-color: #f1f1f1;
    }
    
    .actions {
        display: flex;
        gap: 5px;
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
        font-size: 0.9em;
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
    
    .button.delete {
        background-color: #dc3545;
    }
    
    .button.delete:hover {
        background-color: #c82333;
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
    
    .no-data {
        text-align: center;
        padding: 30px;
        background-color: #f8f9fa;
        border-radius: 4px;
        margin-top: 20px;
    }
    
    .status-pending {
        color: #6c757d;
    }
    
    .status-active {
        color: #007bff;
        font-weight: bold;
    }
    
    .status-overtime {
        color: #fd7e14;
        font-weight: bold;
    }
    
    .status-completed {
        color: #28a745;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>