<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

check_login();

$response = ['success' => false];

if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $upload_dir = 'uploads/chat/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['image']['name'];
    $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($filetype, $allowed)) {
        $new_filename = uniqid() . '.' . $filetype;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $response = [
                'success' => true,
                'file_name' => $new_filename,
                'file_path' => $upload_path
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>