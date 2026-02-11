<?php
// my_foxes.php - Updated with frequency and mode fields
require_once 'config.php';
requireLogin();

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle fox deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $fox_id = $_GET['delete'];
    
    try {
        // Check if user owns this fox or is admin
        $stmt = $db->prepare("SELECT hidden_by FROM foxes WHERE id = ?");
        $stmt->execute([$fox_id]);
        $fox = $stmt->fetch();
        
        if ($fox && ($fox['hidden_by'] == $user_id || isAdmin())) {
            // Begin transaction
            $db->beginTransaction();
            
            // Delete all finds of this fox
            $stmt = $db->prepare("DELETE FROM fox_finds WHERE fox_id = ?");
            $stmt->execute([$fox_id]);
            
            // Delete the fox
            $stmt = $db->prepare("DELETE FROM foxes WHERE id = ?");
            $stmt->execute([$fox_id]);
            
            // Update user's hidden fox count if they owned it
            if ($fox['hidden_by'] == $user_id) {
                $stmt = $db->prepare("UPDATE users SET foxes_hidden = foxes_hidden - 1 WHERE id = ?");
                $stmt->execute([$user_id]);
            }
            
            $db->commit();
            $success = "Fox #{$fox_id} deleted successfully";
            
            // Refresh page to show updated list
            header('Location: my_foxes.php?success=' . urlencode($success));
            exit;
        } else {
            $error = "You don't have permission to delete this fox";
        }
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error deleting fox: " . $e->getMessage();
    }
}

