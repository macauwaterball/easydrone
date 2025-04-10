<?php
require_once __DIR__ . '/play_functions.php';

// 处理比赛开始
function handleStartMatch($pdo, $match_id, $match) {
    if (!isset($_POST['start_match']) || $match['match_status'] !== 'pending') {
        return null;
    }
    
    $match_time_minutes = (int)$_POST['match_time_minutes'];
    $match_time_seconds = (int)$_POST['match_time_seconds'];
    
    // 确保至少有1秒的比赛时间
    if ($match_time_minutes == 0 && $match_time_seconds == 0) {
        return "比赛时间必须大于0秒";
    }
    
    $match_time = $match_time_minutes + ($match_time_seconds / 60); // 转换为分钟（小数形式）
    $match_time_total_seconds = ($match_time_minutes * 60) + $match_time_seconds; // 总秒数，用于前端显示
    
    try {
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET match_status = 'active', 
                half_time = 'first_half',
                match_time = ?,
                match_time_seconds = ?,
                start_time = NOW()
            WHERE match_id = ?
        ");
        $stmt->execute([$match_time, $match_time_total_seconds, $match_id]);
        
        // 重新加载页面以反映更改
        header("Location: play.php?match_id=$match_id");
        exit;
    } catch (PDOException $e) {
        return "启动比赛失败: " . $e->getMessage();
    }
}

// 处理上半场结束，开始下半场
function handleEndFirstHalf($pdo, $match_id, $match) {
    if (!isset($_POST['end_first_half']) || $match['match_status'] !== 'active' || $match['half_time'] !== 'first_half') {
        return null;
    }
    
    $team1_score = (int)$_POST['team1_score'];
    $team2_score = (int)$_POST['team2_score'];
    $team1_fouls = (int)$_POST['team1_fouls'];
    $team2_fouls = (int)$_POST['team2_fouls'];
    
    if (endFirstHalf($pdo, $match_id, $team1_score, $team2_score, $team1_fouls, $team2_fouls)) {
        // 重新加载页面以反映更改
        header("Location: play.php?match_id=$match_id");
        exit;
    } else {
        return "结束上半场失败";
    }
}

// 处理加时赛开始
function handleStartOvertime($pdo, $match_id, $match) {
    if (!isset($_POST['start_overtime']) || $match['match_status'] !== 'active' || $match['half_time'] !== 'second_half') {
        return null;
    }
    
    $overtime_time_minutes = (int)$_POST['overtime_time_minutes'];
    $overtime_time_seconds = (int)$_POST['overtime_time_seconds'];
    
    // 确保至少有1秒的加时赛时间
    if ($overtime_time_minutes == 0 && $overtime_time_seconds == 0) {
        return "加时赛时间必须大于0秒";
    }
    
    $overtime_time = $overtime_time_minutes + ($overtime_time_seconds / 60); // 转换为分钟（小数形式）
    $overtime_time_total_seconds = ($overtime_time_minutes * 60) + $overtime_time_seconds; // 总秒数，用于前端显示
    
    $team1_score = (int)$_POST['team1_score'];
    $team2_score = (int)$_POST['team2_score'];
    $team1_fouls = (int)$_POST['team1_fouls'];
    $team2_fouls = (int)$_POST['team2_fouls'];
    
    // 只有在平局的情况下才进入加时赛
    if ($team1_score != $team2_score) {
        return "只有在平局的情况下才能进入加时赛";
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET match_status = 'overtime', 
                overtime_time = ?,
                overtime_time_seconds = ?,
                team1_score = ?,
                team2_score = ?,
                team1_fouls = ?,
                team2_fouls = ?
            WHERE match_id = ?
        ");
        $stmt->execute([$overtime_time, $overtime_time_total_seconds, $team1_score, $team2_score, $team1_fouls, $team2_fouls, $match_id]);
        
        // 重新加载页面以反映更改
        header("Location: play.php?match_id=$match_id");
        exit;
    } catch (PDOException $e) {
        return "启动加时赛失败: " . $e->getMessage();
    }
}

