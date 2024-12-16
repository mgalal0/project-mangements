<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
check_login();

$success_msg = $error_msg = '';
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get project data with department info
$sql = "SELECT p.*, GROUP_CONCAT(pd.department) as departments 
        FROM projects p 
        LEFT JOIN project_departments pd ON p.id = pd.project_id 
        WHERE p.id = ?
        GROUP BY p.id";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($result);

if (!$project) {
    header("Location: dashboard.php");
    exit;
}

// Get user's department
$user_sql = "SELECT department FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

// Check if user has access to this project
$project_departments = explode(',', $project['departments']);
if (!is_admin() && !in_array($user['department'], $project_departments)) {
    header("Location: dashboard.php");
    exit;
}

// Handle progress update submission
if (isset($_POST['add_update'])) {
    $update_text = sanitize_input($_POST['update_text']);
    $sql = "INSERT INTO project_updates (project_id, user_id, update_text) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iis", $project_id, $_SESSION['user_id'], $update_text);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "Update added successfully!";
        header("Location: project_details.php?id=" . $project_id);
        exit;
    } else {
        $error_msg = "Error adding update.";
    }
}

// Get all updates for this project
$updates_sql = "SELECT pu.*, u.username, u.department 
                FROM project_updates pu 
                JOIN users u ON pu.user_id = u.id 
                WHERE pu.project_id = ? 
                ORDER BY pu.created_at DESC";
$updates_stmt = mysqli_prepare($conn, $updates_sql);
mysqli_stmt_bind_param($updates_stmt, "i", $project_id);
mysqli_stmt_execute($updates_stmt);
$updates = mysqli_stmt_get_result($updates_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Details</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Project: <?php echo htmlspecialchars($project['name']); ?></h1>
                <p class="text-gray-600">Status: 
                    <span class="px-2 py-1 rounded-full text-sm font-medium
                        <?php
                        switch($project['status']) {
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                        }
                        ?>">
                        <?php echo ucfirst($project['status']); ?>
                    </span>
                </p>
            </div>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if ($success_msg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Project Details -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">Project Details</h2>
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-medium text-gray-700">Description</h3>
                            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-700">Deadline</h3>
                            <p class="text-gray-600"><?php echo date('F d, Y', strtotime($project['deadline'])); ?></p>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-700">Departments Involved</h3>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <?php foreach ($project_departments as $dept): ?>
                                <span class="px-2 py-1 rounded-full text-sm font-medium
                                    <?php
                                    switch($dept) {
                                        case 'payments': echo 'bg-green-100 text-green-800'; break;
                                        case 'ui_ux': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'frontend': echo 'bg-purple-100 text-purple-800'; break;
                                        case 'backend': echo 'bg-yellow-100 text-yellow-800'; break;
                                    }
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', '/', $dept)); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Updates Section -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Progress Updates</h2>
                    <form method="POST" class="mb-6">
                        <div class="flex space-x-4">
                            <input type="text" name="update_text" required
                                   placeholder="Add a progress update..."
                                   class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <button type="submit" name="add_update"
                                    class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600">
                                Add Update
                            </button>
                        </div>
                    </form>

                    <div class="space-y-4">
                        <?php while ($update = mysqli_fetch_assoc($updates)): ?>
                        <div class="border-l-4 border-blue-500 pl-4 py-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-gray-800"><?php echo htmlspecialchars($update['update_text']); ?></p>
                                    <p class="text-sm text-gray-500">
                                        By <?php echo htmlspecialchars($update['username']); ?> 
                                        (<?php echo ucfirst(str_replace('_', '/', $update['department'])); ?>) • 
                                        <?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?>
                                    </p>
                                </div>
                                <?php if ($_SESSION['user_id'] == $update['user_id']): ?>
                                <button onclick="deleteUpdate(<?php echo $update['id']; ?>)"
                                        class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Project Timeline -->
            <div class="bg-white rounded-lg shadow-md p-6 h-fit">
                <h2 class="text-xl font-bold mb-4">Project Timeline</h2>
                <div class="space-y-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white">
                            <i class="fas fa-flag"></i>
                        </div>
                        <div>
                            <p class="font-medium">Project Started</p>
                            <p class="text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                            </p>
                        </div>
                    </div>

                    <div class="w-px h-8 bg-gray-300 ml-4"></div>

                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <p class="font-medium">Current Status</p>
                            <p class="text-sm text-gray-500">
                                <?php echo ucfirst($project['status']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="w-px h-8 bg-gray-300 ml-4"></div>

                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <p class="font-medium">Deadline</p>
                            <p class="text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($project['deadline'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function deleteUpdate(updateId) {
        if (confirm('Are you sure you want to delete this update?')) {
            fetch('delete_update.php?id=' + updateId, {
                method: 'POST'
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      location.reload();
                  } else {
                      alert('Error deleting update');
                  }
              });
        }
    }

    // Auto-hide success message after 3 seconds
    const successMsg = document.querySelector('.bg-green-100');
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.display = 'none';
        }, 3000);
    }
    </script>


<?php include 'loader.php'; ?>
</body>
</html>