<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_login() {
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function get_avatar($user_id) {
    // Return a default avatar URL - you can implement custom avatars later
    return "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['username']);
}

function get_status_color($status) {
    switch($status) {
        case 'pending':
            return 'yellow';
        case 'in_progress':
            return 'blue';
        case 'completed':
            return 'green';
        default:
            return 'gray';
    }
}

function time_ago($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year(s) ago';
    if ($diff->m > 0) return $diff->m . ' month(s) ago';
    if ($diff->d > 0) return $diff->d . ' day(s) ago';
    if ($diff->h > 0) return $diff->h . ' hour(s) ago';
    if ($diff->i > 0) return $diff->i . ' minute(s) ago';
    return 'Just now';
}

function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

function get_department_users($department) {
    global $conn;
    $sql = "SELECT * FROM users WHERE department = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $department);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}
?>