// 处理裁判决定
function handleRefereeDecision($pdo, $match_id, $match) {
    if (!isset($_POST['referee_decision']) || $match['match_status'] !== 'overtime') {
        return null;
    }
    
    $winner_id = (int)$_POST['winner_id'];
    $team1_score = (int)$_POST['team1_score'];
    $team2_score = (int)$_POST['team2_score'];
    $team1_fouls = (int)$_POST['team1_fouls'];
    $team2_fouls = (int)$_POST['team2_fouls'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET match_status = 'completed', 
                winner_id = ?,
                team1_score = ?,
                team2_score = ?,
                team1_fouls = ?,
                team2_fouls = ?,
                end_time = NOW()
            WHERE match_id = ?
        ");
        $stmt->execute([$winner_id, $team1_score, $team2_score, $team1_fouls, $team2_fouls, $match_id]);
        
        // 如果是小组赛，更新积分
        if ($match['group_id']) {
            updateGroupStandings($pdo, $match['group_id'], $match['team1_id'], $match['team2_id'], $winner_id);
        }
        
        // 重新加载页面以反映更改
        header("Location: play.php?match_id=$match_id");
        exit;
    } catch (PDOException $e) {
        return "更新比赛结果失败: " . $e->getMessage();
    }
}

// 处理比赛结束
function handleEndMatch($pdo, $match_id, $match) {
    if (!isset($_POST['end_match'])) {
        return null;
    }
    
    // 如果是上半场结束，则转为下半场
    if ($match['match_status'] === 'active' && $match['half_time'] === 'first_half') {
        return handleEndFirstHalf($pdo, $match_id, $match);
    }
    
    // 如果是下半场结束
    if ($match['match_status'] === 'active' && $match['half_time'] === 'second_half') {
        $team1_score = (int)$_POST['team1_score'];
        $team2_score = (int)$_POST['team2_score'];
        $team1_fouls = (int)$_POST['team1_fouls'];
        $team2_fouls = (int)$_POST['team2_fouls'];
        
        // 如果平局，根据犯规数量判断胜者
        if ($team1_score == $team2_score) {
            $winner_id = determineWinnerByFouls($match['team1_id'], $match['team2_id'], $team1_fouls, $team2_fouls);
            
            // 如果犯规也相同，则进入加时赛
            if ($winner_id === null) {
                // 显示加时赛对话框
                $show_overtime = true;
                return null;
            }
        } else {
            // 根据得分判断胜者
            $winner_id = ($team1_score > $team2_score) ? $match['team1_id'] : $match['team2_id'];
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE matches 
                SET match_status = 'completed', 
                    winner_id = ?,
                    team1_score = ?,
                    team2_score = ?,
                    team1_fouls = ?,
                    team2_fouls = ?,
                    end_time = NOW()
                WHERE match_id = ?
            ");
            $stmt->execute([$winner_id, $team1_score, $team2_score, $team1_fouls, $team2_fouls, $match_id]);
            
            // 如果是小组赛，更新积分
            if ($match['group_id']) {
                updateGroupStandings($pdo, $match['group_id'], $match['team1_id'], $match['team2_id'], $winner_id);
            }
            
            // 重新加载页面以反映更改
            header("Location: play.php?match_id=$match_id");
            exit;
        } catch (PDOException $e) {
            return "更新比赛结果失败: " . $e->getMessage();
        }
    }
    
    // 如果是加时赛结束
    if ($match['match_status'] === 'overtime') {
        $team1_score = (int)$_POST['team1_score'];
        $team2_score = (int)$_POST['team2_score'];
        $team1_fouls = (int)$_POST['team1_fouls'];
        $team2_fouls = (int)$_POST['team2_fouls'];
        
        // 如果加时赛后仍然平局，根据犯规数量判断胜者
        if ($team1_score == $team2_score) {
            $winner_id = determineWinnerByFouls($match['team1_id'], $match['team2_id'], $team1_fouls, $team2_fouls);
            
            // 如果犯规也相同，则需要裁判决定
            if ($winner_id === null) {
                // 显示裁判决定对话框
                $show_referee_decision = true;
                return null;
            }
        } else {
            // 根据得分判断胜者
            $winner_id = ($team1_score > $team2_score) ? $match['team1_id'] : $match['team2_id'];
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE matches 
                SET match_status = 'completed', 
                    winner_id = ?,
                    team1_score = ?,
                    team2_score = ?,
                    team1_fouls = ?,
                    team2_fouls = ?,
                    end_time = NOW()
                WHERE match_id = ?
            ");
            $stmt->execute([$winner_id, $team1_score, $team2_score, $team1_fouls, $team2_fouls, $match_id]);
            
            // 如果是小组赛，更新积分
            if ($match['group_id']) {
                updateGroupStandings($pdo, $match['group_id'], $match['team1_id'], $match['team2_id'], $winner_id);
            }
            
            // 重新加载页面以反映更改
            header("Location: play.php?match_id=$match_id");
            exit;
        } catch (PDOException $e) {
            return "更新比赛结果失败: " . $e->getMessage();
        }
    }
    
    return null;
}