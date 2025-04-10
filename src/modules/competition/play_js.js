// 计分板变量
let team1Score = 0;
let team2Score = 0;
let team1Fouls = 0;
let team2Fouls = 0;

// 计时器变量
let timerInterval;
let timeLeft = 0;
let timerRunning = false;
let matchStatus = '';
let timerEnded = false;

// 初始化函数
function initMatch(initialTeam1Score, initialTeam2Score, initialTeam1Fouls, initialTeam2Fouls, initialTimeLeft, initialMatchStatus) {
    team1Score = initialTeam1Score;
    team2Score = initialTeam2Score;
    team1Fouls = initialTeam1Fouls;
    team2Fouls = initialTeam2Fouls;
    timeLeft = initialTimeLeft;
    matchStatus = initialMatchStatus;
    
    // 初始化计时器显示
    updateTimerDisplay();
    
    // 添加计时器按钮事件监听
    document.getElementById('toggle-timer').addEventListener('click', toggleTimer);
    
    // 美化比賽界面
    applyMatchStyles();
    
    // 键盘快捷键
    // 监听键盘事件
    document.addEventListener('keydown', function(event) {
        // 确保计时器已启动且比赛正在进行
        if (timerRunning) {
            switch(event.key.toUpperCase()) {
                case 'A': // 队伍1得分+1
                    updateTeam1Score(1);
                    break;
                case 'S': // 队伍1得分-1
                    updateTeam1Score(-1);
                    break;
                case 'D': // 队伍1犯规+1
                    updateTeam1Fouls(1);
                    break;
                case 'Q': // 队伍1犯规-1 (改用Q键替代X键)
                    updateTeam1Fouls(-1);
                    break;
                case 'K': // 队伍2得分+1
                    updateTeam2Score(1);
                    break;
                case 'L': // 队伍2得分-1
                    updateTeam2Score(-1);
                    break;
                case 'M': // 队伍2犯规+1
                    updateTeam2Fouls(1);
                    break;
                case 'W': // 队伍2犯规-1 (改用W键替代Z键)
                    updateTeam2Fouls(-1);
                    break;
                case ' ': // 空格键 - 开始/暂停计时器
                    toggleTimer();
                    event.preventDefault(); // 防止页面滾動
                    break;
            }
        }
    });
}

// 更新计时器显示
function updateTimerDisplay() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    document.getElementById('timer').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

// 开始/暂停计时器
function toggleTimer() {
    if (timerRunning) {
        clearInterval(timerInterval);
        document.getElementById('toggle-timer').textContent = '继续计时';
    } else {
        timerInterval = setInterval(function() {
            if (timeLeft > 0) {
                timeLeft--;
                updateTimerDisplay();
            } else {
                clearInterval(timerInterval);
                document.getElementById('toggle-timer').textContent = '时间结束';
                document.getElementById('toggle-timer').disabled = true;
                timerEnded = true;
                
                // 检查比赛结果
                checkMatchResult();
            }
        }, 1000);
        document.getElementById('toggle-timer').textContent = '暂停计时';
    }
    timerRunning = !timerRunning;
}

// 检查比赛结果
function checkMatchResult() {
    // 如果是加时赛且比分相同，显示裁判决定对话框
    if (matchStatus === 'overtime' && team1Score === team2Score) {
        showRefereeDecisionDialog();
    }
    // 如果是常规赛且比分相同，显示加时赛对话框
    else if (matchStatus === 'active' && team1Score === team2Score) {
        showOvertimeDialog();
    }
    // 否则显示结束比赛按钮
    else {
        const matchControls = document.getElementById('match-controls');
        if (matchControls) {
            matchControls.style.display = 'flex';
            const endMatchButton = document.querySelector('button[name="end_match"]');
            if (endMatchButton) {
                endMatchButton.style.animation = 'pulse 1s infinite';
                endMatchButton.style.backgroundColor = '#ff5722';
            }
        }
    }
}

// 更新队伍1得分
function updateTeam1Score(change) {
    // 如果计时器已结束，不允许修改分数
    if (timerEnded) return;
    
    team1Score += change;
    if (team1Score < 0) team1Score = 0;
    document.getElementById('team1-score').textContent = team1Score;
    document.getElementById('team1_score_input').value = team1Score;
}

// 更新队伍2得分
function updateTeam2Score(change) {
    // 如果计时器已结束，不允许修改分数
    if (timerEnded) return;
    
    team2Score += change;
    if (team2Score < 0) team2Score = 0;
    document.getElementById('team2-score').textContent = team2Score;
    document.getElementById('team2_score_input').value = team2Score;
}

