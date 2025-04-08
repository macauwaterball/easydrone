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
CREATE TABLE IF NOT EXISTS matches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    match_number VARCHAR(20) NOT NULL,
    group_id INT NULL,
    team1_id INT NOT NULL,
    team2_id INT NOT NULL,
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    match_type ENUM('group', 'knockout', 'final') NOT NULL DEFAULT 'group',
    tournament_stage VARCHAR(50) NULL, -- 例如：'group_stage', 'round_of_16', 'quarter_final', 'semi_final', 'final'
    match_status ENUM('pending', 'active', 'completed', 'overtime') DEFAULT 'pending',
    winner_team_id INT NULL,
    match_date DATETIME,
    match_duration INT DEFAULT 40, -- 比賽時長（分鐘）
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES team_groups(group_id) ON DELETE SET NULL,
    FOREIGN KEY (team1_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (team2_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (winner_team_id) REFERENCES teams(team_id) ON DELETE SET NULL
);

-- 比賽事件表（進球、犯規等）
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

-- 插入示例小組數據 - 每組4隊，適合小組循環賽
INSERT INTO team_groups (group_name, max_teams) VALUES 
('A', 4), 
('B', 4), 
('C', 4), 
('D', 4);

-- 插入示例隊伍數據
INSERT INTO teams (team_name, group_id) VALUES 
('紅隊', 1),
('藍隊', 1),
('綠隊', 1),
('黃隊', 1),
('橙隊', 2),
('紫隊', 2),
('黑隊', 2),
('白隊', 2),
('銀隊', 3),
('金隊', 3),
('銅隊', 3),
('鐵隊', 3),
('天隊', 4),
('地隊', 4),
('風隊', 4),
('火隊', 4);

-- 為每個隊伍添加示例運動員 (1名進攻手，4名防守人員)
INSERT INTO athletes (team_id, name, jersey_number, position) VALUES
-- 紅隊運動員
(1, '張進攻', 1, 'attacker'),
(1, '李防守', 2, 'defender'),
(1, '王防守', 3, 'defender'),
(1, '趙防守', 4, 'defender'),
(1, '孫防守', 5, 'defender'),
(1, '錢替補', 6, 'substitute'),

-- 藍隊運動員
(2, '劉進攻', 1, 'attacker'),
(2, '陳防守', 2, 'defender'),
(2, '楊防守', 3, 'defender'),
(2, '吳防守', 4, 'defender'),
(2, '朱防守', 5, 'defender'),
(2, '秦替補', 6, 'substitute'),

-- 綠隊運動員
(3, '馬進攻', 1, 'attacker'),
(3, '謝防守', 2, 'defender'),
(3, '高防守', 3, 'defender'),
(3, '林防守', 4, 'defender'),
(3, '鄭防守', 5, 'defender'),
(3, '何替補', 6, 'substitute'),

-- 黃隊運動員
(4, '羅進攻', 1, 'attacker'),
(4, '周防守', 2, 'defender'),
(4, '吳防守', 3, 'defender'),
(4, '鄧防守', 4, 'defender'),
(4, '馮防守', 5, 'defender'),
(4, '韓替補', 6, 'substitute');

-- 創建示例比賽 (小組循環賽)
INSERT INTO matches (match_number, group_id, team1_id, team2_id, match_type, tournament_stage, match_date) VALUES
-- A組比賽
('A-1', 1, 1, 2, 'group', 'group_stage', NOW() + INTERVAL 1 DAY),
('A-2', 1, 3, 4, 'group', 'group_stage', NOW() + INTERVAL 1 DAY),
('A-3', 1, 1, 3, 'group', 'group_stage', NOW() + INTERVAL 2 DAY),
('A-4', 1, 2, 4, 'group', 'group_stage', NOW() + INTERVAL 2 DAY),
('A-5', 1, 1, 4, 'group', 'group_stage', NOW() + INTERVAL 3 DAY),
('A-6', 1, 2, 3, 'group', 'group_stage', NOW() + INTERVAL 3 DAY),

-- B組比賽
('B-1', 2, 5, 6, 'group', 'group_stage', NOW() + INTERVAL 1 DAY),
('B-2', 2, 7, 8, 'group', 'group_stage', NOW() + INTERVAL 1 DAY),
('B-3', 2, 5, 7, 'group', 'group_stage', NOW() + INTERVAL 2 DAY),
('B-4', 2, 6, 8, 'group', 'group_stage', NOW() + INTERVAL 2 DAY),
('B-5', 2, 5, 8, 'group', 'group_stage', NOW() + INTERVAL 3 DAY),
('B-6', 2, 6, 7, 'group', 'group_stage', NOW() + INTERVAL 3 DAY);

-- 創建示例比賽 (淘汰賽)
INSERT INTO matches (match_number, team1_id, team2_id, match_type, tournament_stage, match_date) VALUES
('QF-1', 1, 6, 'knockout', 'quarter_final', NOW() + INTERVAL 4 DAY),
('QF-2', 2, 5, 'knockout', 'quarter_final', NOW() + INTERVAL 4 DAY),
('QF-3', 3, 8, 'knockout', 'quarter_final', NOW() + INTERVAL 4 DAY),
('QF-4', 4, 7, 'knockout', 'quarter_final', NOW() + INTERVAL 4 DAY),
('SF-1', 1, 2, 'knockout', 'semi_final', NOW() + INTERVAL 5 DAY),
('SF-2', 3, 4, 'knockout', 'semi_final', NOW() + INTERVAL 5 DAY),
('F-1', 1, 3, 'final', 'final', NOW() + INTERVAL 6 DAY);

-- 創建示例比賽類型
INSERT INTO tournaments (tournament_name, tournament_type, start_date, end_date, status) VALUES
('2025年無人機足球小組循環賽', 'group', NOW(), NOW() + INTERVAL 3 DAY, 'active'),
('2025年無人機足球淘汰賽', 'knockout', NOW() + INTERVAL 4 DAY, NOW() + INTERVAL 6 DAY, 'pending'),
('2025年無人機足球綜合賽', 'mixed', NOW(), NOW() + INTERVAL 6 DAY, 'active');

-- 初始化小組積分表
INSERT INTO group_standings (group_id, team_id) 
SELECT g.group_id, t.team_id 
FROM team_groups g 
JOIN teams t ON g.group_id = t.group_id;