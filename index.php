<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foxhunt - Radio Transmitter Hunting Game</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🦊 Foxhunt</h1>
            <p class="subtitle">Radio Transmitter Hunting Game v2.1</p>
            
            <div class="header-actions">
                <?php if (isLoggedIn()): ?>
                    <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="my_foxes.php" class="btn btn-info">My Foxes</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin.php" class="btn btn-warning">Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="signup.php" class="btn btn-success">Sign Up</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="hero">
            <div class="hero-content">
                <h2>Find the Hidden Fox!</h2>
                <p>A "fox" is a radio transmitter sending morse code. Hunters search for it using radio direction finding techniques.</p>
            </div>
        </div>

        <div class="dashboard">
            <div class="card">
                <h3>👤 Hide a Fox</h3>
                <p>Hide a transmitter in a 6-digit grid square with frequency and mode</p>
                <?php if (isLoggedIn()): ?>
                    <a href="hide_fox.php" class="btn btn-primary">Hide New Fox</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Login to Hide Fox</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>🔍 Find a Fox</h3>
                <p>Search for hidden transmitters by frequency and mode</p>
                <a href="find_fox.php" class="btn btn-success">Find Fox</a>
            </div>

            <div class="card">
                <h3>🏆 Leaderboard</h3>
                <p>See top fox hunters</p>
                <a href="leaderboard.php" class="btn btn-warning">View Leaderboard</a>
            </div>
        </div>

        <div class="instructions">
            <h3>How to Play</h3>
            <ol>
                <li><strong>Hide a Fox:</strong> Log in and hide a transmitter with frequency, mode, and 8-digit serial number</li>
                <li><strong>Find a Fox:</strong> Search for transmitters using direction finding on the specified frequency</li>
                <li><strong>Verify:</strong> Once found, enter the serial number to claim points</li>
                <li><strong>Score:</strong> Each found fox earns points based on difficulty</li>
            </ol>

            <div class="game-info">
                <div class="info-box">
                    <h4>📡 Fox Information</h4>
                    <p><strong>Grid Square:</strong> 6-digit location reference (e.g., FN31pr)</p>
                    <p><strong>Frequency:</strong> Operating frequency in MHz (e.g., 146.520)</p>
                    <p><strong>Mode:</strong> Transmission mode (FM, SSB, CW, AM)</p>
                    <p><strong>RF Power:</strong> Transmitter power level (e.g., 5W, 10W)</p>
                    <p><strong>Serial Number:</strong> 8-digit unique identifier</p>
                </div>

                <div class="info-box">
                    <h4>🎯 Hunting Rules</h4>
                    <p>• Use radio direction finding equipment</p>
                    <p>• Tune to the correct frequency and mode</p>
                    <p>• Follow all local regulations</p>
                    <p>• Respect private property</p>
                    <p>• Have fun and learn radio skills!</p>
                </div>
            </div>
        </div>

        <footer>
            <p>Foxhunt v2.1 | Radio Transmitter Hunting Game | 
                <?php if (isLoggedIn()): ?>
                    Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?>
                <?php else: ?>
                    <a href="login.php">Login</a> to hide foxes
                <?php endif; ?>
            </p>
        </footer>
    </div>
</body>
</html>
