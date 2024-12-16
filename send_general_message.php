<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

check_login();

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $file_name = null;
    $file_type = null;
    
    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = 'uploads/general_chat/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['file']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($filetype, $allowed)) {
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_path)) {
                $file_name = $new_filename;
                $file_type = $filetype;
            }
        }
    }
    
    if ($message || $file_name) {
        $sql = "INSERT INTO general_chat_messages (user_id, message, file_name, file_type) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isss", $_SESSION['user_id'], $message, $file_name, $file_type);
        
        if (mysqli_stmt_execute($stmt)) {
            $response['success'] = true;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>