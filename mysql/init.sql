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
-- 創建比賽表
CREATE TABLE IF NOT EXISTS matches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    match_number VARCHAR(50) NOT NULL,
    team1_id INT NOT NULL,
    team2_id INT NOT NULL,
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
    group_id INT NULL,
    winner_id INT NULL,
    overtime_time INT NULL,
    overtime_start_time DATETIME NULL,
    referee_decision TINYINT(1) DEFAULT 0,
    FOREIGN KEY (team1_id) REFERENCES teams(team_id),
    FOREIGN KEY (team2_id) REFERENCES teams(team_id),
    FOREIGN KEY (group_id) REFERENCES team_groups(group_id) ON DELETE SET NULL
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

-- 插入示例隊伍數據 (改為英文名稱)
INSERT INTO teams (team_name, group_id) VALUES 
('Red Team', 1),
('Blue Team', 1),
('Green Team', 1),
('Yellow Team', 1),
('Orange Team', 2),
('Purple Team', 2),
('Black Team', 2),
('White Team', 2),
('Silver Team', 3),
('Gold Team', 3),
('Bronze Team', 3),
('Iron Team', 3),
('Sky Team', 4),
('Earth Team', 4),
('Wind Team', 4),
('Fire Team', 4);

-- 為每個隊伍添加示例運動員 (1名進攻手，4名防守人員) (改為英文名稱)
INSERT INTO athletes (team_id, name, jersey_number, position) VALUES
-- Red Team Players
(1, 'John Attacker', 1, 'attacker'),
(1, 'Mike Defender', 2, 'defender'),
(1, 'Tom Defender', 3, 'defender'),
(1, 'Alex Defender', 4, 'defender'),
(1, 'Sam Defender', 5, 'defender'),
(1, 'Kevin Substitute', 6, 'substitute'),

-- Blue Team Players
(2, 'James Attacker', 1, 'attacker'),
(2, 'Chris Defender', 2, 'defender'),
(2, 'Ryan Defender', 3, 'defender'),
(2, 'Will Defender', 4, 'defender'),
(2, 'Jack Defender', 5, 'defender'),
(2, 'Peter Substitute', 6, 'substitute'),

-- Green Team Players
(3, 'Mark Attacker', 1, 'attacker'),
(3, 'Steve Defender', 2, 'defender'),
(3, 'Gary Defender', 3, 'defender'),
(3, 'Luke Defender', 4, 'defender'),
(3, 'Eric Defender', 5, 'defender'),
(3, 'Henry Substitute', 6, 'substitute'),

-- Yellow Team Players
(4, 'Robert Attacker', 1, 'attacker'),
(4, 'Charles Defender', 2, 'defender'),
(4, 'William Defender', 3, 'defender'),
(4, 'David Defender', 4, 'defender'),
(4, 'Frank Defender', 5, 'defender'),
(4, 'Harry Substitute', 6, 'substitute');

-- 創建示例比賽 (小組循環賽)
INSERT INTO matches (match_number, group_id, team1_id, team2_id, match_type, match_date) VALUES
-- A組比賽
('A-1', 1, 1, 2, 'group', NOW() + INTERVAL 1 DAY),
('A-2', 1, 3, 4, 'group', NOW() + INTERVAL 1 DAY),
('A-3', 1, 1, 3, 'group', NOW() + INTERVAL 2 DAY),
('A-4', 1, 2, 4, 'group', NOW() + INTERVAL 2 DAY),
('A-5', 1, 1, 4, 'group', NOW() + INTERVAL 3 DAY),
('A-6', 1, 2, 3, 'group', NOW() + INTERVAL 3 DAY),

-- B組比賽
('B-1', 2, 5, 6, 'group', NOW() + INTERVAL 1 DAY),
('B-2', 2, 7, 8, 'group', NOW() + INTERVAL 1 DAY),
('B-3', 2, 5, 7, 'group', NOW() + INTERVAL 2 DAY),
('B-4', 2, 6, 8, 'group', NOW() + INTERVAL 2 DAY),
('B-5', 2, 5, 8, 'group', NOW() + INTERVAL 3 DAY),
('B-6', 2, 6, 7, 'group', NOW() + INTERVAL 3 DAY);

-- 創建示例比賽 (淘汰賽)
INSERT INTO matches (match_number, team1_id, team2_id, match_type, match_date) VALUES
('QF-1', 1, 6, 'knockout', NOW() + INTERVAL 4 DAY),
('QF-2', 2, 5, 'knockout', NOW() + INTERVAL 4 DAY),
('QF-3', 3, 8, 'knockout', NOW() + INTERVAL 4 DAY),
('QF-4', 4, 7, 'knockout', NOW() + INTERVAL 4 DAY),
('SF-1', 1, 2, 'knockout', NOW() + INTERVAL 5 DAY),
('SF-2', 3, 4, 'knockout', NOW() + INTERVAL 5 DAY),
('F-1', 1, 3, 'final', NOW() + INTERVAL 6 DAY);

-- 創建示例比賽類型
INSERT INTO tournaments (tournament_name, tournament_type, start_date, end_date, status) VALUES
('2025 Drone Soccer Group Tournament', 'group', NOW(), NOW() + INTERVAL 3 DAY, 'active'),
('2025 Drone Soccer Knockout Tournament', 'knockout', NOW() + INTERVAL 4 DAY, NOW() + INTERVAL 6 DAY, 'pending'),
('2025 Drone Soccer Mixed Tournament', 'mixed', NOW(), NOW() + INTERVAL 6 DAY, 'active');

-- 初始化小組積分表
INSERT INTO group_standings (group_id, team_id) 
SELECT g.group_id, t.team_id 
FROM team_groups g 
JOIN teams t ON g.group_id = t.group_id;

-- 添加以下代碼來確保用戶權限
CREATE USER IF NOT EXISTS 'dronesoccer'@'%' IDENTIFIED BY 'dronesoccer123';
GRANT ALL PRIVILEGES ON drone_soccer.* TO 'dronesoccer'@'%';
FLUSH PRIVILEGES;