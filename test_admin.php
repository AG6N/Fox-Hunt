<?php
// test_admin.php
require_once 'config.php';

echo "<h1>Admin Test Page</h1>";

// Test password hashing
$password = 'admin123';
$hash = md5($password);
echo "<p>Password: $password</p>";
echo "<p>MD5 Hash: $hash</p>";

// Check if this matches the database hash
$db = getDB();
$stmt = $db->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
$stmt->execute();
$admin_hash = $stmt->fetch()['password_hash'];

echo "<p>Database Hash: $admin_hash</p>";
echo "<p>Match: " . ($hash === $admin_hash ? 'YES' : 'NO') . "</p>";

// Test login function
if (loginUser('admin', 'admin123')) {
    echo "<p style='color: green;'>✓ Admin login successful!</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Username: " . $_SESSION['username'] . "</p>";
    echo "<p>Is Admin: " . ($_SESSION['is_admin'] ? 'YES' : 'NO') . "</p>";
} else {
    echo "<p style='color: red;'>✗ Admin login failed!</p>";
}

echo "<hr>";
echo "<a href='login.php'>Go to Login Page</a><br>";
echo "<a href='index.php'>Go to Home Page</a>";
?>
