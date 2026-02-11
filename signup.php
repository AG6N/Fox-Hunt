<?php
// signup.php - Fixed email handling
require_once 'config.php';

// If already logged in, redirect to home
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username) || strlen($username) < 3) {
        $error = "Username must be at least 3 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        try {
            $db = getDB();
            
            // Check if username exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                $error = "Username already exists";
            } else {
                // Handle email - generate unique if empty
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
                        if ($counter > 100) { // Safety limit
                            $email = uniqid() . '@foxhunt.local';
                            break;
                        }
                    }
                } else {
                    // Check if provided email already exists
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()['count'] > 0) {
                        $error = "Email already exists. Please use a different email.";
                        // Don't return here, let it continue to generate a unique email
                        $email = ''; // Reset to trigger auto-generation
                    }
                }
                
                // Create new user
                $password_hash = hashPassword($password);
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                
                // Auto-login after signup
                if (loginUser($username, $password)) {
                    header('Location: index.php');
                    exit;
                } else {
                    $success = true;
                }
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                $error = "Email already exists. Please use a different email or leave it blank to auto-generate.";
            } else {
                $error = "Registration error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Foxhunt</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📝 Sign Up for Foxhunt</h1>
            <a href="index.php" class="btn btn-secondary">← Back to Home</a>
        </header>

        <?php if ($success): ?>
            <div class="success-message">
                <h2>✅ Account Created Successfully!</h2>
                <p>Your account has been created. You can now log in.</p>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        <?php else: ?>
            <div class="form-container">
                <h2>Create New Account</h2>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="signup.php">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" 
                               required minlength="3" maxlength="20"
                               pattern="[A-Za-z0-9_]+"
                               title="Letters, numbers, and underscores only"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <small>3-20 characters, letters, numbers, underscores only</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email (optional):</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <small>Leave blank to auto-generate a unique email</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" 
                               required minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" 
                               name="confirm_password" required minlength="6">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                        <a href="login.php" class="btn btn-secondary">Already have an account?</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
