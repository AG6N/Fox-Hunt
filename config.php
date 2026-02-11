<?php
// config.php - Updated with frequency and mode support
session_start();

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'foxhuntv2');
define('DB_USER', 'root');
define('DB_PASSWORD', 'drv134');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// APPLICATION SETTINGS
// ============================================
define('SITE_NAME', 'Foxhunt');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('APP_VERSION', '2.1'); // Updated version
define('DEFAULT_POINTS', 10);
define('DEFAULT_RF_POWER', '5W');
define('DEFAULT_FREQUENCY', '146.520');
define('DEFAULT_MODE', 'FM');
define('DEFAULT_EXPIRY_DAYS', 7);
define('MAX_EXPIRY_DAYS', 30);

// ============================================
// SECURITY SETTINGS
// ============================================
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// ============================================
// FILE PATHS
// ============================================
define('ROOT_PATH', dirname(__FILE__));
define('CSS_PATH', 'styles.css');
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('LOG_PATH', ROOT_PATH . '/logs/');

// ============================================
// ERROR HANDLING
// ============================================
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// ============================================
// DATABASE CONNECTION FUNCTION
// ============================================
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $db = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // Log error
            error_log("Database connection failed: " . $e->getMessage());
            
            // User-friendly error message
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection error. Please try again later.");
            }
        }
    }
    
    return $db;
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check session timeout
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = "Administrator access required";
        header('Location: index.php');
        exit;
    }
}

function loginUser($username, $password) {
    $db = getDB();
    
    try {
        // Check login attempts
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $db->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $attempts = $stmt->fetch();
        
        if ($attempts && $attempts['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $lockout_remaining = time() - strtotime($attempts['last_attempt']);
            if ($lockout_remaining < LOGIN_LOCKOUT_TIME) {
                $minutes = ceil((LOGIN_LOCKOUT_TIME - $lockout_remaining) / 60);
                return ['success' => false, 'message' => "Too many login attempts. Try again in $minutes minutes."];
            }
        }
        
        // Get user
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password_hash'])) {
            // Reset login attempts
            $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->execute([$ip]);
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            return ['success' => true, 'user' => $user];
        } else {
            // Record failed attempt
            if ($attempts) {
                $stmt = $db->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address = ?");
                $stmt->execute([$ip]);
            } else {
                $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (?, 1)");
                $stmt->execute([$ip]);
            }
            
            return ['success' => false, 'message' => "Invalid username or password"];
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => "Login error. Please try again."];
    }
}

function logoutUser() {
    // Clear session data
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

// ============================================
// PASSWORD FUNCTIONS
// ============================================
function hashPassword($password) {
    // Use password_hash for production, MD5 for demo
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        return password_hash($password, PASSWORD_DEFAULT);
    } else {
        return md5($password); // Demo only - easy to test
    }
}

function verifyPassword($password, $hash) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        return password_verify($password, $hash);
    } else {
        return md5($password) === $hash; // Demo only
    }
}

function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return "Password must be at least " . PASSWORD_MIN_LENGTH . " characters";
    }
    return true;
}

// ============================================
// FOX MANAGEMENT FUNCTIONS (UPDATED)
// ============================================
function generateSerialNumber() {
    // Generate 8-digit serial number
    $serial = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    
    // Check for uniqueness (very unlikely but possible)
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM foxes WHERE serial_number = ?");
    $stmt->execute([$serial]);
    
    if ($stmt->fetch()['count'] > 0) {
        // Regenerate if duplicate (extremely rare)
        return generateSerialNumber();
    }
    
    return $serial;
}

function validateGridSquare($grid) {
    $grid = strtoupper(trim($grid));
    
    // Check length
    if (strlen($grid) != 6) {
        return false;
    }
    
    // Accept Maidenhead format: 2 letters, 2 digits, 2 letters
    if (preg_match('/^[A-R]{2}\d{2}[A-X]{2}$/', $grid)) {
        return $grid;
    }
    
    // Accept 6-digit numeric
    if (preg_match('/^\d{6}$/', $grid)) {
        return $grid;
    }
    
    // Accept alphanumeric 6-character format
    if (preg_match('/^[A-Z0-9]{6}$/', $grid)) {
        return $grid;
    }
    
    return false;
}

// NEW: Validate frequency (supports formats like 146.520, 446.000, 1296.0, 50.125)
function validateFrequency($frequency) {
    $frequency = trim($frequency);
    
    // Check length (max 8 characters)
    if (strlen($frequency) > 8 || strlen($frequency) < 3) {
        return false;
    }
    
    // Accept formats like: 146.520, 446.000, 1296.0, 50.125, 7.100, 144.300
    if (preg_match('/^\d{1,4}\.\d{1,3}$/', $frequency)) {
        return $frequency;
    }
    
    // Accept formats without decimal: 146520 (though less common)
    if (preg_match('/^\d{3,8}$/', $frequency)) {
        return $frequency;
    }
    
    return false;
}

