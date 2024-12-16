<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'admin_dashboard');

try {
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if(!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
} catch(Exception $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}
?>