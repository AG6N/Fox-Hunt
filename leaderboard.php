<?php
// leaderboard.php - Fixed version
require_once 'config.php';

$db = getDB();
$leaderboard = [];
$recent_finds = [];
$stats = [];

try {
    // Get leaderboard data from view
    $stmt = $db->query("SELECT * FROM leaderboard_view ORDER BY rank_position");
    $leaderboard = $stmt->fetchAll();
    
    // Get recent finds (last 10)
    $stmt = $db->query("SELECT ff.*, 
        f.grid_square, f.rf_power, f.notes,
        uh.username as hidden_by_username,
        uf.username as found_by_username
        FROM fox_finds ff
        JOIN foxes f ON ff.fox_id = f.id
        LEFT JOIN users uh ON f.hidden_by = uh.id
        LEFT JOIN users uf ON ff.user_id = uf.id
        ORDER BY ff.found_at DESC 
        LIMIT 10");
    $recent_finds = $stmt->fetchAll();
    
    // Get game statistics
    $stats = $db->query("SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM foxes) as total_foxes,
        (SELECT COUNT(*) FROM fox_finds) as total_finds,
        (SELECT SUM(total_points) FROM users) as total_points_awarded,
        (SELECT AVG(points) FROM foxes) as avg_points,
        (SELECT username FROM users ORDER BY total_points DESC LIMIT 1) as top_hunter,
        (SELECT total_points FROM users ORDER BY total_points DESC LIMIT 1) as top_score,
        (SELECT COUNT(*) FROM foxes WHERE expires_at IS NULL OR expires_at > NOW()) as active_foxes
        FROM dual")->fetch();
        
} catch (PDOException $e) {
    $error = "Error loading leaderboard: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .leaderboard-section {
            margin: 30px 0;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .leaderboard-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        
        .leaderboard-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .leaderboard-table tr:hover {
            background: #f8f9fa;
        }
        
        .leaderboard-table tr.top-three {
            background: linear-gradient(90deg, rgba(255,215,0,0.1), rgba(255,215,0,0.05));
        }
        
        .leaderboard-table tr:nth-child(1) {
            background: linear-gradient(90deg, rgba(255,215,0,0.15), rgba(255,215,0,0.08));
            font-weight: bold;
        }
        
        .leaderboard-table tr:nth-child(2) {
            background: linear-gradient(90deg, rgba(192,192,192,0.15), rgba(192,192,192,0.08));
        }
        
        .leaderboard-table tr:nth-child(3) {
            background: linear-gradient(90deg, rgba(205,127,50,0.15), rgba(205,127,50,0.08));
        }
        
        .rank-cell {
            text-align: center;
            font-weight: bold;
            font-size: 1.1em;
            width: 70px;
        }
        
        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }
        
        .username-cell {
            font-weight: 500;
        }
        
        .points-cell {
            font-weight: bold;
            color: #2ecc71;
            text-align: right;
        }
        
        .badges-cell {
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid #3498db;
        }
        
        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .stat-value {
            display: block;
            font-size: 2.5em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .recent-finds {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .recent-finds th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .recent-finds td {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .recent-finds tr:hover {
            background: #f5f5f5;
        }
        
        .medal {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .medal-gold {
            background: #ffd700;
            color: white;
        }
        
        .medal-silver {
            background: #c0c0c0;
            color: white;
        }
        
        .medal-bronze {
            background: #cd7f32;
            color: white;
        }
        
        .user-badges {
            font-size: 1.2em;
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
        
        @media (max-width: 768px) {
            .leaderboard-table,
            .recent-finds {
                font-size: 0.9em;
            }
            
            .leaderboard-table th,
            .leaderboard-table td,
            .recent-finds th,
            .recent-finds td {
                padding: 8px 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .medal {
                width: 25px;
                height: 25px;
                line-height: 25px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🏆 Foxhunt Leaderboard</h1>
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

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="leaderboard-section">
            <h2>Top Fox Hunters</h2>
            
            <?php if (empty($leaderboard)): ?>
                <div class="no-data">
                    <h3>No Leaderboard Data</h3>
                    <p>No hunters have earned points yet.</p>
                    <p>Be the first to find a fox!</p>
                    <a href="find_fox.php" class="btn btn-success">Start Hunting</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Hunter</th>
                                <th>Points</th>
                                <th>Foxes Found</th>
                                <th>Foxes Hidden</th>
                                <th>Badges</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $user): 
                                $rank = $user['rank_position'];
                                $medal_class = '';
                                $medal_icon = '';
                                
                                if ($rank == 1) {
                                    $medal_class = 'medal-gold';
                                    $medal_icon = '🥇';
                                } elseif ($rank == 2) {
                                    $medal_class = 'medal-silver';
                                    $medal_icon = '🥈';
                                } elseif ($rank == 3) {
                                    $medal_class = 'medal-bronze';
                                    $medal_icon = '🥉';
                                }
                            ?>
                                <tr class="<?php echo $rank <= 3 ? 'top-three' : ''; ?>">
                                    <td class="rank-cell">
                                        <?php if ($medal_class): ?>
                                            <span class="medal <?php echo $medal_class; ?>">
                                                <?php echo $medal_icon; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="rank-<?php echo $rank; ?>">
                                                #<?php echo $rank; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="username-cell">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if (isLoggedIn() && $user['username'] == $_SESSION['username']): ?>
                                            <span class="badge-you">(You)</span>
                                        <?php endif; ?>
                                        <?php if ($user['username'] == ($stats['top_hunter'] ?? '')): ?>
                                            <span class="crown">👑</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="points-cell"><?php echo $user['total_points']; ?></td>
                                    <td><?php echo $user['foxes_found']; ?></td>
                                    <td><?php echo $user['foxes_hidden']; ?></td>
                                    <td class="badges-cell">
                                        <div class="user-badges">
                                            <?php if ($user['total_points'] >= 100): ?>🏅<?php endif; ?>
                                            <?php if ($user['total_points'] >= 50): ?>⭐<?php endif; ?>
                                            <?php if ($user['total_points'] >= 25): ?>🔍<?php endif; ?>
                                            <?php if ($user['foxes_hidden'] >= 5): ?>🦊<?php endif; ?>
                                            <?php if ($user['foxes_found'] >= 5): ?>🎯<?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Hunters</h3>
                <span class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></span>
                <span class="stat-label">Registered Users</span>
            </div>
            
            <div class="stat-card">
                <h3>Active Foxes</h3>
                <span class="stat-value"><?php echo $stats['active_foxes'] ?? 0; ?></span>
                <span class="stat-label">Currently Hidden</span>
            </div>
            
            <div class="stat-card">
                <h3>Total Finds</h3>
                <span class="stat-value"><?php echo $stats['total_finds'] ?? 0; ?></span>
                <span class="stat-label">Successful Hunts</span>
            </div>
            
            <div class="stat-card">
                <h3>Points Awarded</h3>
                <span class="stat-value"><?php echo $stats['total_points_awarded'] ?? 0; ?></span>
                <span class="stat-label">Total Points</span>
            </div>
        </div>

        <div class="section">
            <h2>Recent Finds</h2>
            <?php if (!empty($recent_finds)): ?>
                <table class="recent-finds">
                    <thead>
                        <tr>
                            <th>Fox ID</th>
                            <th>Grid Square</th>
                            <th>RF Power</th>
                            <th>Found By</th>
                            <th>Date Found</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_finds as $find): ?>
                            <tr>
                                <td>#<?php echo $find['fox_id']; ?></td>
                                <td class="grid-cell"><?php echo $find['grid_square']; ?></td>
                                <td><?php echo htmlspecialchars($find['rf_power']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($find['found_by_username']); ?>
                                    <?php if (isLoggedIn() && $find['found_by_username'] == $_SESSION['username']): ?>
                                        <span class="badge-you">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d H:i', strtotime($find['found_at'])); ?></td>
                                <td class="points-cell">+<?php echo $find['points_awarded']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <p>No recent finds to display.</p>
                    <a href="find_fox.php" class="btn btn-success">Be the first to find a fox!</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($stats['top_hunter']): ?>
            <div class="top-hunter-section">
                <div class="top-hunter-card">
                    <h3>👑 Current Champion</h3>
                    <div class="champion-info">
                        <div class="champion-avatar">
                            <span class="crown-large">👑</span>
                        </div>
                        <div class="champion-details">
                            <h4><?php echo htmlspecialchars($stats['top_hunter']); ?></h4>
                            <p class="champion-points"><?php echo $stats['top_score']; ?> points</p>
                            <p class="champion-stats">
                                <span>Rank: #1</span>
                                <span>•</span>
                                <span>Top of the leaderboard</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="leaderboard-legend">
            <h3>Legend</h3>
            <div class="legend-grid">
                <div class="legend-item">
                    <span class="legend-icon">🥇</span>
                    <span class="legend-text">Gold Medal (1st Place)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">🥈</span>
                    <span class="legend-text">Silver Medal (2nd Place)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">🥉</span>
                    <span class="legend-text">Bronze Medal (3rd Place)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">👑</span>
                    <span class="legend-text">Current Champion</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">🏅</span>
                    <span class="legend-text">100+ Points</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">⭐</span>
                    <span class="legend-text">50+ Points</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">🔍</span>
                    <span class="legend-text">25+ Points</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">🦊</span>
                    <span class="legend-text">5+ Foxes Hidden</span>
                </div>
                <div class="legend-item">
                    <span class="legend-icon">🎯</span>
                    <span class="legend-text">5+ Foxes Found</span>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="find_fox.php" class="btn btn-success">Find More Foxes</a>
                <a href="hide_fox.php" class="btn btn-primary">Hide a Fox</a>
                <a href="my_foxes.php" class="btn btn-info">My Stats</a>
            <?php else: ?>
                <a href="signup.php" class="btn btn-success">Join the Hunt</a>
                <a href="find_fox.php" class="btn btn-primary">View Active Foxes</a>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .top-hunter-section {
            margin: 40px 0;
        }
        
        .top-hunter-card {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .top-hunter-card h3 {
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .champion-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .champion-avatar {
            font-size: 4em;
        }
        
        .champion-details {
            text-align: left;
        }
        
        .champion-details h4 {
            font-size: 2em;
            margin: 0 0 10px 0;
        }
        
        .champion-points {
            font-size: 1.5em;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        
        .champion-stats {
            color: rgba(255,255,255,0.9);
            font-size: 0.9em;
        }
        
        .leaderboard-legend {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
        }
        
        .leaderboard-legend h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        
        .legend-icon {
            font-size: 1.5em;
            width: 30px;
            text-align: center;
        }
        
        .legend-text {
            color: #2c3e50;
        }
        
        .badge-you {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        
        .crown {
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .champion-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .champion-details {
                text-align: center;
            }
            
            .legend-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