// NEW: Validate mode (FM, SSB, CW, AM, etc.)
function validateMode($mode) {
    $mode = strtoupper(trim($mode));
    
    // Check length (max 4 characters)
    if (strlen($mode) > 4 || strlen($mode) < 2) {
        return false;
    }
    
    // Accept common modes: FM, SSB, CW, AM, USB, LSB, RTTY
    if (preg_match('/^[A-Z]{2,4}$/', $mode)) {
        return $mode;
    }
    
    return false;
}

// UPDATED: Validate RF Power (now 5 characters)
function validateRFPower($power) {
    $power = strtoupper(trim($power));
    
    // Check length (now 5 characters max)
    if (strlen($power) < 1 || strlen($power) > 5) {
        return false;
    }
    
    // Accept alphanumeric, W, mW, kW suffixes
    if (preg_match('/^[A-Z0-9]{1,5}$/', $power)) {
        return $power;
    }
    
    return false;
}

function validateNotes($notes) {
    $notes = trim($notes);
    
    // Limit to 25 characters
    if (strlen($notes) > 25) {
        return substr($notes, 0, 25);
    }
    
    return $notes;
}

function calculateExpiration($days, $hours = 0) {
    $expiration = new DateTime();
    
    if ($days > 0) {
        $expiration->add(new DateInterval("P{$days}D"));
    }
    
    if ($hours > 0) {
        $expiration->add(new DateInterval("PT{$hours}H"));
    }
    
    // Cap at maximum expiry days
    $max_expiry = new DateTime();
    $max_expiry->add(new DateInterval("P" . MAX_EXPIRY_DAYS . "D"));
    
    if ($expiration > $max_expiry) {
        return $max_expiry->format('Y-m-d H:i:s');
    }
    
    return $expiration->format('Y-m-d H:i:s');
}

function isFoxExpired($expires_at, $is_found = false) {
    if (!$expires_at || $is_found) {
        return false;
    }
    
    $now = new DateTime();
    $expiry = new DateTime($expires_at);
    
    return $now > $expiry;
}

function cleanupExpiredFoxes() {
    $db = getDB();
    
    try {
        // We don't delete expired foxes, just mark them as inactive
        // by having them excluded from active_foxes view
        return true;
    } catch (PDOException $e) {
        error_log("Cleanup error: " . $e->getMessage());
        return false;
    }
}

function recordFoxFind($fox_id, $user_id, $serial_number, $points) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Check if user already found this fox
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM fox_finds WHERE fox_id = ? AND user_id = ?");
        $stmt->execute([$fox_id, $user_id]);
        
        if ($stmt->fetch()['count'] > 0) {
            $db->rollBack();
            return ['success' => false, 'message' => 'You have already found this fox'];
        }
        
        // Verify serial number matches
        $stmt = $db->prepare("SELECT serial_number FROM foxes WHERE id = ?");
        $stmt->execute([$fox_id]);
        $fox = $stmt->fetch();
        
        if (!$fox || $fox['serial_number'] !== $serial_number) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Invalid serial number'];
        }
        
        // Check if fox is expired
        $stmt = $db->prepare("SELECT expires_at FROM foxes WHERE id = ?");
        $stmt->execute([$fox_id]);
        $fox_expiry = $stmt->fetch();
        
        if ($fox_expiry && $fox_expiry['expires_at'] && new DateTime($fox_expiry['expires_at']) < new DateTime()) {
            $db->rollBack();
            return ['success' => false, 'message' => 'This fox has expired'];
        }
        
        // Record the find
        $stmt = $db->prepare("INSERT INTO fox_finds (fox_id, user_id, serial_number, points_awarded) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fox_id, $user_id, $serial_number, $points]);
        
        // Update fox stats
        $stmt = $db->prepare("UPDATE foxes SET total_finds = total_finds + 1 WHERE id = ?");
        $stmt->execute([$fox_id]);
        
        // Set first_found_at if this is the first find
        $stmt = $db->prepare("SELECT total_finds FROM foxes WHERE id = ?");
        $stmt->execute([$fox_id]);
        $fox_stats = $stmt->fetch();
        
        if ($fox_stats['total_finds'] == 1) {
            $stmt = $db->prepare("UPDATE foxes SET first_found_at = NOW() WHERE id = ?");
            $stmt->execute([$fox_id]);
        }
        
        // Update user stats
        $stmt = $db->prepare("UPDATE users SET 
            total_points = total_points + ?,
            foxes_found = foxes_found + 1,
            last_activity = NOW()
            WHERE id = ?");
        $stmt->execute([$points, $user_id]);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Fox find recorded successfully'];
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Record fox find error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error recording fox find'];
    }
}

