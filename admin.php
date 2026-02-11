<?php
// admin.php - Updated with frequency and mode fields
require_once 'config.php';
requireAdmin();

$db = getDB();
$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'delete_user':
                $user_id = $_POST['user_id'];
                if ($user_id != $_SESSION['user_id']) {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Delete user's fox finds
                    $stmt = $db->prepare("DELETE FROM fox_finds WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Update foxes where user was the hider (set to NULL)
                    $stmt = $db->prepare("UPDATE foxes SET hidden_by = NULL WHERE hidden_by = ?");
                    $stmt->execute([$user_id]);
                    
                    // Delete the user
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $db->commit();
                    $success = "User deleted successfully";
                } else {
                    $error = "Cannot delete your own account";
                }
                break;
                
            case 'add_user':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                
                // Validate inputs
                if (empty($username) || strlen($username) < 3) {
                    $error = "Username must be at least 3 characters";
                    break;
                }
                
                if (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters";
                    break;
                }
                
                // Generate unique email if empty
                if (empty($email)) {
                    $base_email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username)) . '@foxhunt.local';
                    $email = $base_email;
                    
                    // Check if base email exists and make unique if needed
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
                    // Check if provided email already exists
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()['count'] > 0) {
                    $error = "Email already exists. Please use a different email or leave blank to auto-generate.";
                    break;
                }
            }
            
            // Check if username already exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                $error = "Username already exists";
                break;
            }
            
            $password_hash = hashPassword($password);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash, $is_admin]);
            
            $success = "User '{$username}' added successfully";
            break;
            
        case 'delete_fox':
            $fox_id = $_POST['fox_id'];
            
            // Begin transaction
            $db->beginTransaction();
            
            // Delete all finds of this fox
            $stmt = $db->prepare("DELETE FROM fox_finds WHERE fox_id = ?");
            $stmt->execute([$fox_id]);
            
            // Delete the fox
            $stmt = $db->prepare("DELETE FROM foxes WHERE id = ?");
            $stmt->execute([$fox_id]);
            
            $db->commit();
            $success = "Fox #{$fox_id} deleted successfully";
            break;
            
        case 'reset_password':
            $user_id = $_POST['user_id'];
            $new_password = $_POST['new_password'];
            
            if (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters";
                break;
            }
            
            $password_hash = hashPassword($new_password);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            $success = "Password reset successfully";
            break;
            
        case 'toggle_admin':
            $user_id = $_POST['user_id'];
            
            // Don't allow removing admin from yourself
            if ($user_id == $_SESSION['user_id']) {
                $error = "Cannot change your own admin status";
                break;
            }
            
            $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $new_status = $user['is_admin'] ? 0 : 1;
            $stmt = $db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id]);
            
            $status_text = $new_status ? 'granted' : 'revoked';
            $success = "Admin privileges {$status_text}";
            break;
    }
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $error = "Error: " . $e->getMessage();
}
}