// 更新队伍1犯规
function updateTeam1Fouls(change) {
    // 如果计时器已结束，不允许修改分数
    if (timerEnded) return;
    
    console.log('嘗試更新隊伍1犯規:', change);
    
    // 檢查元素是否存在
    const foulsElement = document.getElementById('team1-fouls');
    const inputElement = document.getElementById('team1_fouls_input');
    
    if (!foulsElement) {
        console.error('找不到元素: team1-fouls');
        return;
    }
    
    if (!inputElement) {
        console.error('找不到元素: team1_fouls_input');
        return;
    }
    
    team1Fouls += change;
    if (team1Fouls < 0) team1Fouls = 0;
    foulsElement.textContent = team1Fouls;
    inputElement.value = team1Fouls;
    
    console.log('隊伍1犯規已更新為:', team1Fouls);
}

// 更新队伍2犯规
function updateTeam2Fouls(change) {
    // 如果计时器已结束，不允许修改分数
    if (timerEnded) return;
    
    console.log('嘗試更新隊伍2犯規:', change);
    
    // 檢查元素是否存在
    const foulsElement = document.getElementById('team2-fouls');
    const inputElement = document.getElementById('team2_fouls_input');
    
    if (!foulsElement) {
        console.error('找不到元素: team2-fouls');
        return;
    }
    
    if (!inputElement) {
        console.error('找不到元素: team2_fouls_input');
        return;
    }
    
    team2Fouls += change;
    if (team2Fouls < 0) team2Fouls = 0;
    foulsElement.textContent = team2Fouls;
    inputElement.value = team2Fouls;
    
    console.log('隊伍2犯規已更新為:', team2Fouls);
}

// 显示加时赛对话框
function showOvertimeDialog() {
    const overtimeDialog = document.createElement('div');
    overtimeDialog.className = 'overtime-dialog';
    overtimeDialog.innerHTML = `
        <div class="overtime-content">
            <h3>进入加时赛</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="overtime_time_minutes">加时赛时间：</label>
                    <input type="number" id="overtime_time_minutes" name="overtime_time_minutes" min="0" max="10" value="2" required style="width: 60px;"> 分
                    <input type="number" id="overtime_time_seconds" name="overtime_time_seconds" min="0" max="59" value="0" required style="width: 60px;"> 秒
                </div>
                <input type="hidden" name="team1_score" value="${team1Score}">
                <input type="hidden" name="team2_score" value="${team2Score}">
                <input type="hidden" name="team1_fouls" value="${team1Fouls}">
                <input type="hidden" name="team2_fouls" value="${team2Fouls}">
                <div class="form-actions">
                    <button type="submit" name="start_overtime" class="button">开始加时赛</button>
                    <button type="button" id="cancel-overtime" class="button secondary">取消</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(overtimeDialog);
    
    document.getElementById('cancel-overtime').addEventListener('click', function() {
        document.body.removeChild(overtimeDialog);
    });
}

// 显示裁判决定对话框
function showRefereeDecisionDialog() {
    // 获取队伍名称
    const team1Name = document.querySelector('.team1 h3').textContent;
    const team2Name = document.querySelector('.team2 h3').textContent;
    
    const refereeDialog = document.createElement('div');
    refereeDialog.className = 'overtime-dialog';
    refereeDialog.innerHTML = `
        <div class="overtime-content">
            <h3>裁判决定</h3>
            <p>比赛结束，请裁判决定结果：</p>
            <div class="referee-buttons">
                <button type="button" class="button" onclick="submitRefereeDecision(team1Id)">${team1Name} 获胜</button>
                <button type="button" class="button secondary" onclick="submitRefereeDecision(0)">平局</button>
                <button type="button" class="button" onclick="submitRefereeDecision(team2Id)">${team2Name} 获胜</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(refereeDialog);
}

