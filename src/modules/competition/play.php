<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/play_functions.php';
require_once __DIR__ . '/play_handlers.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// 检查数据库结构
checkAndUpdateDatabaseStructure($pdo);

$match_id = $_GET['match_id'] ?? 0;

// 获取比赛信息
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
    header('Location: /modules/creatematches/list.php');
    exit;
}

// 处理各种请求
$error_message = null;
$error_message = handleStartMatch($pdo, $match_id, $match) ?? $error_message;
$error_message = handleEndFirstHalf($pdo, $match_id, $match) ?? $error_message;
$error_message = handleStartOvertime($pdo, $match_id, $match) ?? $error_message;
$error_message = handleRefereeDecision($pdo, $match_id, $match) ?? $error_message;
$error_message = handleEndMatch($pdo, $match_id, $match) ?? $error_message;

// 如果有任何处理程序重定向，这里不会执行
// 重新获取比赛信息，以防它已更新
$stmt->execute([$match_id]);
$match = $stmt->fetch();

$pageTitle = '比赛进行：' . $match['team1_name'] . ' vs ' . $match['team2_name'];
include __DIR__ . '/../../includes/header.php';

// 获取半场状态
$halfTime = getHalfTimeStatus($match);

// 检查是否需要显示加时赛对话框
$show_overtime = isset($_GET['show_overtime']) ? true : false;

// 检查是否需要显示裁判决定对话框
$show_referee_decision = isset($_GET['referee_decision']) ? true : false;
?>

