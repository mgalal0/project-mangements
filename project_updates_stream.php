<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once 'config.php';

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

while (true) {
    $sql = "SELECT COUNT(*) as count FROM project_updates WHERE project_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $count = mysqli_fetch_assoc($result)['count'];

    echo "data: " . json_encode(['count' => $count]) . "\n\n";
    
    ob_flush();
    flush();
    
    sleep(5); // Check for updates every 5 seconds
}
?>