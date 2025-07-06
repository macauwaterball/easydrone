<?php
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
                match_time = ?,
                match_time_seconds = ?,
                half_time = 'first_half',
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
    // 检查是否是结束上半场的请求
    if (!isset($_POST['end_first_half'])) {
        return null;
    }
    
    // 获取表单提交的分数和犯规
    $team1_score = isset($_POST['team1_score']) ? (int)$_POST['team1_score'] : $match['team1_score'];
    $team2_score = isset($_POST['team2_score']) ? (int)$_POST['team2_score'] : $match['team2_score'];
    $team1_fouls = isset($_POST['team1_fouls']) ? (int)$_POST['team1_fouls'] : $match['team1_fouls'];
    $team2_fouls = isset($_POST['team2_fouls']) ? (int)$_POST['team2_fouls'] : $match['team2_fouls'];
    
    // 添加调试信息
    error_log("处理上半场结束: match_id=$match_id, team1_score=$team1_score, team2_score=$team2_score, team1_fouls=$team1_fouls, team2_fouls=$team2_fouls");
    
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
    if (isset($_POST['referee_decision'])) {
        $winner_id = $_POST['winner_id'];
        $team1_score = $_POST['team1_score'];
        $team2_score = $_POST['team2_score'];
        $team1_fouls = $_POST['team1_fouls'];
        $team2_fouls = $_POST['team2_fouls'];
        
        // 更新比赛结果
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET match_status = 'completed',
                team1_score = ?,
                team2_score = ?,
                team1_fouls = ?,
                team2_fouls = ?,
                winner_id = ?,
                referee_decision = 1
            WHERE match_id = ?
        ");
        
        // 这里需要确保 winner_id 为 0 时也能正确处理（表示平局）
        $stmt->execute([$team1_score, $team2_score, $team1_fouls, $team2_fouls, $winner_id, $match_id]);
        
        // 重定向到比赛页面
        header("Location: play.php?match_id=$match_id");
        exit;
    }
    return null;
}

// 处理比赛结束
function handleEndMatch($pdo, $match_id, $match) {
    if (isset($_POST['end_match'])) {
        try {
            $pdo->beginTransaction();
            
            // 如果是上半场结束，则转为下半场
            if ($match['match_status'] === 'active' && $match['half_time'] === 'first_half') {
                return handleEndFirstHalf($pdo, $match_id, $match);
            }
            
            // 如果是下半场结束或加时赛结束
            if (($match['match_status'] === 'active' && $match['half_time'] === 'second_half') || 
                $match['match_status'] === 'overtime') {
                
                $team1_score = (int)$_POST['team1_score'];
                $team2_score = (int)$_POST['team2_score'];
                $team1_fouls = (int)$_POST['team1_fouls'];
                $team2_fouls = (int)$_POST['team2_fouls'];
                
                // 如果平局，根据犯规数量判断胜者
                if ($team1_score == $team2_score) {
                    $winner_id = determineWinnerByFouls($match['team1_id'], $match['team2_id'], $team1_fouls, $team2_fouls);
                    
                    // 如果犯规也相同，且不是加时赛，则进入加时赛
                    if ($winner_id === null && $match['match_status'] !== 'overtime') {
                        $show_overtime = true;
                        return null;
                    }
                } else {
                    // 根据得分判断胜者
                    $winner_id = ($team1_score > $team2_score) ? $match['team1_id'] : $match['team2_id'];
                }
                
                // 更新比赛状态为已完成
                $stmt = $pdo->prepare("
                    UPDATE matches 
                    SET match_status = 'completed',
                        team1_score = ?,
                        team2_score = ?,
                        team1_fouls = ?,
                        team2_fouls = ?,
                        winner_id = ?
                    WHERE match_id = ?
                ");
                $stmt->execute([
                    $team1_score,
                    $team2_score,
                    $team1_fouls,
                    $team2_fouls,
                    $winner_id,
                    $match_id
                ]);
                
                // 添加淘汰赛处理逻辑
                if ($match['match_type'] === 'knockout') {
                    require_once __DIR__ . '/../tournaments/process_knockout.php';
                    processKnockoutMatches();
                }
                
                $pdo->commit();
                
                // 重定向到比赛列表页面
                header('Location: /modules/creatematches/list.php?message=' . urlencode('比赛已成功结束'));
                exit;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            return "处理比赛结束时发生错误：" . $e->getMessage();
        }
    }
    return null;
}

?>
