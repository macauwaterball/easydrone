<?php ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

// 設置錯誤日誌
ini_set('log_errors', 1);
ini_set('error_log', '/dev/stderr');  // Change to Docker's stderr
error_reporting(E_ALL);

function processKnockoutMatches() {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    try {
        $pdo->beginTransaction();
        
        // 首先檢查當前要處理的輪次
        $stmt = $pdo->prepare("
            SELECT MIN(round_number) as current_round
            FROM knockout_brackets kb
            JOIN matches m ON kb.match_id = m.match_id
            WHERE m.match_status = 'completed'
            AND NOT EXISTS (
                SELECT 1 FROM knockout_brackets kb2
                WHERE kb2.round_number = kb.round_number + 1
            )
        ");
        $stmt->execute();
        $current_round = $stmt->fetchColumn();
        error_log("當前處理輪次: " . $current_round);

        // 獲取當前輪次的比賽
        $stmt = $pdo->prepare("
            SELECT m.match_id, m.match_status, m.winner_id, 
                   m.team1_id, m.team2_id, m.team1_score, m.team2_score,
                   kb.round_number, kb.position_in_round,
                   t1.team_name as team1_name, t2.team_name as team2_name
            FROM matches m
            JOIN knockout_brackets kb ON m.match_id = kb.match_id
            LEFT JOIN teams t1 ON m.team1_id = t1.team_id
            LEFT JOIN teams t2 ON m.team2_id = t2.team_id
            WHERE kb.round_number = ?
            AND m.match_type = 'knockout'
            ORDER BY kb.position_in_round
        ");
        $stmt->execute([$current_round]);
        $current_matches = $stmt->fetchAll();

        // 如果沒有找到比賽，檢查數據庫狀態
        if (count($current_matches) === 0) {
            // 檢查 matches 表
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, match_type, match_status 
                FROM matches 
                GROUP BY match_type, match_status
            ");
            $matches_stats = $stmt->fetchAll();
            error_log("比賽統計: " . print_r($matches_stats, true));
            
            // 檢查 knockout_brackets 表
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, round_number 
                FROM knockout_brackets 
                GROUP BY round_number
            ");
            $brackets_stats = $stmt->fetchAll();
            error_log("淘汰賽統計: " . print_r($brackets_stats, true));
        }
        
        // 輸出調試信息
        error_log("當前輪次比賽數量: " . count($current_matches));
        foreach ($current_matches as $match) {
            error_log(sprintf(
                "比賽ID: %d, 狀態: %s, 獲勝者ID: %s, 隊伍1(%s)得分: %d, 隊伍2(%s)得分: %d",
                $match['match_id'],
                $match['match_status'],
                $match['winner_id'],
                $match['team1_name'],
                $match['team1_score'],
                $match['team2_name'],
                $match['team2_score']
            ));
        }
        
        // 檢查是否所有比賽都已完成且有獲勝者
        $all_completed = true;
        $all_have_winner = true;
        foreach ($current_matches as $match) {
            if ($match['match_status'] !== 'completed') {
                $all_completed = false;
                error_log("發現未完成的比賽: " . $match['match_id']);
            }
            if (empty($match['winner_id'])) {
                $all_have_winner = false;
                error_log("發現沒有獲勝者的比賽: " . $match['match_id']);
            }
        }
        
        error_log("所有比賽完成狀態: " . ($all_completed ? "是" : "否"));
        error_log("所有比賽都有獲勝者: " . ($all_have_winner ? "是" : "否"));
        
        // 如果所有比賽都完成且有獲勝者，則創建半決賽
        if ($all_completed && $all_have_winner) {
            // 檢查是否已存在半決賽
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM knockout_brackets WHERE round_number = 2");
            $stmt->execute();
            $semifinal_exists = $stmt->fetchColumn() > 0;
            
            error_log("半決賽是否已存在: " . ($semifinal_exists ? "是" : "否"));
            
            if (!$semifinal_exists) {
                // 創建半決賽
                for ($i = 0; $i < count($current_matches); $i += 2) {
                    $winner1 = $current_matches[$i]['winner_id'];
                    $winner2 = $current_matches[$i + 1]['winner_id'];
                    
                    error_log("創建半決賽: 隊伍1 ID = $winner1, 隊伍2 ID = $winner2");
                    
                    // 插入新的半決賽
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (
                            match_type, tournament_stage, match_status,
                            team1_id, team2_id, match_date, match_number
                        ) VALUES (
                            'knockout', 'semi_final', 'pending',
                            ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY),
                            CONCAT('半決賽-', ?)
                        )
                    ");
                    $stmt->execute([$winner1, $winner2, ceil(($i + 2) / 2)]);
                    
                    $new_match_id = $pdo->lastInsertId();
                    error_log("創建的新比賽ID: " . $new_match_id);
                    
                    // 創建半決賽關係
                    $stmt = $pdo->prepare("
                        INSERT INTO knockout_brackets (
                            match_id, round_number, position_in_round
                        ) VALUES (?, 2, ?)
                    ");
                    $stmt->execute([$new_match_id, ceil(($i + 2) / 2)]);
                }
                
                error_log("半決賽創建完成");
            }
        }
        
        $pdo->commit();
        error_log("處理完成，提交事務");
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("處理淘汰賽錯誤: " . $e->getMessage());
        error_log("錯誤堆疊: " . $e->getTraceAsString());
        return false;
    }
}

// 執行處理並返回詳細信息
if (isset($_GET['process']) && $_GET['process'] === 'true') {
    $result = processKnockoutMatches();
    if ($result) {
        header('Location: knockout_bracket.php?message=' . urlencode('已處理晉級隊伍'));
    } else {
        header('Location: knockout_bracket.php?error=' . urlencode('處理晉級隊伍時發生錯誤，請查看錯誤日誌'));
    }
    exit;
}