function getFoxFinders($fox_id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("SELECT u.username, ff.found_at, ff.points_awarded 
                             FROM fox_finds ff 
                             JOIN users u ON ff.user_id = u.id 
                             WHERE ff.fox_id = ? 
                             ORDER BY ff.found_at");
        $stmt->execute([$fox_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get fox finders error: " . $e->getMessage());
        return [];
    }
}

function hasUserFoundFox($user_id, $fox_id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM fox_finds WHERE user_id = ? AND fox_id = ?");
        $stmt->execute([$user_id, $fox_id]);
        return $stmt->fetch()['count'] > 0;
    } catch (PDOException $e) {
        error_log("Check user found fox error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// USER MANAGEMENT FUNCTIONS
// ============================================
function createUser($username, $email, $password, $is_admin = false) {
    $db = getDB();
    
    try {
        // Validate inputs
        if (strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters'];
        }
        
        if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
        }
        
        $password_validation = validatePassword($password);
        if ($password_validation !== true) {
            return ['success' => false, 'message' => $password_validation];
        }
        
        // Check if username exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()['count'] > 0) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Handle email
        if (empty($email)) {
            // Generate unique email
            $base_email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username)) . '@foxhunt.local';
            $email = $base_email;
            
            $counter = 1;
            while (true) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()['count'] == 0) {
                    break;
                }
                $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username)) . $counter . '@foxhunt.local';
                $counter++;
            }
        } else {
            // Check if email exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()['count'] > 0) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
        }
        
        // Create user
        $password_hash = hashPassword($password);
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password_hash, $is_admin ? 1 : 0]);
        
        $user_id = $db->lastInsertId();
        
        return ['success' => true, 'user_id' => $user_id, 'message' => 'User created successfully'];
        
    } catch (PDOException $e) {
        error_log("Create user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error creating user'];
    }
}

function updateUserPassword($user_id, $new_password) {
    $db = getDB();
    
    try {
        $password_validation = validatePassword($new_password);
        if ($password_validation !== true) {
            return ['success' => false, 'message' => $password_validation];
        }
        
        $password_hash = hashPassword($new_password);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$password_hash, $user_id]);
        
        return ['success' => true, 'message' => 'Password updated successfully'];
        
    } catch (PDOException $e) {
        error_log("Update password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating password'];
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

function formatDate($date_string, $format = 'M d, Y H:i') {
    if (empty($date_string)) {
        return 'Never';
    }
    
    try {
        $date = new DateTime($date_string);
        return $date->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

function getTimeRemaining($expires_at) {
    if (!$expires_at) {
        return 'Never expires';
    }
    
    try {
        $now = new DateTime();
        $expiry = new DateTime($expires_at);
        
        if ($now > $expiry) {
            return 'Expired';
        }
        
        $interval = $now->diff($expiry);
        
        if ($interval->days > 0) {
            return $interval->days . ' day' . ($interval->days != 1 ? 's' : '');
        } elseif ($interval->h > 0) {
            return $interval->h . ' hour' . ($interval->h != 1 ? 's' : '');
        } elseif ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i != 1 ? 's' : '');
        } else {
            return 'Less than a minute';
        }
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function getPointsBadge($points) {
    if ($points >= 100) {
        return '<span class="badge badge-gold">🏅 ' . $points . '</span>';
    } elseif ($points >= 50) {
        return '<span class="badge badge-silver">🥈 ' . $points . '</span>';
    } elseif ($points >= 25) {
        return '<span class="badge badge-bronze">🥉 ' . $points . '</span>';
    } else {
        return '<span class="badge">' . $points . '</span>';
    }
}

// ============================================
// MESSAGE HANDLING FUNCTIONS
// ============================================
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages() {
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    return [];
}

function displayFlashMessages() {
    $messages = getFlashMessages();
    if (!empty($messages)) {
        $output = '';
        foreach ($messages as $msg) {
            $output .= '<div class="alert alert-' . $msg['type'] . '">' . htmlspecialchars($msg['message']) . '</div>';
        }
        return $output;
    }
    return '';
}

// ============================================
// ENVIRONMENT CHECK
// ============================================
function checkEnvironment() {
    $errors = [];
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4.0') < 0) {
        $errors[] = "PHP 7.4 or higher is required";
    }
    
    // Check required extensions
    $required_extensions = ['pdo', 'pdo_mysql', 'session', 'mbstring'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "PHP extension '$ext' is required";
        }
    }
    
    // Check write permissions
    $writable_dirs = [LOG_PATH, UPLOAD_PATH];
    foreach ($writable_dirs as $dir) {
        if (file_exists($dir) && !is_writable($dir)) {
            $errors[] = "Directory '$dir' must be writable";
        }
    }
    
    return $errors;
}

// ============================================
// INITIALIZATION
// ============================================
// Check environment (only in development)
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development'); // Change to 'production' for live site
}

// Check for environment errors
if (ENVIRONMENT === 'development') {
    $env_errors = checkEnvironment();
    if (!empty($env_errors)) {
        die("Environment errors:<br>" . implode("<br>", $env_errors));
    }
}

// Auto-load missing tables (for development)
if (ENVIRONMENT === 'development') {
    try {
        $db = getDB();
        
        // Check if login_attempts table exists, create if not
        $stmt = $db->query("SHOW TABLES LIKE 'login_attempts'");
        if ($stmt->rowCount() == 0) {
            $db->exec("CREATE TABLE login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempts INT DEFAULT 1,
                last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip (ip_address)
            )");
        }
        
    } catch (PDOException $e) {
        // Silently fail - table creation is optional
    }
}
