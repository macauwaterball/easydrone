<?php
// 检查并更新数据库结构
function checkAndUpdateDatabaseStructure($pdo) {
    // 检查是否存在match_time_seconds字段
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'matches' 
        AND COLUMN_NAME = 'match_time_seconds'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // 添加match_time_seconds字段
        $pdo->exec("
            ALTER TABLE matches 
            ADD COLUMN match_time_seconds INT DEFAULT NULL AFTER match_time
        ");
    }
    
    // 检查是否存在overtime_time_seconds字段
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'matches' 
        AND COLUMN_NAME = 'overtime_time_seconds'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // 添加overtime_time_seconds字段
        $pdo->exec("
            ALTER TABLE matches 
            ADD COLUMN overtime_time_seconds INT DEFAULT NULL AFTER overtime_time
        ");
    }
    
    // 检查是否存在half_time字段
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'matches' 
        AND COLUMN_NAME = 'half_time'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // 添加half_time字段，用于标记上半场/下半场
        $pdo->exec("
            ALTER TABLE matches 
            ADD COLUMN half_time VARCHAR(20) DEFAULT 'first_half' AFTER match_status
        ");
    }
    
    // 检查是否存在team1_first_half_score和team2_first_half_score字段
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'matches' 
        AND COLUMN_NAME = 'team1_first_half_score'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // 添加上半场得分字段
        $pdo->exec("
            ALTER TABLE matches 
            ADD COLUMN team1_first_half_score INT DEFAULT 0 AFTER team1_score,
            ADD COLUMN team2_first_half_score INT DEFAULT 0 AFTER team2_score,
            ADD COLUMN team1_first_half_fouls INT DEFAULT 0 AFTER team1_fouls,
            ADD COLUMN team2_first_half_fouls INT DEFAULT 0 AFTER team2_fouls
        ");
    }
}

// 更新小组积分
function updateGroupStandings($pdo, $group_id, $team1_id, $team2_id, $winner_id) {
    // 获取当前积分
    $stmt = $pdo->prepare("
        SELECT * FROM group_standings 
        WHERE group_id = ? AND (team_id = ? OR team_id = ?)
    ");
    $stmt->execute([$group_id, $team1_id, $team2_id]);
    $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 整理积分数据
    $standingsData = [];
    foreach ($standings as $standing) {
        $standingsData[$standing['team_id']] = $standing;
    }
    
    // 更新队伍1积分
    if (!isset($standingsData[$team1_id])) {
        // 创建新记录
        $stmt = $pdo->prepare("
            INSERT INTO group_standings (group_id, team_id, played, won, drawn, lost, points)
            VALUES (?, ?, 1, ?, ?, ?, ?)
        ");
        
        if ($winner_id === null) {
            // 平局
            $stmt->execute([$group_id, $team1_id, 0, 1, 0, 1]);
        } elseif ($winner_id == $team1_id) {
            // 胜利
            $stmt->execute([$group_id, $team1_id, 1, 0, 0, 3]);
        } else {
            // 失败
            $stmt->execute([$group_id, $team1_id, 0, 0, 1, 0]);
        }
    } else {
        // 更新现有记录
        $played = $standingsData[$team1_id]['played'] + 1;
        $won = $standingsData[$team1_id]['won'];
        $drawn = $standingsData[$team1_id]['drawn'];
        $lost = $standingsData[$team1_id]['lost'];
        
        if ($winner_id === null) {
            // 平局
            $drawn++;
            $points = $standingsData[$team1_id]['points'] + 1;
        } elseif ($winner_id == $team1_id) {
            // 胜利
            $won++;
            $points = $standingsData[$team1_id]['points'] + 3;
        } else {
            // 失败
            $lost++;
            $points = $standingsData[$team1_id]['points'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE group_standings 
            SET played = ?, won = ?, drawn = ?, lost = ?, points = ?
            WHERE group_id = ? AND team_id = ?
        ");
        $stmt->execute([$played, $won, $drawn, $lost, $points, $group_id, $team1_id]);
    }
    
    // 更新队伍2积分
    if (!isset($standingsData[$team2_id])) {
        // 创建新记录
        $stmt = $pdo->prepare("
            INSERT INTO group_standings (group_id, team_id, played, won, drawn, lost, points)
            VALUES (?, ?, 1, ?, ?, ?, ?)
        ");
        
        if ($winner_id === null) {
            // 平局
            $stmt->execute([$group_id, $team2_id, 0, 1, 0, 1]);
        } elseif ($winner_id == $team2_id) {
            // 胜利
            $stmt->execute([$group_id, $team2_id, 1, 0, 0, 3]);
        } else {
            // 失败
            $stmt->execute([$group_id, $team2_id, 0, 0, 1, 0]);
        }
    } else {
        // 更新现有记录
        $played = $standingsData[$team2_id]['played'] + 1;
        $won = $standingsData[$team2_id]['won'];
        $drawn = $standingsData[$team2_id]['drawn'];
        $lost = $standingsData[$team2_id]['lost'];
        
        if ($winner_id === null) {
            // 平局
            $drawn++;
            $points = $standingsData[$team2_id]['points'] + 1;
        } elseif ($winner_id == $team2_id) {
            // 胜利
            $won++;
            $points = $standingsData[$team2_id]['points'] + 3;
        } else {
            // 失败
            $lost++;
            $points = $standingsData[$team2_id]['points'];
        }
        
        $stmt = $pdo->prepare("
            UPDATE group_standings 
            SET played = ?, won = ?, drawn = ?, lost = ?, points = ?
            WHERE group_id = ? AND team_id = ?
        ");
        $stmt->execute([$played, $won, $drawn, $lost, $points, $group_id, $team2_id]);
    }
}

// 根据犯规数量判断胜者
function determineWinnerByFouls($team1_id, $team2_id, $team1_fouls, $team2_fouls) {
    // 如果犯规数相同，返回null表示平局
    if ($team1_fouls == $team2_fouls) {
        return null;
    }
    
    // 返回犯规较少的队伍ID
    return ($team1_fouls < $team2_fouls) ? $team1_id : $team2_id;
}

// 结束上半场，开始下半场
function endFirstHalf($pdo, $match_id, $team1_score, $team2_score, $team1_fouls, $team2_fouls) {
    try {
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET half_time = 'second_half',
                team1_first_half_score = ?,
                team2_first_half_score = ?,
                team1_first_half_fouls = ?,
                team2_first_half_fouls = ?,
                team1_score = ?,
                team2_score = ?,
                team1_fouls = ?,
                team2_fouls = ?
            WHERE match_id = ?
        ");
        $stmt->execute([
            $team1_score, $team2_score, $team1_fouls, $team2_fouls,
            $team1_score, $team2_score, $team1_fouls, $team2_fouls,
            $match_id
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// 获取比赛半场状态
function getHalfTimeStatus($match) {
    return $match['half_time'] ?? 'first_half';
}

// 检查是否需要进入加时赛
function needOvertime($team1_score, $team2_score) {
    return $team1_score == $team2_score;
}