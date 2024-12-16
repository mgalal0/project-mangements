<?php
require_once 'config.php';

// Create a new admin user
$username = 'admin';
$password = 'admin123';
$email = 'admin@example.com';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashed_password);
    
    if(mysqli_stmt_execute($stmt)) {
        echo "Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error creating admin user: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>