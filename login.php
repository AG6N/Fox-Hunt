<?php
// login.php - Updated
require_once 'config.php';

// If already logged in, redirect to home
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (loginUser($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔐 Login to Foxhunt</h1>
            <a href="index.php" class="btn btn-secondary">← Back to Home</a>
        </header>

        <div class="form-container">
            <h2>Sign In</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Login</button>
                    <a href="signup.php" class="btn btn-secondary">Create Account</a>
                </div>
            </form>
            
        </div>
    </div>
</body>
</html>
