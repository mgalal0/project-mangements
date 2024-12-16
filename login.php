<?php
session_start();
require_once 'config.php';

$login_err = '';

if(isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // For debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $sql = "SELECT * FROM users WHERE username = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if(mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                
                // For debugging
                echo "<!--";
                echo "Stored Hash: " . $row['password'] . "\n";
                echo "Entered Password: " . $password . "\n";
                echo "Password Verify Result: " . (password_verify($password, $row['password']) ? 'true' : 'false') . "\n";
                echo "-->";
                
                if(password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $login_err = "Invalid password";
                }
            } else {
                $login_err = "User not found";
            }
        } else {
            $login_err = "Query failed: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
        <h2 class="text-2xl font-bold mb-6">Login</h2>
        <?php 
        if(!empty($login_err)) {
            echo "<div class='text-red-500 mb-4'>$login_err</div>";
        }
        ?>
        
        <form action="" method="post">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Username</label>
                <input type="text" name="username" 
                       class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 mb-2">Password</label>
                <input type="password" name="password" 
                       class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <button type="submit" name="login" 
                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
                Login
            </button>
        </form>
    </div>
</body>
</html>