<div class="match-play-container">
    <h2>比赛进行</h2>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    
    <div class="match-info">
        <?php if ($match['group_id']): ?>
            <p><strong>小组：</strong> <?= htmlspecialchars($match['group_name']) ?></p>
        <?php else: ?>
            <p><strong>比赛类别：</strong> <?= $match['match_type'] ? htmlspecialchars($match['match_type']) : '淘汰赛' ?></p>
        <?php endif; ?>
        <p><strong>场次：</strong> <?= htmlspecialchars($match['match_number']) ?></p>
        <p><strong>状态：</strong> 
            <?php
            switch ($match['match_status']) {
                case 'pending':
                    echo '待开始';
                    break;
                case 'active':
                    echo ($halfTime === 'first_half') ? '上半场' : '下半场';
                    break;
                case 'overtime':
                    echo '加时赛';
                    break;
                case 'completed':
                    echo '已结束';
                    break;
                default:
                    echo '未知';
            }
            ?>
        </p>
    </div>
    
    <?php if ($match['match_status'] === 'pending'): ?>
        <!-- 比赛设置表单 -->
        <div class="match-setup">
            <h3>开始比赛</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="match_time_minutes">比赛时间（每半场）：</label>
                    <input type="number" id="match_time_minutes" name="match_time_minutes" min="0" max="60" value="5" required style="width: 60px;"> 分
                    <input type="number" id="match_time_seconds" name="match_time_seconds" min="0" max="59" value="0" required style="width: 60px;"> 秒
                </div>
                <button type="submit" name="start_match" class="button">开始比赛</button>
            </form>
        </div>
    <?php elseif ($match['match_status'] === 'active' || $match['match_status'] === 'overtime'): ?>
        <!-- 比赛计分板 -->
        <div class="scoreboard">
            <div class="timer-container">
                <div id="half-time-indicator" class="half-time-indicator">
                    <?php if ($match['match_status'] === 'overtime'): ?>
                        加时赛
                    <?php else: ?>
                        <?= ($halfTime === 'first_half') ? '上半场' : '下半场' ?>
                    <?php endif; ?>
                </div>
                <div id="timer">00:00</div>
                <button id="toggle-timer" class="button">开始计时</button>
            </div>
            
            <div class="teams-container">
                <div class="team team1">
                    <h3><?= htmlspecialchars($match['team1_name']) ?></h3>
                    <div class="score-display">
                        <div class="score" id="team1-score"><?= $match['team1_score'] ?? 0 ?></div>
                    </div>
                    <div class="fouls-display">
                        <span class="fouls-label">犯规：</span>
                        <span class="fouls" id="team1-fouls"><?= $match['team1_fouls'] ?? 0 ?></span>
                    </div>
                    <?php if ($halfTime === 'second_half' && isset($match['team1_first_half_score'])): ?>
                    <div class="first-half-stats">
                        <span>上半场：<?= $match['team1_first_half_score'] ?>分 / <?= $match['team1_first_half_fouls'] ?>犯规</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="vs">VS</div>
                
                <div class="team team2">
                    <h3><?= htmlspecialchars($match['team2_name']) ?></h3>
                    <div class="score-display">
                        <div class="score" id="team2-score"><?= $match['team2_score'] ?? 0 ?></div>
                    </div>
                    <div class="fouls-display">
                        <span class="fouls-label">犯规：</span>
                        <span class="fouls" id="team2-fouls"><?= $match['team2_fouls'] ?? 0 ?></span>
                    </div>
                    <?php if ($halfTime === 'second_half' && isset($match['team2_first_half_score'])): ?>
                    <div class="first-half-stats">
                        <span>上半场：<?= $match['team2_first_half_score'] ?>分 / <?= $match['team2_first_half_fouls'] ?>犯规</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="match-controls" id="match-controls" style="display: none;">
                <form method="POST" action="" id="end-match-form">
                    <input type="hidden" name="team1_score" id="team1_score_input" value="<?= $match['team1_score'] ?? 0 ?>">
                    <input type="hidden" name="team2_score" id="team2_score_input" value="<?= $match['team2_score'] ?? 0 ?>">
                    <input type="hidden" name="team1_fouls" id="team1_fouls_input" value="<?= $match['team1_fouls'] ?? 0 ?>">
                    <input type="hidden" name="team2_fouls" id="team2_fouls_input" value="<?= $match['team2_fouls'] ?? 0 ?>">
                    <button type="submit" name="<?= ($halfTime === 'first_half') ? 'end_first_half' : 'end_match' ?>" class="button danger">
                        <?= ($halfTime === 'first_half') ? '结束上半场' : '结束比赛' ?>
                    </button>
                </form>
            </div>
            
            <div class="keyboard-shortcuts">
                <h5>键盘快捷键：</h5>
                <ul>
                    <li><strong>A</strong> - 队伍1得分+1</li>
                    <li><strong>S</strong> - 队伍1得分-1</li>
                    <li><strong>D</strong> - 队伍1犯规+1</li>
                    <li><strong>X</strong> - 队伍1犯规-1</li>
                    <li><strong>K</strong> - 队伍2得分+1</li>
                    <li><strong>L</strong> - 队伍2得分-1</li>
                    <li><strong>M</strong> - 队伍2犯规+1</li>
                    <li><strong>N</strong> - 队伍2犯规-1</li>
                    <li><strong>空格</strong> - 开始/暂停计时器</li>
                </ul>
            </div>
        </div>
        
        <?php if ($show_overtime): ?>
        <!-- 加时赛对话框 -->
        <div class="overtime-dialog" id="overtime-dialog">
            <div class="overtime-content">
                <h3>进入加时赛</h3>
                <p>比分相同，且犯规数也相同，需要进行加时赛。</p>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="overtime_time_minutes">加时赛时间：</label>
                        <input type="number" id="overtime_time_minutes" name="overtime_time_minutes" min="0" max="10" value="2" required style="width: 60px;"> 分
                        <input type="number" id="overtime_time_seconds" name="overtime_time_seconds" min="0" max="59" value="0" required style="width: 60px;"> 秒
                    </div>
                    <input type="hidden" name="team1_score" value="<?= $match['team1_score'] ?>">
                    <input type="hidden" name="team2_score" value="<?= $match['team2_score'] ?>">
                    <input type="hidden" name="team1_fouls" value="<?= $match['team1_fouls'] ?>">
                    <input type="hidden" name="team2_fouls" value="<?= $match['team2_fouls'] ?>">
                    <div class="form-actions">
                        <button type="submit" name="start_overtime" class="button">开始加时赛</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($show_referee_decision): ?>
        <!-- 裁判决定对话框 -->
        <div class="referee-dialog" id="referee-decision-dialog">
            <div class="referee-content">
                <h3>裁判决定</h3>
                <p>加时赛结束，比分相同且犯规数也相同，请裁判决定胜者：</p>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>
                            <input type="radio" name="winner_id" value="<?= $match['team1_id'] ?>" required>
                            <?= htmlspecialchars($match['team1_name']) ?> 获胜
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="winner_id" value="<?= $match['team2_id'] ?>">
                            <?= htmlspecialchars($match['team2_name']) ?> 获胜
                        </label>
                    </div>
                    <input type="hidden" name="team1_score" value="<?= $match['team1_score'] ?>">
                    <input type="hidden" name="team2_score" value="<?= $match['team2_score'] ?>">
                    <input type="hidden" name="team1_fouls" value="<?= $match['team1_fouls'] ?>">
                    <input type="hidden" name="team2_fouls" value="<?= $match['team2_fouls'] ?>">
                    <button type="submit" name="referee_decision" class="button">确认决定</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
    <?php elseif ($match['match_status'] === 'completed'): ?>
        <!-- 比赛结果 -->
        <div class="match-result">
            <h3>比赛结果</h3>
            <div class="teams-container">
                <div class="team team1 <?= $match['winner_id'] == $match['team1_id'] ? 'winner' : '' ?>">
                    <h3><?= htmlspecialchars($match['team1_name']) ?></h3>
                    <div class="score-display">
                        <div class="score"><?= $match['team1_score'] ?></div>
                    </div>
                    <div class="fouls-display">
                        <span class="fouls-label">犯规：</span>
                        <span class="fouls"><?= $match['team1_fouls'] ?></span>
                    </div>
                    <?php if (isset($match['team1_first_half_score'])): ?>
                    <div class="first-half-stats">
                        <span>上半场：<?= $match['team1_first_half_score'] ?>分 / <?= $match['team1_first_half_fouls'] ?>犯规</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="vs">VS</div>
                
                <div class="team team2 <?= $match['winner_id'] == $match['team2_id'] ? 'winner' : '' ?>">
                    <h3><?= htmlspecialchars($match['team2_name']) ?></h3>
                    <div class="score-display">
                        <div class="score"><?= $match['team2_score'] ?></div>
                    </div>
                    <div class="fouls-display">
                        <span class="fouls-label">犯规：</span>
                        <span class="fouls"><?= $match['team2_fouls'] ?></span>
                    </div>
                    <?php if (isset($match['team2_first_half_score'])): ?>
                    <div class="first-half-stats">
                        <span>上半场：<?= $match['team2_first_half_score'] ?>分 / <?= $match['team2_first_half_fouls'] ?>犯规</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="winner-display">
                <h4>
                    <?php if ($match['winner_id'] == $match['team1_id']): ?>
                        <?= htmlspecialchars($match['team1_name']) ?> 获胜!
                    <?php elseif ($match['winner_id'] == $match['team2_id']): ?>
                        <?= htmlspecialchars($match['team2_name']) ?> 获胜!
                    <?php else: ?>
                        平局
                    <?php endif; ?>
                </h4>
                <?php if ($match['referee_decision']): ?>
                    <p class="referee-note">（裁判决定）</p>
                <?php endif; ?>
            </div>
            
            <div class="match-actions">
                <a href="/modules/creatematches/list.php" class="button">返回比赛列表</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($match['match_status'] === 'active' || $match['match_status'] === 'overtime'): ?>
    // 计分板变量
    let team1Score = <?= $match['team1_score'] ?? 0 ?>;
    let team2Score = <?= $match['team2_score'] ?? 0 ?>;
    let team1Fouls = <?= $match['team1_fouls'] ?? 0 ?>;
    let team2Fouls = <?= $match['team2_fouls'] ?? 0 ?>;

    // 计时器变量
    let timerInterval;
    let timeLeft = <?php 
        if ($match['match_status'] === 'active') {
            echo isset($match['match_time_seconds']) && $match['match_time_seconds'] > 0 
                ? $match['match_time_seconds'] 
                : ($match['match_time'] * 60);
        } else {
            echo isset($match['overtime_time_seconds']) && $match['overtime_time_seconds'] > 0
                ? $match['overtime_time_seconds'] 
                : ($match['overtime_time'] * 60);
        }
    ?>;
    let timerRunning = false;
    let matchStatus = '<?= $match['match_status'] ?>';
    let halfTime = '<?= $halfTime ?>';

    // 初始化计时器显示
    updateTimerDisplay();
    
    // 显示比赛控制按钮
    document.getElementById('match-controls').style.display = 'block';
    
    // 添加计时器按钮事件监听
    document.getElementById('toggle-timer').addEventListener('click', toggleTimer);
    
    // 修改结束比赛按钮的行为
    const endMatchForm = document.getElementById('end-match-form');
    if (endMatchForm) {
        endMatchForm.addEventListener('submit', function(event) {
            // 更新隐藏字段的值
            document.getElementById('team1_score_input').value = team1Score;
            document.getElementById('team2_score_input').value = team2Score;
            document.getElementById('team1_fouls_input').value = team1Fouls;
            document.getElementById('team2_fouls_input').value = team2Fouls;
        });
    }
    
    // 添加键盘快捷键
    document.addEventListener('keydown', handleKeyPress);
    
    // 更新计时器显示
    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        document.getElementById('timer').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // 如果时间到了，停止计时器并显示提示
        if (timeLeft <= 0 && timerRunning) {
            stopTimer();
            
            // 播放声音提示
            playTimerEndSound();
            
            // 显示时间结束提示
            alert('时间结束！');
        }
    }
    
    // 开始/停止计时器
    function toggleTimer() {
        if (timerRunning) {
            stopTimer();
        } else {
            startTimer();
        }
    }
    
    // 开始计时器
    function startTimer() {
        if (timeLeft <= 0) return;
        
        timerRunning = true;
        document.getElementById('toggle-timer').textContent = '暂停计时';
        
        timerInterval = setInterval(function() {
            timeLeft--;
            updateTimerDisplay();
        }, 1000);
    }
    
    // 停止计时器
    function stopTimer() {
        timerRunning = false;
        document.getElementById('toggle-timer').textContent = '开始计时';
        clearInterval(timerInterval);
    }
    
    // 播放计时器结束声音
    function playTimerEndSound() {
        // 创建音频元素
        const audio = new Audio('/assets/sounds/timer_end.mp3');
        audio.play().catch(e => console.log('无法播放声音：', e));
    }
    
    // 处理键盘快捷键
    function handleKeyPress(event) {
        // 如果有输入框获得焦点，不处理快捷键
        if (document.activeElement.tagName === 'INPUT' || 
            document.activeElement.tagName === 'TEXTAREA') {
            return;
        }
        
        switch (event.key.toLowerCase()) {
            case 'a': // 队伍1 +1分
                team1Score++;
                document.getElementById('team1-score').textContent = team1Score;
                break;
            case 's': // 队伍1 -1分
                if (team1Score > 0) {
                    team1Score--;
                    document.getElementById('team1-score').textContent = team1Score;
                }
                break;
            case 'd': // 队伍1 +1犯规
                team1Fouls++;
                document.getElementById('team1-fouls').textContent = team1Fouls;
                break;
            case 'x': // 队伍1 -1犯规
                if (team1Fouls > 0) {
                    team1Fouls--;
                    document.getElementById('team1-fouls').textContent = team1Fouls;
                }
                break;
            case 'k': // 队伍2 +1分
                team2Score++;
                document.getElementById('team2-score').textContent = team2Score;
                break;
            case 'l': // 队伍2 -1分
                if (team2Score > 0) {
                    team2Score--;
                    document.getElementById('team2-score').textContent = team2Score;
                }
                break;
            case 'm': // 队伍2 +1犯规
                team2Fouls++;
                document.getElementById('team2-fouls').textContent = team2Fouls;
                break;
            case 'n': // 队伍2 -1犯规
                if (team2Fouls > 0) {
                    team2Fouls--;
                    document.getElementById('team2-fouls').textContent = team2Fouls;
                }
                break;
            case ' ': // 空格键：开始/暂停计时
                event.preventDefault(); // 防止页面滚动
                toggleTimer();
                break;
        }
    }
    <?php endif; ?>
});
</script>

