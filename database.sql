-- database.sql - Updated with frequency and mode fields
CREATE DATABASE IF NOT EXISTS foxhuntv2;
USE foxhuntv2;

-- Users table with admin support
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) NULL, -- Changed to allow NULL
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    total_points INT DEFAULT 0,
    foxes_hidden INT DEFAULT 0,
    foxes_found INT DEFAULT 0,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_points (total_points DESC)
);

-- Foxes table (remains active even when found) - UPDATED with frequency and mode
CREATE TABLE foxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(8) UNIQUE NOT NULL,
    grid_square VARCHAR(6) NOT NULL,
    frequency VARCHAR(8) DEFAULT '146.520', -- NEW FIELD: 8 characters for frequency
    mode VARCHAR(4) DEFAULT 'FM', -- NEW FIELD: 4 characters for mode (FM, SSB, CW, etc.)
    rf_power VARCHAR(5) DEFAULT '5W', -- UPDATED: Changed from 4 to 5 characters
    notes VARCHAR(25),
    hidden_by INT,
    hidden_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    points INT DEFAULT 10,
    total_finds INT DEFAULT 0,
    first_found_at DATETIME,
    FOREIGN KEY (hidden_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_grid_square (grid_square),
    INDEX idx_frequency (frequency), -- NEW INDEX
    INDEX idx_hidden_by (hidden_by),
    INDEX idx_expires (expires_at),
    INDEX idx_hidden_at (hidden_at DESC),
    INDEX idx_total_finds (total_finds DESC)
);

-- Track all fox finds (multiple users can find same fox)
CREATE TABLE fox_finds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fox_id INT NOT NULL,
    user_id INT NOT NULL,
    found_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    serial_number VARCHAR(8) NOT NULL,
    points_awarded INT DEFAULT 0,
    FOREIGN KEY (fox_id) REFERENCES foxes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fox_user (fox_id, user_id),
    INDEX idx_fox_id (fox_id),
    INDEX idx_user_id (user_id),
    INDEX idx_found_at (found_at DESC)
);

-- Leaderboard view
CREATE VIEW leaderboard_view AS
SELECT 
    u.id,
    u.username,
    u.total_points,
    u.foxes_hidden,
    u.foxes_found,
    u.last_activity,
    RANK() OVER (ORDER BY u.total_points DESC) as rank_position
FROM users u
WHERE u.total_points > 0
ORDER BY u.total_points DESC;

-- Insert admin user (password: admin123)
INSERT INTO users (username, email, password_hash, is_admin, total_points, foxes_hidden, foxes_found) 
VALUES ('admin', 'admin@foxhunt.com', '21232f297a57a5a743894a0e4a801fc3', TRUE, 0, 0, 0);

-- Insert sample users (passwords are same as username)
INSERT INTO users (username, email, password_hash, total_points, foxes_hidden, foxes_found) VALUES
('FoxHunter1', 'hunter1@foxhunt.local', MD5('FoxHunter1'), 45, 3, 2),
('RadioExpert', 'expert@foxhunt.local', MD5('RadioExpert'), 85, 5, 3),
('MorseMaster', 'master@foxhunt.local', MD5('MorseMaster'), 120, 8, 4),
('GridSeeker', 'seeker@foxhunt.local', MD5('GridSeeker'), 30, 2, 1),
('SignalChaser', 'chaser@foxhunt.local', MD5('SignalChaser'), 65, 4, 2),
('QRPOperator', 'qrp@foxhunt.local', MD5('QRPOperator'), 25, 1, 1),
('VHFHunter', 'vhf@foxhunt.local', MD5('VHFHunter'), 50, 2, 2),
('UHFExpert', 'uhf@foxhunt.local', MD5('UHFExpert'), 75, 3, 3),
('AntennaGuru', 'antenna@foxhunt.local', MD5('AntennaGuru'), 95, 6, 2),
('NewHunter', 'new@foxhunt.local', MD5('NewHunter'), 0, 0, 0);

-- Insert sample foxes WITH NEW FIELDS
INSERT INTO foxes (serial_number, grid_square, frequency, mode, rf_power, notes, hidden_by, expires_at, points) VALUES
-- Active foxes (not expired)
('12345678', 'FN31pr', '146.520', 'FM', '10W', 'Near big oak tree', 2, DATE_ADD(NOW(), INTERVAL 7 DAY), 15),
('23456789', 'JN76bh', '446.000', 'FM', '5W', 'Under park bench', 3, DATE_ADD(NOW(), INTERVAL 5 DAY), 10),
('34567890', 'KM92df', '144.300', 'SSB', '20W', 'By river bank', 1, DATE_ADD(NOW(), INTERVAL 3 DAY), 20),
('45678901', 'DM43ac', '7.100', 'CW', '1W', 'QRP - Good luck!', 4, DATE_ADD(NOW(), INTERVAL 10 DAY), 25),
('56789012', 'EL88gt', '446.500', 'FM', '15W', 'On hiking trail', 5, DATE_ADD(NOW(), INTERVAL 14 DAY), 18),
('67890123', 'AB12CD', '1296.0', 'FM', '50W', 'High power transmitter', 6, DATE_ADD(NOW(), INTERVAL 21 DAY), 30),
('78901234', 'XY34ZW', '145.500', 'FM', '5W', 'Near picnic area', 7, NULL, 12), -- Never expires
('89012345', 'FN42ab', '146.580', 'FM', '10W', 'Forest edge', 8, DATE_ADD(NOW(), INTERVAL 2 DAY), 15), -- Expiring soon
('90123456', 'JN85cd', '50.125', 'SSB', '100W', 'Maximum power', 9, DATE_ADD(NOW(), INTERVAL 30 DAY), 40),
('01234567', 'KM31ef', '144.200', 'CW', '5W', 'QRP challenge', 10, DATE_ADD(NOW(), INTERVAL 7 DAY), 28),