// Get user's data
try {
    // User stats
    $stmt = $db->prepare("SELECT username, total_points, foxes_hidden, foxes_found, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_stats = $stmt->fetch();
    
    // Foxes hidden by user
    $stmt = $db->prepare("SELECT f.*, 
        (SELECT COUNT(*) FROM fox_finds WHERE fox_id = f.id) as total_finds,
        (SELECT GROUP_CONCAT(DISTINCT u.username ORDER BY ff.found_at SEPARATOR ', ') 
         FROM fox_finds ff 
         JOIN users u ON ff.user_id = u.id 
         WHERE ff.fox_id = f.id 
         LIMIT 5) as finder_names
        FROM foxes f 
        WHERE f.hidden_by = ?
        ORDER BY f.hidden_at DESC");
    $stmt->execute([$user_id]);
    $hidden_foxes = $stmt->fetchAll();
    
    // Foxes found by user with details
    $stmt = $db->prepare("SELECT ff.*, f.*, 
        uh.username as hidden_by_username,
        ff.found_at as user_found_at,
        ff.points_awarded as user_points
        FROM fox_finds ff 
        JOIN foxes f ON ff.fox_id = f.id
        LEFT JOIN users uh ON f.hidden_by = uh.id
        WHERE ff.user_id = ?
        ORDER BY ff.found_at DESC");
    $stmt->execute([$user_id]);
    $found_foxes = $stmt->fetchAll();
    
    // Get user's rank
    $stmt = $db->prepare("SELECT rank_position FROM leaderboard_view WHERE id = ?");
    $stmt->execute([$user_id]);
    $rank_data = $stmt->fetch();
    $user_rank = $rank_data ? $rank_data['rank_position'] : null;
    
} catch (PDOException $e) {
    $error = "Error loading data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Foxes - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .user-profile {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-username {
            font-size: 1.8em;
            font-weight: bold;
        }
        
        .profile-rank {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 1.2em;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .profile-stat {
            text-align: center;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .profile-stat-value {
            display: block;
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .profile-stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .tabs {
            margin: 30px 0;
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
            flex-wrap: wrap;
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
        }
        
        .tab-content.active {
            display: block;
        }
        
        .foxes-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .foxes-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .foxes-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .foxes-table tr:hover {
            background: #f5f5f5;
        }
        
        .serial-cell {
            font-family: monospace;
        }
        
        .serial-reveal-btn {
            background: none;
            border: none;
            color: #3498db;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
            font: inherit;
        }
        
        .serial-reveal-btn:hover {
            color: #2980b9;
        }
        
        .serial-revealed {
            font-family: monospace;
            font-weight: bold;
            color: #2c3e50;
            letter-spacing: 1px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-found {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        
        .finder-list {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .actions-cell {
            white-space: nowrap;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .no-data p {
            margin: 10px 0;
            color: #7f8c8d;
        }
        
        .table-responsive {
            overflow-x: auto;
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
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .tab-nav {
                flex-direction: column;
            }
            
            .tab-link {
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            
            .foxes-table {
                font-size: 0.9em;
            }
            
            .foxes-table th,
            .foxes-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 My Foxes</h1>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">← Home</a>
                <a href="hide_fox.php" class="btn btn-primary">Hide New Fox</a>
                <a href="find_fox.php" class="btn btn-success">Find Foxes</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn btn-warning">Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <div class="user-profile">
            <div class="profile-header">
                <div class="profile-username">
                    <?php echo htmlspecialchars($user_stats['username']); ?>
                </div>
                <?php if ($user_rank): ?>
                    <div class="profile-rank">
                        Rank: #<?php echo $user_rank; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-stats">
                <div class="profile-stat">
                    <span class="profile-stat-value"><?php echo $user_stats['total_points']; ?></span>
                    <span class="profile-stat-label">Total Points</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-value"><?php echo $user_stats['foxes_hidden']; ?></span>
                    <span class="profile-stat-label">Foxes Hidden</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-value"><?php echo $user_stats['foxes_found']; ?></span>
                    <span class="profile-stat-label">Foxes Found</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-value">
                        <?php 
                        $days = floor((time() - strtotime($user_stats['created_at'])) / (60 * 60 * 24));
                        echo $days > 0 ? $days . ' days' : 'Today';
                        ?>
                    </span>
                    <span class="profile-stat-label">Member For</span>
                </div>
            </div>
        </div>

        <div class="tabs">
            <div class="tab-nav">
                <button class="tab-link active" onclick="openTab(event, 'hidden-foxes')">
                    🦊 Hidden Foxes (<?php echo count($hidden_foxes); ?>)
                </button>
                <button class="tab-link" onclick="openTab(event, 'found-foxes')">
                    ✅ Found Foxes (<?php echo count($found_foxes); ?>)
                </button>
            </div>

            <div id="hidden-foxes" class="tab-content active">
                <h2>Foxes I've Hidden</h2>
                <?php if (empty($hidden_foxes)): ?>
                    <div class="no-data">
                        <h3>No Hidden Foxes</h3>
                        <p>You haven't hidden any foxes yet.</p>
                        <a href="hide_fox.php" class="btn btn-primary">Hide Your First Fox</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="foxes-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Grid</th>
                                    <th>Freq</th>
                                    <th>Mode</th>
                                    <th>Power</th>
                                    <th>Notes</th>
                                    <th>Serial</th>
                                    <th>Status</th>
                                    <th>Times Found</th>
                                    <th>Finders</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hidden_foxes as $fox): 
                                    $is_expired = isFoxExpired($fox['expires_at'], false);
                                ?>
                                    <tr>
                                        <td>#<?php echo $fox['id']; ?></td>
                                        <td class="grid-cell"><?php echo $fox['grid_square']; ?></td>
                                        <td class="frequency-cell"><?php echo $fox['frequency']; ?></td>
                                        <td class="mode-cell"><?php echo $fox['mode']; ?></td>
                                        <td><?php echo htmlspecialchars($fox['rf_power']); ?></td>
                                        <td><?php echo htmlspecialchars($fox['notes']); ?></td>
                                        <td class="serial-cell">
                                            <?php if ($fox['total_finds'] > 0): ?>
                                                <span class="serial-revealed"><?php echo $fox['serial_number']; ?></span>
                                            <?php else: ?>
                                                <button type="button" 
                                                        class="serial-reveal-btn" 
                                                        onclick="revealSerial(this, '<?php echo $fox['serial_number']; ?>')">
                                                    Click to reveal
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fox['total_finds'] > 0): ?>
                                                <span class="status-badge status-found">
                                                    Found (<?php echo $fox['total_finds']; ?>)
                                                </span>
                                            <?php elseif ($is_expired): ?>
                                                <span class="status-badge status-expired">Expired</span>
                                            <?php else: ?>
                                                <span class="status-badge status-active">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $fox['total_finds']; ?></td>
                                        <td class="finder-list" title="<?php echo htmlspecialchars($fox['finder_names']); ?>">
                                            <?php echo $fox['finder_names'] ? htmlspecialchars($fox['finder_names']) : 'None'; ?>
                                        </td>
                                        <td>
                                            <?php if ($fox['expires_at'] && !$is_expired): ?>
                                                <?php 
                                                $expires = new DateTime($fox['expires_at']);
                                                $now = new DateTime();
                                                $interval = $now->diff($expires);
                                                
                                                if ($interval->days > 0) {
                                                    echo $interval->days . ' day' . ($interval->days != 1 ? 's' : '');
                                                } elseif ($interval->h > 0) {
                                                    echo $interval->h . ' hour' . ($interval->h != 1 ? 's' : '');
                                                } else {
                                                    echo 'Today';
                                                }
                                                ?>
                                            <?php else: ?>
                                                <?php echo $is_expired ? 'Expired' : 'Never'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions-cell">
                                            <?php if (!$is_expired && $fox['total_finds'] == 0): ?>
                                                <a href="my_foxes.php?delete=<?php echo $fox['id']; ?>" 
                                                   class="btn btn-danger btn-small"
                                                   onclick="return confirm('Are you sure you want to delete fox #<?php echo $fox['id']; ?>? This cannot be undone.')">
                                                    Delete
                                                </a>
                                            <?php else: ?>
                                                <span class="btn btn-secondary btn-small" disabled>Delete</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="found-foxes" class="tab-content">
                <h2>Foxes I've Found</h2>
                <?php if (empty($found_foxes)): ?>
                    <div class="no-data">
                        <h3>No Found Foxes</h3>
                        <p>You haven't found any foxes yet.</p>
                        <a href="find_fox.php" class="btn btn-success">Start Hunting</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="foxes-table">
                            <thead>
                                <tr>
                                    <th>Fox ID</th>
                                    <th>Grid</th>
                                    <th>Freq</th>
                                    <th>Mode</th>
                                    <th>Power</th>
                                    <th>Notes</th>
                                    <th>Serial</th>
                                    <th>Hidden By</th>
                                    <th>Points</th>
                                    <th>Found Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($found_foxes as $find): ?>
                                    <tr>
                                        <td>#<?php echo $find['id']; ?></td>
                                        <td class="grid-cell"><?php echo $find['grid_square']; ?></td>
                                        <td class="frequency-cell"><?php echo $find['frequency']; ?></td>
                                        <td class="mode-cell"><?php echo $find['mode']; ?></td>
                                        <td><?php echo htmlspecialchars($find['rf_power']); ?></td>
                                        <td><?php echo htmlspecialchars($find['notes']); ?></td>
                                        <td class="serial-revealed"><?php echo $find['serial_number']; ?></td>
                                        <td><?php echo htmlspecialchars($find['hidden_by_username']); ?></td>
                                        <td class="points-cell">+<?php echo $find['user_points']; ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($find['user_found_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="info-box">
            <h3>📋 Understanding Your Dashboard</h3>
            <div class="details-grid">
                <div class="detail">
                    <span class="detail-label">Frequency:</span>
                    <span class="detail-value">Operating frequency in MHz</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Mode:</span>
                    <span class="detail-value">Transmission mode (FM, SSB, CW, etc.)</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Hidden Foxes:</span>
                    <span class="detail-value">Foxes you've placed for others to find</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Found Foxes:</span>
                    <span class="detail-value">Foxes you've successfully located and verified</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Active Status:</span>
                    <span class="detail-value">Fox is still hidden and can be found</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Found Status:</span>
                    <span class="detail-value">Fox has been found (shows how many times)</span>
                </div>
                <div class="detail">
                    <span class="detail-label">Expired Status:</span>
                    <span class="detail-value">Fox was not found before expiration</span>
                </div>
            </div>
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
    
    // Serial number reveal
    function revealSerial(button, serialNumber) {
        // Create a new span element with the serial number
        const serialSpan = document.createElement('span');
        serialSpan.className = 'serial-revealed';
        serialSpan.textContent = serialNumber;
        
        // Create a copy button
        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'btn btn-info btn-small';
        copyBtn.style.marginLeft = '10px';
        copyBtn.textContent = 'Copy';
        copyBtn.onclick = function(e) {
            e.stopPropagation();
            copyToClipboard(serialNumber);
        };
        
        // Replace the button with the serial number and copy button
        button.parentNode.innerHTML = '';
        button.parentNode.appendChild(serialSpan);
        button.parentNode.appendChild(copyBtn);
    }
    
    // Copy to clipboard function
    function copyToClipboard(text) {
        // Fallback method for older browsers
        const copyToClipboard = (text) => {
            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                return navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    return Promise.resolve();
                } catch (err) {
                    return Promise.reject(err);
                } finally {
                    document.body.removeChild(textArea);
                }
            }
        };
        
        copyToClipboard(text).then(() => {
            // Find and update the copy button
            const buttons = document.querySelectorAll('.btn-info.btn-small');
            buttons.forEach(btn => {
                if (btn.textContent === 'Copy') {
                    btn.textContent = 'Copied!';
                    btn.classList.remove('btn-info');
                    btn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        btn.textContent = 'Copy';
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-info');
                    }, 2000);
                }
            });
        }).catch(err => {
            alert('Failed to copy: ' + err);
        });
    }
    </script>
</body>
</html>
