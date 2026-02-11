<?php
// reset_passwords.php - One-time script to reset passwords
require_once 'config.php';

if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    die("Add ?confirm=yes to URL to reset passwords");
}

$db = getDB();

// Update passwords
$users = [
    ['admin', 'admin123'],
    ['FoxHunter1', 'FoxHunter1'],
    ['RadioExpert', 'RadioExpert'],
    ['MorseMaster', 'MorseMaster'],
    ['GridSeeker', 'GridSeeker'],
    ['SignalChaser', 'SignalChaser']
];

foreach ($users as $user) {
    $username = $user[0];
    $password = $user[1];
    $hash = md5($password);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    
    echo "Updated password for $username to '$password' (hash: $hash)<br>";
}

echo "<hr>All passwords reset!<br>";
echo "<a href='login.php'>Go to Login</a>";
?>
