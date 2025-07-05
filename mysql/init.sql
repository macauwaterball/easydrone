-- 創建數據庫（如果不存在）
CREATE DATABASE IF NOT EXISTS drone_soccer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drone_soccer;

-- 管理員表
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 小組表
CREATE TABLE IF NOT EXISTS team_groups (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    group_name CHAR(1) NOT NULL UNIQUE,
    max_teams INT DEFAULT 4 NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 隊伍表
CREATE TABLE IF NOT EXISTS teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    group_id INT NULL,
    team_color VARCHAR(20) DEFAULT '#FFFFFF',
    is_virtual TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES team_groups(group_id) ON DELETE SET NULL
);

-- 運動員表 - 修改為無人機足球隊伍結構：1名進攻手，3-5名防守人員
CREATE TABLE IF NOT EXISTS athletes (
    athlete_id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    jersey_number INT NOT NULL,
    position ENUM('attacker', 'defender', 'substitute') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    UNIQUE KEY (team_id, jersey_number)
);

-- 比賽表 - 增加比賽類型選項
-- 創建比賽表
-- 比賽表
CREATE TABLE IF NOT EXISTS matches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,    -- 添加主鍵
    match_number VARCHAR(50) NOT NULL,
    team1_id INT NULL,                         -- 修改為允許 NULL
    team2_id INT NULL,                         -- 修改為允許 NULL
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    team1_fouls INT DEFAULT 0,
    team2_fouls INT DEFAULT 0,
    match_date DATE NOT NULL,
    match_time INT DEFAULT 10,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    match_status ENUM('pending', 'active', 'overtime', 'completed') DEFAULT 'pending',
    match_type VARCHAR(50) NULL,
    tournament_stage VARCHAR(50) NULL,
    group_id INT NULL,
    winner_id INT NULL,
    overtime_time INT NULL,
    overtime_start_time DATETIME NULL,
    referee_decision TINYINT(1) DEFAULT 0,
    FOREIGN KEY (team1_id) REFERENCES teams(team_id) ON DELETE SET NULL,
    FOREIGN KEY (team2_id) REFERENCES teams(team_id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES team_groups(group_id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES teams(team_id) ON DELETE SET NULL
);

-- 修改 match_events 表的外鍵
CREATE TABLE IF NOT EXISTS match_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    team_id INT NOT NULL,
    athlete_id INT NULL,
    event_type ENUM('goal', 'foul', 'timeout', 'penalty', 'substitution', 'other') NOT NULL,
    event_time TIME NOT NULL,
    period INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (athlete_id) REFERENCES athletes(athlete_id) ON DELETE SET NULL
);

-- 修改 knockout_brackets 表
CREATE TABLE IF NOT EXISTS knockout_brackets (
    bracket_id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NULL,
    match_id INT NULL,
    round_number INT NOT NULL,
    position_in_round INT NOT NULL,
    next_match_id INT NULL,
    is_winner_bracket TINYINT(1) DEFAULT 1,    -- 修改為 TINYINT(1)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id) ON DELETE SET NULL,
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    FOREIGN KEY (next_match_id) REFERENCES matches(match_id) ON DELETE SET NULL
);

-- 新增：比賽類型表
CREATE TABLE IF NOT EXISTS tournaments (
    tournament_id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_name VARCHAR(100) NOT NULL,
    tournament_type ENUM('group', 'knockout', 'mixed') NOT NULL, -- mixed表示"小組循環+淘汰賽"
    start_date DATE,
    end_date DATE,
    status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 新增：小組積分表
CREATE TABLE IF NOT EXISTS group_standings (
    standing_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    team_id INT NOT NULL,
    played INT DEFAULT 0,
    won INT DEFAULT 0,
    drawn INT DEFAULT 0,
    lost INT DEFAULT 0,
    goals_for INT DEFAULT 0,
    goals_against INT DEFAULT 0,
    points INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES team_groups(group_id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    UNIQUE KEY (group_id, team_id)
);

-- 插入默認管理員賬號
INSERT INTO admins (username, password) VALUES ('admin', 'admin123');

-- 初始化小組積分表
INSERT INTO group_standings (group_id, team_id) 
SELECT g.group_id, t.team_id 
FROM team_groups g 
JOIN teams t ON g.group_id = t.group_id;

-- 添加以下代碼來確保用戶權限
CREATE USER IF NOT EXISTS 'dronesoccer'@'%' IDENTIFIED BY 'dronesoccer123';
GRANT ALL PRIVILEGES ON drone_soccer.* TO 'dronesoccer'@'%';
FLUSH PRIVILEGES;


-- 新增：淘汰賽結構表
CREATE TABLE IF NOT EXISTS knockout_brackets (
    bracket_id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NULL,
    match_id INT NULL,
    round_number INT NOT NULL,
    position_in_round INT NOT NULL,
    next_match_id INT NULL,
    is_winner_bracket BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id) ON DELETE SET NULL,
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    FOREIGN KEY (next_match_id) REFERENCES matches(match_id) ON DELETE SET NULL
);