// Get all data
try {
// All users
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// All foxes with additional info
$foxes = $db->query("SELECT f.*, 
    uh.username as hidden_by_username,
    COUNT(ff.id) as total_finds,
    GROUP_CONCAT(DISTINCT uf.username ORDER BY ff.found_at SEPARATOR ', ') as finder_usernames
    FROM foxes f
    LEFT JOIN users uh ON f.hidden_by = uh.id
    LEFT JOIN fox_finds ff ON f.id = ff.fox_id
    LEFT JOIN users uf ON ff.user_id = uf.id
    GROUP BY f.id
    ORDER BY f.hidden_at DESC")->fetchAll();

// Statistics
$stats = $db->query("SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM foxes) as total_foxes,
    (SELECT COUNT(*) FROM fox_finds) as total_finds,
    (SELECT SUM(total_points) FROM users) as total_points_awarded,
    (SELECT username FROM users ORDER BY total_points DESC LIMIT 1) as top_hunter,
    (SELECT total_points FROM users ORDER BY total_points DESC LIMIT 1) as top_score,
    (SELECT COUNT(*) FROM users WHERE is_admin = 1) as admin_count
    FROM dual")->fetch();
    
} catch (PDOException $e) {
$error = "Error loading data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-tabs {
            margin: 30px 0;
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 2px solid #ddd;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .tab-link {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
            color: #7f8c8d;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        
        .tab-link:hover {
            color: #3498db;
        }
        
        .tab-link.active {
            color: #3498db;
            border-bottom-color: #3498db;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .admin-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .admin-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .admin-table tr:hover {
            background: #f8f9fa;
        }
        
        .admin-table .actions {
            white-space: nowrap;
        }
        
        .admin-form {
            max-width: 600px;
            margin: 0 auto;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
        }
        
        .admin-form .form-group {
            margin-bottom: 20px;
        }
        
        .badge-admin {
            background: #ff9800;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .badge-you {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }
        
        .modal-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #f1f1f1;
            text-align: right;
        }
        
        .close-modal {
            float: right;
            font-size: 1.5em;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .stats-card h2 {
            color: white;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat {
            text-align: center;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .stat-value {
            display: block;
            font-size: 2em;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9em;
        }
        
        .frequency-cell {
            font-family: monospace;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .mode-cell {
            font-weight: bold;
            color: #27ae60;
        }
        
        @media (max-width: 768px) {
            .tab-nav {
                flex-direction: column;
            }
            
            .tab-link {
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            
            .admin-table {
                font-size: 0.9em;
                display: block;
                overflow-x: auto;
            }
            
            .admin-table th,
            .admin-table td {
                padding: 8px 10px;
            }
            
            .modal-content {
                margin: 5% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>⚙️ Admin Panel</h1>
            <div class="header-actions">
                <span class="user-info">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="index.php" class="btn btn-secondary">← Home</a>
                <a href="my_foxes.php" class="btn btn-info">My Foxes</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="stats-card">
            <h2>📊 System Statistics</h2>
            <div class="stats-grid">
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['total_foxes'] ?? 0; ?></span>
                    <span class="stat-label">Total Foxes</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['total_finds'] ?? 0; ?></span>
                    <span class="stat-label">Total Finds</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['total_points_awarded'] ?? 0; ?></span>
                    <span class="stat-label">Points Awarded</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['admin_count'] ?? 0; ?></span>
                    <span class="stat-label">Admins</span>
                </div>
            </div>
            
            <?php if ($stats['top_hunter']): ?>
                <div style="text-align: center; margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                    <h4 style="color: white; margin-bottom: 10px;">👑 Top Hunter</h4>
                    <p style="font-size: 1.2em;">
                        <strong><?php echo htmlspecialchars($stats['top_hunter']); ?></strong> 
                        with <?php echo $stats['top_score']; ?> points
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-tabs">
            <div class="tab-nav">
                <button class="tab-link active" onclick="openTab(event, 'users')">👥 Users</button>
                <button class="tab-link" onclick="openTab(event, 'foxes')">🦊 Foxes</button>
                <button class="tab-link" onclick="openTab(event, 'add-user')">➕ Add User</button>
            </div>

            <div id="users" class="tab-content active">
                <h3>Manage Users</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Points</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge-you">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge-admin">Admin</span>
                                    <?php else: ?>
                                        User
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['total_points']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td class="actions">
                                    <button class="btn btn-info btn-small" onclick="openResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        Reset Password
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-small">
                                                <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small" 
                                                    onclick="return confirm('Delete user <?php echo htmlspecialchars($user['username']); ?>? This will also delete their fox finds and set their hidden foxes to NULL.')">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="foxes" class="tab-content">
                <h3>Manage Foxes</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Grid</th>
                            <th>Freq</th>
                            <th>Mode</th>
                            <th>Power</th>
                            <th>Serial</th>
                            <th>Hidden By</th>
                            <th>Finds</th>
                            <th>Finders</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($foxes as $fox): 
                            $is_expired = isFoxExpired($fox['expires_at'], false);
                        ?>
                            <tr>
                                <td>#<?php echo $fox['id']; ?></td>
                                <td class="grid-cell"><?php echo $fox['grid_square']; ?></td>
                                <td class="frequency-cell"><?php echo $fox['frequency']; ?></td>
                                <td class="mode-cell"><?php echo $fox['mode']; ?></td>
                                <td><?php echo htmlspecialchars($fox['rf_power']); ?></td>
                                <td><?php echo $fox['serial_number']; ?></td>
                                <td>
                                    <?php if ($fox['hidden_by_username']): ?>
                                        <?php echo htmlspecialchars($fox['hidden_by_username']); ?>
                                    <?php else: ?>
                                        <em>Unknown</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $fox['total_finds']; ?></td>
                                <td>
                                    <?php if ($fox['finder_usernames']): ?>
                                        <?php 
                                        $finders = explode(', ', $fox['finder_usernames']);
                                        echo count($finders) > 3 
                                            ? implode(', ', array_slice($finders, 0, 3)) . '...' 
                                            : $fox['finder_usernames']; 
                                        ?>
                                    <?php else: ?>
                                        None
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fox['total_finds'] > 0): ?>
                                        <span class="status found">Found (<?php echo $fox['total_finds']; ?>)</span>
                                    <?php elseif ($is_expired): ?>
                                        <span class="status expired">Expired</span>
                                    <?php else: ?>
                                        <span class="status active">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_fox">
                                        <input type="hidden" name="fox_id" value="<?php echo $fox['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small" 
                                                onclick="return confirm('Delete fox #<?php echo $fox['id']; ?>? This will also delete all finds of this fox.')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="add-user" class="tab-content">
                <h3>Add New User</h3>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="form-group">
                        <label for="username">Username: *</label>
                        <input type="text" id="username" name="username" 
                               required minlength="3" maxlength="20"
                               pattern="[A-Za-z0-9_]+"
                               title="Letters, numbers, and underscores only"
                               placeholder="Enter username">
                        <small>3-20 characters, letters, numbers, underscores only</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" 
                               placeholder="user@example.com (optional)">
                        <small>Optional. If left blank, a unique email will be auto-generated.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password: *</label>
                        <input type="password" id="password" name="password" 
                               required minlength="6"
                               placeholder="Enter password">
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_admin" value="1">
                            Make this user an administrator
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add User</button>
                        <button type="reset" class="btn btn-secondary">Reset Form</button>
                    </div>
                </form>
                
                <div class="info-box">
                    <h4>📝 User Creation Notes</h4>
                    <ul>
                        <li>Username must be unique</li>
                        <li>Email is optional but must be unique if provided</li>
                        <li>Admin users have full system access</li>
                        <li>New users can log in immediately</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <span class="close-modal" onclick="closeModal('resetPasswordModal')">&times;</span>
            </div>
            <form id="resetPasswordForm" method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" id="modal_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" 
                           required minlength="6"
                           placeholder="Enter new password">
                    <small>Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password:</label>
                    <input type="password" id="confirm_new_password" 
                           required minlength="6"
                           placeholder="Confirm new password">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Tab switching
    function openTab(evt, tabName) {
        const tabContents = document.getElementsByClassName("tab-content");
        const tabLinks = document.getElementsByClassName("tab-link");
        
        for (let tabContent of tabContents) {
            tabContent.classList.remove("active");
        }
        
        for (let tabLink of tabLinks) {
            tabLink.classList.remove("active");
        }
        
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
    
    // Modal functions
    function openResetPasswordModal(userId, username) {
        document.getElementById('modal_user_id').value = userId;
        document.getElementById('resetPasswordModal').style.display = 'block';
        document.querySelector('#resetPasswordModal .modal-header h3').textContent = 
            `Reset Password for ${username}`;
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.getElementsByClassName('modal');
        for (let modal of modals) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }
    
    // Password confirmation validation
    document.getElementById('resetPasswordForm').onsubmit = function(e) {
        const password = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_new_password').value;
        
        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            e.preventDefault();
            return false;
        }
        
        if (password.length < 6) {
            alert('Password must be at least 6 characters!');
            e.preventDefault();
            return false;
        }
        
        return true;
    };
    
    // Form validation for add user
    document.addEventListener('DOMContentLoaded', function() {
        const addUserForm = document.querySelector('form[action*="add-user"]');
        if (addUserForm) {
            addUserForm.onsubmit = function(e) {
                const password = document.getElementById('password')?.value;
                if (password && password.length < 6) {
                    alert('Password must be at least 6 characters!');
                    e.preventDefault();
                    return false;
                }
                return true;
            };
        }
    });
    </script>
</body>
</html>