<style>
.match-play-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.match-info {
    margin-bottom: 20px;
    padding: 10px;
    background-color: #f5f5f5;
    border-radius: 5px;
}

.scoreboard {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 20px;
}

.timer-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 20px;
}

.half-time-indicator {
    font-size: 1.2em;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

#timer {
    font-size: 3em;
    font-weight: bold;
    margin-bottom: 10px;
}

.teams-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-bottom: 20px;
}

.team {
    flex: 1;
    text-align: center;
    padding: 10px;
    border-radius: 5px;
}

.team h3 {
    margin-top: 0;
}

.vs {
    margin: 0 20px;
    font-size: 1.5em;
    font-weight: bold;
}

.score-display {
    margin-bottom: 10px;
}

.score {
    font-size: 3em;
    font-weight: bold;
}

.fouls-display {
    font-size: 1.2em;
}

.first-half-stats {
    margin-top: 10px;
    font-size: 0.9em;
    color: #666;
}

.match-controls {
    margin-top: 20px;
    text-align: center;
}

.keyboard-shortcuts {
    margin-top: 30px;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}

.keyboard-shortcuts ul {
    columns: 2;
    list-style-type: none;
    padding-left: 0;
}

.keyboard-shortcuts li {
    margin-bottom: 5px;
}

.match-result {
    text-align: center;
}

.winner {
    background-color: #e8f5e9;
    border: 2px solid #4caf50;
}

.winner-display {
    margin-top: 20px;
    font-size: 1.5em;
    color: #4caf50;
}

.referee-note {
    font-size: 0.8em;
    color: #666;
    margin-top: 5px;
}

.match-actions {
    margin-top: 30px;
}

.overtime-dialog, .referee-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.overtime-content, .referee-content {
    background-color: white;
    padding: 20px;
    border-radius: 5px;
    max-width: 500px;
    width: 100%;
}

.form-actions {
    margin-top: 20px;
    text-align: center;
}

.button {
    display: inline-block;
    padding: 8px 16px;
    background-color: #4caf50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
}

.button:hover {
    background-color: #45a049;
}

.button.danger {
    background-color: #f44336;
}

.button.danger:hover {
    background-color: #d32f2f;
}

.error-message {
    color: #f44336;
    margin-bottom: 15px;
    padding: 10px;
    background-color: #ffebee;
    border-radius: 4px;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>