-- Expired foxes (for testing)
('11223344', 'DM12gh', '146.520', 'FM', '5W', 'Old location', 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 10),
('22334455', 'EL23ij', '446.000', 'FM', '10W', 'Expired test', 3, DATE_SUB(NOW(), INTERVAL 2 DAY), 15);

-- Insert sample fox finds (multiple users can find same fox)
INSERT INTO fox_finds (fox_id, user_id, serial_number, points_awarded, found_at) VALUES
-- Fox #1 found by multiple users
(1, 2, '12345678', 15, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 3, '12345678', 15, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 4, '12345678', 15, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 5, '12345678', 15, DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Fox #2 found by some users
(2, 1, '23456789', 10, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 3, '23456789', 10, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 6, '23456789', 10, DATE_SUB(NOW(), INTERVAL 4 DAY)),

-- Fox #3 found once
(3, 2, '34567890', 20, DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- Fox #4 found by many users (popular fox)
(4, 1, '45678901', 25, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, 2, '45678901', 25, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(4, 3, '45678901', 25, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 4, '45678901', 25, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 5, '45678901', 25, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 6, '45678901', 25, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 7, '45678901', 25, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Fox #11 (expired) was found before expiration
(11, 1, '11223344', 10, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(11, 3, '11223344', 10, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Update foxes total_finds and first_found_at based on fox_finds
UPDATE foxes f
SET 
    total_finds = (
        SELECT COUNT(*) 
        FROM fox_finds ff 
        WHERE ff.fox_id = f.id
    ),
    first_found_at = (
        SELECT MIN(found_at) 
        FROM fox_finds ff 
        WHERE ff.fox_id = f.id
    )
WHERE EXISTS (
    SELECT 1 
    FROM fox_finds ff 
    WHERE ff.fox_id = f.id
);

-- Update user statistics based on fox_finds
UPDATE users u
SET 
    foxes_found = (
        SELECT COUNT(*) 
        FROM fox_finds ff 
        WHERE ff.user_id = u.id
    ),
    total_points = (
        SELECT COALESCE(SUM(points_awarded), 0) 
        FROM fox_finds ff 
        WHERE ff.user_id = u.id
    )
WHERE EXISTS (
    SELECT 1 
    FROM fox_finds ff 
    WHERE ff.user_id = u.id
);

-- Update user foxes_hidden count
UPDATE users u
SET 
    foxes_hidden = (
        SELECT COUNT(*) 
        FROM foxes f 
        WHERE f.hidden_by = u.id
    )
WHERE EXISTS (
    SELECT 1 
    FROM foxes f 
    WHERE f.hidden_by = u.id
);

-- Create stored procedure for hiding a fox (optional) - UPDATED
DELIMITER //
CREATE PROCEDURE HideFox(
    IN p_serial_number VARCHAR(8),
    IN p_grid_square VARCHAR(6),
    IN p_frequency VARCHAR(8),
    IN p_mode VARCHAR(4),
    IN p_rf_power VARCHAR(5),
    IN p_notes VARCHAR(25),
    IN p_hidden_by INT,
    IN p_expires_at DATETIME,
    IN p_points INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insert the fox
    INSERT INTO foxes (serial_number, grid_square, frequency, mode, rf_power, notes, hidden_by, expires_at, points)
    VALUES (p_serial_number, p_grid_square, p_frequency, p_mode, p_rf_power, p_notes, p_hidden_by, p_expires_at, p_points);
    
    -- Update user's hidden fox count
    UPDATE users SET foxes_hidden = foxes_hidden + 1 WHERE id = p_hidden_by;
    
    COMMIT;
END//
DELIMITER ;

-- Create stored procedure for finding a fox
DELIMITER //
CREATE PROCEDURE FindFox(
    IN p_fox_id INT,
    IN p_user_id INT,
    IN p_serial_number VARCHAR(8)
)
BEGIN
    DECLARE v_points INT;
    DECLARE v_already_found INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Check if user already found this fox
    SELECT COUNT(*) INTO v_already_found 
    FROM fox_finds 
    WHERE fox_id = p_fox_id AND user_id = p_user_id;
    
    IF v_already_found > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User already found this fox';
    END IF;
    
    -- Get fox points
    SELECT points INTO v_points FROM foxes WHERE id = p_fox_id;
    
    IF v_points IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fox not found';
    END IF;
    
    -- Verify serial number
    IF NOT EXISTS (SELECT 1 FROM foxes WHERE id = p_fox_id AND serial_number = p_serial_number) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid serial number';
    END IF;
    
    -- Check if fox is expired
    IF EXISTS (SELECT 1 FROM foxes WHERE id = p_fox_id AND expires_at < NOW()) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fox has expired';
    END IF;
    
    -- Record the find
    INSERT INTO fox_finds (fox_id, user_id, serial_number, points_awarded)
    VALUES (p_fox_id, p_user_id, p_serial_number, v_points);
    
    -- Update fox stats
    UPDATE foxes 
    SET 
        total_finds = total_finds + 1,
        first_found_at = COALESCE(first_found_at, NOW())
    WHERE id = p_fox_id;
    
    -- Update user stats
    UPDATE users 
    SET 
        total_points = total_points + v_points,
        foxes_found = foxes_found + 1,
        last_activity = NOW()
    WHERE id = p_user_id;
    
    COMMIT;
END//
DELIMITER ;

-- Create view for user activity
CREATE VIEW user_activity AS
SELECT 
    u.id,
    u.username,
    u.total_points,
    u.foxes_hidden,
    u.foxes_found,
    u.last_activity,
    u.created_at,
    COALESCE((
        SELECT COUNT(DISTINCT f.id)
        FROM foxes f
        WHERE f.hidden_by = u.id
        AND (f.expires_at IS NULL OR f.expires_at > NOW())
        AND f.total_finds = 0
    ), 0) as active_hidden_foxes,
    COALESCE((
        SELECT COUNT(*)
        FROM fox_finds ff
        WHERE ff.user_id = u.id
        AND ff.found_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ), 0) as finds_last_7_days,
    COALESCE((
        SELECT COUNT(*)
        FROM foxes f
        WHERE f.hidden_by = u.id
        AND f.hidden_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ), 0) as hidden_last_7_days
FROM users u;

-- Create view for active foxes - UPDATED
CREATE VIEW active_foxes AS
SELECT 
    f.*,
    u.username as hidden_by_username,
    DATEDIFF(COALESCE(f.expires_at, DATE_ADD(NOW(), INTERVAL 100 YEAR)), NOW()) as days_remaining,
    CASE 
        WHEN f.expires_at IS NULL THEN 'Never'
        WHEN f.expires_at > DATE_ADD(NOW(), INTERVAL 2 DAY) THEN 'Active'
        WHEN f.expires_at > NOW() THEN 'Expiring Soon'
        ELSE 'Expired'
    END as status_display
FROM foxes f
LEFT JOIN users u ON f.hidden_by = u.id
WHERE f.expires_at IS NULL OR f.expires_at > NOW()
ORDER BY f.hidden_at DESC;

-- Create view for popular foxes (most found) - UPDATED
CREATE VIEW popular_foxes AS
SELECT 
    f.*,
    u.username as hidden_by_username,
    f.total_finds,
    f.first_found_at,
    DATEDIFF(NOW(), COALESCE(f.first_found_at, NOW())) as days_since_first_find
FROM foxes f
LEFT JOIN users u ON f.hidden_by = u.id
WHERE f.total_finds > 0
ORDER BY f.total_finds DESC, f.first_found_at;

-- Create event to clean up expired foxes (optional - runs daily)
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_expired_foxes
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Note: We don't delete expired foxes, we just mark them as no longer active
    -- by having them excluded from the active_foxes view
    -- Expired foxes remain in the database for historical purposes
    
    -- Optional: Send notification about expiring foxes
    -- This is a placeholder for future notification system
END//
DELIMITER ;

-- Enable event scheduler if not already enabled
SET GLOBAL event_scheduler = ON;

-- Create trigger to update last_activity when user finds a fox
DELIMITER //
CREATE TRIGGER update_user_activity_on_find
AFTER INSERT ON fox_finds
FOR EACH ROW
BEGIN
    UPDATE users 
    SET last_activity = NOW() 
    WHERE id = NEW.user_id;
END//
DELIMITER ;

-- Create trigger to update user stats when hiding a fox
DELIMITER //
CREATE TRIGGER update_user_stats_on_hide
AFTER INSERT ON foxes
FOR EACH ROW
BEGIN
    UPDATE users 
    SET foxes_hidden = foxes_hidden + 1,
        last_activity = NOW()
    WHERE id = NEW.hidden_by;
END//
DELIMITER ;

-- Display database summary
SELECT 'Database created successfully!' as message;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_foxes FROM foxes;
SELECT COUNT(*) as total_finds FROM fox_finds;
SELECT 'Setup complete. Admin login: admin / admin123' as login_info;
