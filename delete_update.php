<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
check_login();

$response = ['success' => false];

if (isset($_GET['id'])) {
    $update_id = (int)$_GET['id'];
    
    // Check if user owns this update or is admin
    $sql = "SELECT user_id FROM project_updates WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $update_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $update = mysqli_fetch_assoc($result);

    if ($update && ($_SESSION['user_id'] == $update['user_id'] || is_admin())) {
        $sql = "DELETE FROM project_updates WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $update_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $response['success'] = true;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>