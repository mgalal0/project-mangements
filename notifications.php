<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

check_login();

// Mark notification as read
if (isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND to_user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
}

// Get user's notifications
$sql = "SELECT n.*, 
        u.username as from_username,
        p.name as project_name
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        LEFT JOIN projects p ON n.project_id = p.id
        WHERE n.to_user_id = ?
        ORDER BY n.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$notifications = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Notifications</h1>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if (mysqli_num_rows($notifications) > 0): ?>
                <div class="space-y-4">
                    <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                        <div class="<?php echo $notification['is_read'] ? 'bg-gray-50' : 'bg-blue-50'; ?> p-4 rounded-lg">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-gray-800"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        From: <?php echo htmlspecialchars($notification['from_username']); ?>
                                        • <?php echo time_ago($notification['created_at']); ?>
                                        <?php if ($notification['project_name']): ?>
                                            • Project: <?php echo htmlspecialchars($notification['project_name']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" class="ml-4">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" name="mark_read" class="text-blue-600 hover:text-blue-800">
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">Soon - No Notifications</p>
            <?php endif; ?>
        </div>
    </div>



    <?php include 'loader.php'; ?>
</body>
</html>