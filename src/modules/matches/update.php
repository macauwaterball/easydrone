// 在更新比賽結果後，添加以下代碼來更新淘汰賽下一輪的隊伍

// 如果是淘汰賽且比賽已完成
if ($match_type == 'knockout' && $match_status == 'completed' && $winner_id) {
    // 查詢此比賽在淘汰賽結構中的下一場比賽
    $stmt = $pdo->prepare("
        SELECT next_match_id, position_in_round 
        FROM knockout_brackets 
        WHERE match_id = ?
    ");
    $stmt->execute([$match_id]);
    $bracket = $stmt->fetch();
    
    if ($bracket && $bracket['next_match_id']) {
        $next_match_id = $bracket['next_match_id'];
        $position = $bracket['position_in_round'];
        
        // 確定獲勝隊伍應該放在下一場比賽的哪個位置
        $team_field = ($position % 2 == 1) ? 'team1_id' : 'team2_id';
        
        // 更新下一場比賽的隊伍
        $stmt = $pdo->prepare("
            UPDATE matches 
            SET $team_field = ? 
            WHERE match_id = ?
        ");
        $stmt->execute([$winner_id, $next_match_id]);
    }
    
    // 如果是半決賽，還需要更新季軍賽的隊伍
    $stmt = $pdo->prepare("
        SELECT tournament_stage FROM matches WHERE match_id = ?
    ");
    $stmt->execute([$match_id]);
    $match_stage = $stmt->fetchColumn();
    
    if ($match_stage == 'semi_final') {
        // 查找季軍賽
        $stmt = $pdo->prepare("
            SELECT m.match_id 
            FROM matches m
            JOIN knockout_brackets kb ON m.match_id = kb.match_id
            WHERE m.tournament_stage = 'third_place' 
            AND kb.is_winner_bracket = 0
        ");
        $stmt->execute();
        $third_place_match = $stmt->fetchColumn();
        
        if ($third_place_match) {
            // 獲取輸方ID
            $loser_id = ($match['team1_id'] == $winner_id) ? $match['team2_id'] : $match['team1_id'];
            
            // 確定應該放在季軍賽的哪個位置
            $team_field = ($position % 2 == 1) ? 'team1_id' : 'team2_id';
            
            // 更新季軍賽的隊伍
            $stmt = $pdo->prepare("
                UPDATE matches 
                SET $team_field = ? 
                WHERE match_id = ?
            ");
            $stmt->execute([$loser_id, $third_place_match]);
        }
    }
}