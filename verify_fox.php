<?php
// verify_fox.php - Fixed with frequency and mode fields
require_once 'config.php';
requireLogin();

$db = getDB();
$fox_id = $_GET['id'] ?? 0;
$fox = null;
$success = false;

// Clean up expired foxes
cleanupExpiredFoxes();

if ($fox_id) {
    // FIXED: Query includes all fields including frequency and mode
    $stmt = $db->prepare("SELECT f.*, u.username as hidden_by_username 
                         FROM foxes f 
                         LEFT JOIN users u ON f.hidden_by = u.id
                         WHERE f.id = ? 
                         AND (f.expires_at IS NULL OR f.expires_at > NOW())");
    $stmt->execute([$fox_id]);
    $fox = $stmt->fetch();
}

if (!$fox) {
    header('Location: find_fox.php?error=invalid_fox');
    exit;
}

// Check if user already found this fox
$already_found = hasUserFoundFox($_SESSION['user_id'], $fox_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number = trim($_POST['serial_number']);
    
    if (empty($serial_number)) {
        $error = "Please enter the serial number";
    } elseif ($serial_number !== $fox['serial_number']) {
        $error = "Invalid serial number. Please check and try again.";
    } elseif ($already_found) {
        $error = "You have already found this fox!";
    } else {
        try {
            // Record the fox find
            $result = recordFoxFind($fox_id, $_SESSION['user_id'], $serial_number, $fox['points']);
            
            if ($result['success']) {
                // Get finders for this fox
                $finders = getFoxFinders($fox_id);
                
                // Store success data in session
                $_SESSION['last_found_fox'] = [
                    'fox_id' => $fox_id,
                    'serial_number' => $fox['serial_number'],
                    'grid_square' => $fox['grid_square'],
                    'frequency' => $fox['frequency'],
                    'mode' => $fox['mode'],
                    'rf_power' => $fox['rf_power'],
                    'notes' => $fox['notes'],
                    'points' => $fox['points'],
                    'hidden_by' => $fox['hidden_by_username'],
                    'finders' => $finders,
                    'total_finds' => count($finders) + 1 // +1 for the current find
                ];
                
                header('Location: verify_fox.php?id=' . $fox_id . '&success=1');
                exit;
            } else {
                $error = $result['message'] ?? "Failed to record fox find. You may have already found this fox.";
            }
        } catch (PDOException $e) {
            $error = "Error recording find: " . $e->getMessage();
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Check for success message
if (isset($_GET['success']) && isset($_SESSION['last_found_fox'])) {
    $success = true;
    $found_data = $_SESSION['last_found_fox'];
    $already_found = true; // User just found it
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Fox - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .frequency-badge {
            background: #ffeb3b;
            color: #333;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-family: monospace;
        }
        
        .mode-badge {
            background: #4caf50;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }
        
        .serial-input {
            font-size: 1.2em;
            font-family: monospace;
            letter-spacing: 2px;
            text-align: center;
        }
        
        .verify-form {
            max-width: 500px;
            margin: 0 auto;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>✅ Verify Fox</h1>
            <div class="header-actions">
                <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="find_fox.php" class="btn btn-secondary">← Back to Find</a>
                <a href="my_foxes.php" class="btn btn-info">My Foxes</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <?php if (isset($_GET['success']) && $success): ?>
            <div class="success-message">
                <div class="celebration">
                    <h2>🎉 Congratulations!</h2>
                    <p>You successfully found and verified fox #<?php echo $found_data['fox_id']; ?>!</p>
                </div>
                
                <div class="found-details">
                    <h3>Fox Details</h3>
                    <div class="details-grid">
                        <div class="detail">
                            <span class="detail-label">Serial Number:</span>
                            <span class="detail-value serial-revealed"><?php echo $found_data['serial_number']; ?></span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">Grid Square:</span>
                            <span class="detail-value"><?php echo $found_data['grid_square']; ?></span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">Frequency:</span>
                            <span class="detail-value frequency-badge"><?php echo $found_data['frequency']; ?> MHz</span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">Mode:</span>
                            <span class="detail-value mode-badge"><?php echo $found_data['mode']; ?></span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">RF Power:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($found_data['rf_power']); ?></span>
                        </div>
                        <?php if ($found_data['notes']): ?>
                        <div class="detail">
                            <span class="detail-label">Notes:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($found_data['notes']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail">
                            <span class="detail-label">Hidden By:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($found_data['hidden_by']); ?></span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">Found By:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">Points Earned:</span>
                            <span class="detail-value points-earned">+<?php echo $found_data['points']; ?> points</span>
                        </div>
                        <div class="detail">
                            <span class="detail-label">Total Times Found:</span>
                            <span class="detail-value"><?php echo $found_data['total_finds']; ?> time(s)</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($found_data['finders'])): ?>
                    <div class="finders-list">
                        <h4>👥 All Finders of This Fox</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Hunter</th>
                                    <th>Found On</th>
                                    <th>Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($found_data['finders'] as $finder): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($finder['username']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($finder['found_at'])); ?></td>
                                    <td>+<?php echo $finder['points_awarded']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="next-steps">
                    <h4>What's Next?</h4>
                    <ul>
                        <li>Your points have been added to the leaderboard</li>
                        <li>This fox remains active for others to find</li>
                        <li>Try finding another fox or hide your own</li>
                    </ul>
                </div>
                
                <div class="action-buttons">
                    <a href="find_fox.php" class="btn btn-primary">Find Another Fox</a>
                    <a href="leaderboard.php" class="btn btn-warning">View Leaderboard</a>
                    <a href="hide_fox.php" class="btn btn-info">Hide Your Own Fox</a>
                </div>
            </div>
            
            <?php 
            // Clear the success data after displaying
            unset($_SESSION['last_found_fox']);
            ?>
            
        <?php else: ?>
            <div class="form-container verify-form">
                <h2>Verify Fox #<?php echo $fox['id']; ?></h2>
                
                <?php if ($already_found): ?>
                    <div class="info-box">
                        <h3>✅ You Already Found This Fox!</h3>
                        <p>You've already claimed points for this fox on 
                           <?php 
                           $stmt = $db->prepare("SELECT found_at FROM fox_finds WHERE user_id = ? AND fox_id = ?");
                           $stmt->execute([$_SESSION['user_id'], $fox_id]);
                           $find = $stmt->fetch();
                           echo $find ? date('M d, Y H:i', strtotime($find['found_at'])) : 'previously';
                           ?>.
                        </p>
                        <p>You can still help others find it, but you won't earn additional points.</p>
                    </div>
                <?php endif; ?>
                
                <div class="fox-info">
                    <h3>Fox Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Grid Square:</span>
                            <span class="info-value"><?php echo $fox['grid_square']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Frequency:</span>
                            <span class="info-value frequency-badge"><?php echo $fox['frequency']; ?> MHz</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Mode:</span>
                            <span class="info-value mode-badge"><?php echo $fox['mode']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">RF Power:</span>
                            <span class="info-value"><?php echo htmlspecialchars($fox['rf_power']); ?></span>
                        </div>
                        <?php if ($fox['notes']): ?>
                        <div class="info-item">
                            <span class="info-label">Notes:</span>
                            <span class="info-value"><?php echo htmlspecialchars($fox['notes']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Hidden By:</span>
                            <span class="info-value"><?php echo htmlspecialchars($fox['hidden_by_username']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Points Available:</span>
                            <span class="info-value points-available"><?php echo $fox['points']; ?> points</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Times Found:</span>
                            <span class="info-value"><?php echo $fox['total_finds']; ?> time(s)</span>
                        </div>
                        <?php if ($fox['expires_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Expires:</span>
                            <span class="info-value"><?php echo date('M d, Y H:i', strtotime($fox['expires_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($fox['total_finds'] > 0): ?>
                    <div class="finders-preview">
                        <h4>Previous Finders</h4>
                        <?php 
                        $finders = getFoxFinders($fox_id);
                        $finder_names = array_map(function($f) { return $f['username']; }, array_slice($finders, 0, 3));
                        ?>
                        <p><?php echo implode(', ', $finder_names); ?>
                           <?php if (count($finders) > 3): ?> and <?php echo (count($finders) - 3); ?> more<?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!$already_found): ?>
                <form method="POST" action="verify_fox.php?id=<?php echo $fox_id; ?>">
                    <div class="form-group">
                        <label for="serial_number">8-Digit Serial Number: *</label>
                        <input type="text" id="serial_number" name="serial_number" 
                               placeholder="12345678" 
                               pattern="\d{8}" 
                               title="8-digit number (e.g., 12345678)" 
                               required
                               maxlength="8"
                               class="serial-input"
                               inputmode="numeric">
                        <small>Found on the physical transmitter. Must match exactly.</small>
                    </div>
                    
                    <div class="form-group">
                        <p><strong>Verifying as:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <input type="hidden" name="found_by" value="<?php echo $_SESSION['user_id']; ?>">
                        <small>Points will be awarded to your account.</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Verify and Claim Points</button>
                        <a href="find_fox.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <?php else: ?>
                    <div class="action-buttons">
                        <a href="find_fox.php" class="btn btn-primary">Find Another Fox</a>
                    </div>
                <?php endif; ?>
                
                <div class="warning-box">
                    <h4>⚠️ Verification Rules</h4>
                    <ul>
                        <li>You must physically locate the transmitter</li>
                        <li>Serial number must match exactly (8 digits)</li>
                        <li>Each user can only claim points once per fox</li>
                        <li>Fox remains active for others to find</li>
                        <li>Be honest - this is a game of skill and honor</li>
                        <li>Expired foxes cannot be verified</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Auto-focus on serial number input
    document.addEventListener('DOMContentLoaded', function() {
        const serialInput = document.getElementById('serial_number');
        if (serialInput) {
            serialInput.focus();
        }
    });
    
    // Format serial number input
    document.getElementById('serial_number')?.addEventListener('input', function(e) {
        // Remove any non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        
        // Limit to 8 digits
        if (this.value.length > 8) {
            this.value = this.value.slice(0, 8);
        }
    });
    </script>
</body>
</html>