// 提交裁判决定
function submitRefereeDecision(winnerId) {
    // 创建一个表单并提交
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const winnerInput = document.createElement('input');
    winnerInput.type = 'hidden';
    winnerInput.name = 'winner_id';
    winnerInput.value = winnerId;
    
    const submitInput = document.createElement('input');
    submitInput.type = 'hidden';
    submitInput.name = 'referee_decision';
    submitInput.value = '1';
    
    // 添加当前的比分和犯规
    const team1ScoreInput = document.createElement('input');
    team1ScoreInput.type = 'hidden';
    team1ScoreInput.name = 'team1_score';
    team1ScoreInput.value = team1Score;
    
    const team2ScoreInput = document.createElement('input');
    team2ScoreInput.type = 'hidden';
    team2ScoreInput.name = 'team2_score';
    team2ScoreInput.value = team2Score;
    
    const team1FoulsInput = document.createElement('input');
    team1FoulsInput.type = 'hidden';
    team1FoulsInput.name = 'team1_fouls';
    team1FoulsInput.value = team1Fouls;
    
    const team2FoulsInput = document.createElement('input');
    team2FoulsInput.type = 'hidden';
    team2FoulsInput.name = 'team2_fouls';
    team2FoulsInput.value = team2Fouls;
    
    form.appendChild(winnerInput);
    form.appendChild(submitInput);
    form.appendChild(team1ScoreInput);
    form.appendChild(team2ScoreInput);
    form.appendChild(team1FoulsInput);
    form.appendChild(team2FoulsInput);
    
    document.body.appendChild(form);
    form.submit();
}

// 應用比賽界面樣式
function applyMatchStyles() {
    // 美化計時器
    const timerElement = document.getElementById('timer');
    if (timerElement) {
        timerElement.style.fontSize = '5rem';
        timerElement.style.fontWeight = 'bold';
        timerElement.style.textShadow = '2px 2px 4px rgba(0,0,0,0.3)';
        timerElement.style.color = '#2c3e50';
        timerElement.style.margin = '20px 0';
    }
    
    // 美化比賽狀態顯示
    const matchStatusElement = document.querySelector('.match-status');
    if (matchStatusElement) {
        matchStatusElement.style.fontSize = '1.8rem';
        matchStatusElement.style.fontWeight = 'bold';
        matchStatusElement.style.color = '#3498db';
        matchStatusElement.style.padding = '10px';
        matchStatusElement.style.borderRadius = '5px';
        matchStatusElement.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
        matchStatusElement.style.marginBottom = '20px';
    }
    
    // 美化隊伍顯示
    const teamElements = document.querySelectorAll('.team');
    teamElements.forEach(team => {
        team.style.padding = '20px';
        team.style.borderRadius = '10px';
        team.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        team.style.transition = 'all 0.3s ease';
    });
    
    // 美化隊伍1
    const team1Element = document.querySelector('.team1');
    if (team1Element) {
        team1Element.style.backgroundColor = 'rgba(231, 76, 60, 0.1)';
        team1Element.style.borderLeft = '5px solid #e74c3c';
    }
    
    // 美化隊伍2
    const team2Element = document.querySelector('.team2');
    if (team2Element) {
        team2Element.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
        team2Element.style.borderLeft = '5px solid #3498db';
    }
    
    // 美化分數顯示
    const scoreElements = document.querySelectorAll('.team-score');
    scoreElements.forEach(score => {
        score.style.fontSize = '6rem';
        score.style.fontWeight = 'bold';
        score.style.textAlign = 'center';
        score.style.margin = '10px 0';
    });
    
    // 美化VS顯示
    const vsElement = document.querySelector('.vs');
    if (vsElement) {
        vsElement.style.fontSize = '2.5rem';
        vsElement.style.fontWeight = 'bold';
        vsElement.style.color = '#7f8c8d';
        vsElement.style.margin = '0 20px';
        vsElement.style.alignSelf = 'center';
    }
    
    // 美化犯規顯示
    const foulsElements = document.querySelectorAll('.fouls');
    foulsElements.forEach(fouls => {
        fouls.style.fontSize = '1.2rem';
        fouls.style.padding = '5px 10px';
        fouls.style.borderRadius = '5px';
        fouls.style.backgroundColor = 'rgba(0,0,0,0.05)';
        fouls.style.display = 'inline-block';
        fouls.style.marginTop = '10px';
    });
    
    // 美化按鈕
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        if (!button.classList.contains('styled')) {
            button.classList.add('styled');
            button.style.padding = '10px 20px';
            button.style.borderRadius = '5px';
            button.style.border = 'none';
            button.style.cursor = 'pointer';
            button.style.fontWeight = 'bold';
            button.style.transition = 'all 0.3s ease';
        }
    });
    
    // 特別美化計時器按鈕
    const timerButton = document.getElementById('toggle-timer');
    if (timerButton) {
        timerButton.style.backgroundColor = '#2ecc71';
        timerButton.style.color = 'white';
        timerButton.style.fontSize = '1.2rem';
        timerButton.style.padding = '12px 25px';
        timerButton.style.marginTop = '15px';
        timerButton.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    }
}