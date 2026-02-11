<?php
// find_fox.php - Updated with frequency and mode fields
require_once 'config.php';

// Clean up expired foxes
cleanupExpiredFoxes();

$db = getDB();
$active_foxes = [];
$found_foxes = [];
$error = '';

try {
    // Get active (not expired) foxes
    $stmt = $db->prepare("SELECT f.*, u.username as hidden_by_username,
                         (SELECT COUNT(*) FROM fox_finds WHERE fox_id = f.id) as total_finds,
                         (SELECT GROUP_CONCAT(DISTINCT uf.username ORDER BY ff.found_at SEPARATOR ', ') 
                          FROM fox_finds ff 
                          JOIN users uf ON ff.user_id = uf.id 
                          WHERE ff.fox_id = f.id 
                          LIMIT 3) as recent_finders
        FROM foxes f 
        LEFT JOIN users u ON f.hidden_by = u.id
        WHERE f.expires_at IS NULL OR f.expires_at > NOW()
        ORDER BY f.hidden_at DESC");
    $stmt->execute();
    $active_foxes = $stmt->fetchAll();
    
    // Get recently found foxes (last 10 finds)
    $stmt = $db->query("SELECT ff.*, f.grid_square, f.frequency, f.mode, f.rf_power, f.notes, 
                        uh.username as hidden_by_username,
                        uf.username as found_by_username
        FROM fox_finds ff
        JOIN foxes f ON ff.fox_id = f.id
        LEFT JOIN users uh ON f.hidden_by = uh.id
        LEFT JOIN users uf ON ff.user_id = uf.id
        ORDER BY ff.found_at DESC 
        LIMIT 10");
    $found_foxes = $stmt->fetchAll();
    
    // Check if user is logged in and has found any foxes
    $user_found_foxes = [];
    if (isLoggedIn()) {
        $stmt = $db->prepare("SELECT fox_id FROM fox_finds WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_found_foxes = array_column($stmt->fetchAll(), 'fox_id');
    }
    
} catch (PDOException $e) {
    $error = "Error loading foxes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Fox - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Compact Fox Cards */
        .foxes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .fox-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            position: relative;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .fox-card:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            border-color: #3498db;
        }
        
        .fox-card.expiring-soon {
            border-color: #ff9800;
            background: #fff8e1;
        }
        
        .fox-card.found-by-me {
            border-color: #2ecc71;
            background: #f0f9f0;
        }
        
        .fox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .fox-id {
            font-weight: bold;
            color: #2c3e50;
            font-size: 1em;
        }
        
        .fox-status {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-found {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .fox-details {
            flex: 1;
            margin-bottom: 12px;
        }
        
        .fox-detail-row {
            display: flex;
            margin-bottom: 6px;
            font-size: 0.9em;
        }
        
        .fox-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 85px;
            font-size: 0.85em;
        }
        
        .fox-value {
            color: #555;
            flex: 1;
            font-size: 0.85em;
        }
        
        .grid-square-value {
            font-family: monospace;
            font-weight: bold;
            color: #2c3e50;
            background: #f0f8ff;
            padding: 1px 6px;
            border-radius: 3px;
            border: 1px solid #bdc3c7;
            font-size: 0.9em;
        }
        
        .frequency-value {
            font-family: monospace;
            font-weight: bold;
            color: #e74c3c;
            background: #ffeaa7;
            padding: 1px 6px;
            border-radius: 3px;
            border: 1px solid #fdcb6e;
            font-size: 0.9em;
        }
        
        .mode-value {
            font-family: monospace;
            font-weight: bold;
            color: #27ae60;
            background: #d5f4e6;
            padding: 1px 6px;
            border-radius: 3px;
            border: 1px solid #82e0aa;
            font-size: 0.9em;
        }
        
        .fox-notes {
            font-style: italic;
            color: #7f8c8d;
            margin: 8px 0;
            padding: 6px;
            background: #f9f9f9;
            border-left: 2px solid #3498db;
            border-radius: 0 3px 3px 0;
            font-size: 0.85em;
            line-height: 1.3;
        }
        
        .finders-list {
            margin: 10px 0 8px 0;
            padding: 6px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.8em;
        }
        
        .finders-list h5 {
            margin-bottom: 3px;
            color: #7f8c8d;
            font-size: 0.85em;
        }
        
        .fox-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .points-badge {
            background: #f39c12;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.9em;
            white-space: nowrap;
        }
        
        .expiry-warning {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff9800;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Action buttons */
        .action-btn {
            padding: 6px 12px;
            font-size: 0.85em;
            white-space: nowrap;
        }
        
        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Recent finds table */
        .recent-finds-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
            box-shadow: 0 0 3px rgba(0,0,0,0.1);
            border-radius: 5px;
            overflow: hidden;
            font-size: 0.9em;
        }
        
        .recent-finds-table th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.9em;
        }
        
        .recent-finds-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .recent-finds-table tr:hover {
            background: #f5f5f5;
        }
        
        .serial-masked {
            font-family: monospace;
            letter-spacing: 1px;
            font-size: 0.9em;
        }
        
        /* Section headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.4em;
        }
        
        .fox-count {
            background: #3498db;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        /* No foxes message */
        .no-foxes {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .no-foxes h3 {
            margin: 0 0 10px 0;
            color: #7f8c8d;
            font-size: 1.2em;
        }
        
        .no-foxes p {
            margin: 0 0 15px 0;
            color: #7f8c8d;
            font-size: 0.95em;
        }
        
        /* Info boxes */
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
            font-size: 0.9em;
        }
        
        .info-box h3 {
            margin: 0 0 10px 0;
            color: #1565c0;
            font-size: 1.1em;
        }
        
        .info-box ul {
            margin: 0 0 15px 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin: 5px 0;
            color: #388e3c;
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        /* Radio band colors */
        .band-2m {
            background: #e3f2fd;
        }
        
        .band-70cm {
            background: #f3e5f5;
        }
        
        .band-hf {
            background: #fff3e0;
        }
        
        .band-23cm {
            background: #e8f5e9;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .foxes-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 12px;
            }
            
            .fox-card {
                padding: 12px;
            }
            
            .fox-detail-row {
                flex-direction: column;
                margin-bottom: 8px;
            }
            
            .fox-label {
                min-width: auto;
                margin-bottom: 2px;
            }
            
            .fox-footer {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }
            
            .action-btn {
                width: 100%;
            }
            
            .recent-finds-table {
                font-size: 0.85em;
            }
            
            .recent-finds-table th,
            .recent-finds-table td {
                padding: 6px 8px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .foxes-grid {
                grid-template-columns: 1fr;
            }
            
            .fox-card {
                max-width: 100%;
            }
        }
        
        /* Quick stats bar */
        .stats-bar {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        
        .stat-icon {
            font-size: 1.2em;
        }
        
        /* Filter buttons */
        .filter-buttons {
            display: flex;
            gap: 8px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 5px 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 15px;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            background: #e9ecef;
        }
        
        .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Find a Fox</h1>
            <div class="header-actions">
                <?php if (isLoggedIn()): ?>
                    <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="my_foxes.php" class="btn btn-info">My Foxes</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="signup.php" class="btn btn-success">Sign Up</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">← Home</a>
            </div>
        </header>

        <div class="content">
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-icon">🦊</span>
                    <span><?php echo count($active_foxes); ?> Active Foxes</span>
                </div>
                <?php if (isLoggedIn()): ?>
                <div class="stat-item">
                    <span class="stat-icon">✅</span>
                    <span><?php echo count($user_found_foxes); ?> Found by You</span>
                </div>
                <?php endif; ?>
                <div class="stat-item">
                    <span class="stat-icon">👥</span>
                    <span><?php echo count($found_foxes); ?> Recent Finds</span>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>Active Foxes</h2>
                    <span class="fox-count"><?php echo count($active_foxes); ?> available</span>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (empty($active_foxes)): ?>
                    <div class="no-foxes">
                        <h3>No Active Foxes</h3>
                        <p>There are currently no active foxes to find.</p>
                        <?php if (isLoggedIn()): ?>
                            <a href="hide_fox.php" class="btn btn-primary btn-small">Hide a New Fox</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-small">Login to Hide a Fox</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="foxes-grid">
                        <?php foreach ($active_foxes as $fox): 
                            $expires_soon = false;
                            $user_has_found = in_array($fox['id'], $user_found_foxes);
                            
                            if ($fox['expires_at']) {
                                $expires = new DateTime($fox['expires_at']);
                                $now = new DateTime();
                                $interval = $now->diff($expires);
                                $expires_soon = $interval->days < 2;
                            }
                            
                            // Determine band for styling
                            $band_class = '';
                            if (strpos($fox['frequency'], '146') === 0 || strpos($fox['frequency'], '144') === 0) {
                                $band_class = 'band-2m';
                            } elseif (strpos($fox['frequency'], '446') === 0) {
                                $band_class = 'band-70cm';
                            } elseif (strpos($fox['frequency'], '1296') === 0) {
                                $band_class = 'band-23cm';
                            } elseif (strpos($fox['frequency'], '7.') === 0 || strpos($fox['frequency'], '14.') === 0) {
                                $band_class = 'band-hf';
                            }
                            
                            $card_classes = ['fox-card'];
                            if ($expires_soon) $card_classes[] = 'expiring-soon';
                            if ($user_has_found) $card_classes[] = 'found-by-me';
                            if ($band_class) $card_classes[] = $band_class;
                        ?>
                            <div class="<?php echo implode(' ', $card_classes); ?>">
                                <div class="fox-header">
                                    <div class="fox-id">Fox #<?php echo $fox['id']; ?></div>
                                    <div class="fox-status">
                                        <?php if ($user_has_found): ?>
                                            <span class="status-badge status-found">✅ Found</span>
                                        <?php endif; ?>
                                        <?php if ($fox['total_finds'] > 0): ?>
                                            <span class="status-badge status-found">👥 <?php echo $fox['total_finds']; ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-active">🦊 Active</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="fox-details">
                                    <div class="fox-detail-row">
                                        <span class="fox-label">Grid:</span>
                                        <span class="fox-value grid-square-value"><?php echo $fox['grid_square']; ?></span>
                                    </div>
                                    
                                    <div class="fox-detail-row">
                                        <span class="fox-label">Frequency:</span>
                                        <span class="fox-value frequency-value"><?php echo $fox['frequency']; ?> MHz</span>
                                    </div>
                                    
                                    <div class="fox-detail-row">
                                        <span class="fox-label">Mode:</span>
                                        <span class="fox-value mode-value"><?php echo htmlspecialchars($fox['mode']); ?></span>
                                    </div>
                                    
                                    <div class="fox-detail-row">
                                        <span class="fox-label">Power:</span>
                                        <span class="fox-value"><?php echo htmlspecialchars($fox['rf_power']); ?></span>
                                    </div>
                                    
                                    <div class="fox-detail-row">
                                        <span class="fox-label">Hidden By:</span>
                                        <span class="fox-value"><?php echo htmlspecialchars($fox['hidden_by_username']); ?></span>
                                    </div>
                                    
                                    <div class="fox-detail-row">
                                        <span class="fox-label">Expires:</span>
                                        <span class="fox-value">
                                            <?php if ($fox['expires_at']): ?>
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
                                                Never
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($fox['notes']): ?>
                                        <div class="fox-notes" title="<?php echo htmlspecialchars($fox['notes']); ?>">
                                            📝 <?php echo htmlspecialchars(strlen($fox['notes']) > 40 ? substr($fox['notes'], 0, 40) . '...' : $fox['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($fox['total_finds'] > 0 && $fox['recent_finders']): ?>
                                        <div class="finders-list">
                                            <h5>Recent Finders:</h5>
                                            <p><?php echo htmlspecialchars(strlen($fox['recent_finders']) > 50 ? substr($fox['recent_finders'], 0, 50) . '...' : $fox['recent_finders']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="fox-footer">
                                    <div class="points-badge">
                                        <?php echo $fox['points']; ?> pts
                                    </div>
                                    
                                    <div>
                                        <?php if (isLoggedIn()): ?>
                                            <?php if ($user_has_found): ?>
                                                <button class="btn btn-success action-btn" disabled>
                                                    ✅ Found
                                                </button>
                                            <?php else: ?>
                                                <a href="verify_fox.php?id=<?php echo $fox['id']; ?>" class="btn btn-success action-btn">
                                                    🔍 Verify
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-success action-btn">
                                                🔐 Login
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($expires_soon): ?>
                                    <div class="expiry-warning">Soon!</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>Recent Finds</h2>
                    <span class="fox-count">Last <?php echo count($found_foxes); ?></span>
                </div>
                
                <?php if (!empty($found_foxes)): ?>
                    <table class="recent-finds-table">
                        <thead>
                            <tr>
                                <th>Fox</th>
                                <th>Grid</th>
                                <th>Freq</th>
                                <th>Mode</th>
                                <th>Found By</th>
                                <th>Date</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($found_foxes as $find): ?>
                                <tr>
                                    <td>#<?php echo $find['fox_id']; ?></td>
                                    <td class="grid-square-value"><?php echo $find['grid_square']; ?></td>
                                    <td class="frequency-value"><?php echo $find['frequency']; ?></td>
                                    <td class="mode-value"><?php echo $find['mode']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($find['found_by_username']); ?>
                                        <?php if (isLoggedIn() && $find['found_by_username'] == $_SESSION['username']): ?>
                                            <span style="color: #3498db; font-weight: bold;">(You)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d', strtotime($find['found_at'])); ?></td>
                                    <td class="points-cell" style="color: #27ae60; font-weight: bold;">+<?php echo $find['points_awarded']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 5px;">
                        <p>No recent finds yet.</p>
                        <?php if (!isLoggedIn()): ?>
                            <a href="signup.php" class="btn btn-success btn-small">Be the first!</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!isLoggedIn()): ?>
                <div class="info-box">
                    <h3>🔐 Join the Hunt!</h3>
                    <p>You need to be logged in to:</p>
                    <ul>
                        <li>Verify fox finds and earn points</li>
                        <li>Hide your own foxes</li>
                        <li>Track your hunting progress</li>
                        <li>Compete on the leaderboard</li>
                    </ul>
                    <div class="action-buttons">
                        <a href="login.php" class="btn btn-primary">Login</a>
                        <a href="signup.php" class="btn btn-success">Sign Up Free</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>🎯 Quick Guide</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li><strong>Select a Fox:</strong> Note its grid square, frequency, and mode</li>
                    <li><strong>Tune Your Radio:</strong> Set to the fox's frequency and mode</li>
                    <li><strong>Go Hunting:</strong> Use radio direction finding to locate it</li>
                    <li><strong>Find It:</strong> Locate the transmitter and get its 8-digit serial number</li>
                    <li><strong>Verify:</strong> Click "Verify" and enter the serial number</li>
                    <li><strong>Earn Points:</strong> Each fox gives points shown on its card</li>
                </ol>
                <p style="margin-top: 10px; font-style: italic; color: #7f8c8d;">
                    Note: Multiple hunters can find the same fox - all earn points!
                </p>
            </div>
        </div>
    </div>
</body>
</html>
