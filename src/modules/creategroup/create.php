<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = trim($_POST['group_name']);

    if (!empty($group_name)) {
        try {
            // 創建小組
            $stmt = $pdo->prepare("INSERT INTO team_groups (group_name) VALUES (?)");
            $stmt->execute([$group_name]);
            $message = "小組 '$group_name' 創建成功！";
        } catch (PDOException $e) {
            $error = "創建失敗: " . $e->getMessage();
        }
    } else {
        $error = "請填寫小組名稱";
    }
}

$pageTitle = '創建小組';
include __DIR__ . '/../../includes/header.php';
?>

<div class="form-section">
    <h2>創建新小組</h2>
    <?php if (isset($message)): ?>
        <div class="success-message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="create-form">
        <div class="form-group">
            <label for="group_name">小組名稱（A~H）：</label>
            <input type="text" id="group_name" name="group_name" required maxlength="1" pattern="[A-H]">
        </div>
        <button type="submit">創建小組</button>
    </form>
</div>

<div class="action-links">
    <a href="list.php" class="button">返回小組列表</a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>