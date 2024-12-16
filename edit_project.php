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

// Get project data
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
    header("Location: manage_projects.php");
    exit;
}

// Get all progress updates
$sql = "SELECT pu.*, u.username 
        FROM project_updates pu 
        JOIN users u ON pu.user_id = u.id 
        WHERE pu.project_id = ? 
        ORDER BY pu.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$updates = mysqli_stmt_get_result($stmt);

// Handle project update
if (isset($_POST['update_project'])) {
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $deadline = sanitize_input($_POST['deadline']);
    $status = sanitize_input($_POST['status']);
    $departments = isset($_POST['departments']) ? $_POST['departments'] : [];

    mysqli_begin_transaction($conn);
    try {
        // Update project
        $sql = "UPDATE projects SET name = ?, description = ?, deadline = ?, status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $name, $description, $deadline, $status, $project_id);
        mysqli_stmt_execute($stmt);

        // Update departments
        $sql = "DELETE FROM project_departments WHERE project_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);

        foreach ($departments as $department) {
            $sql = "INSERT INTO project_departments (project_id, department) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "is", $project_id, $department);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);
        $success_msg = "Project updated successfully!";

        // Refresh project data
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
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Error updating project: " . $e->getMessage();
    }
}

// Handle status update/progress note
if (isset($_POST['add_update'])) {
    $update_text = sanitize_input($_POST['update_text']);
    $sql = "INSERT INTO project_updates (project_id, user_id, update_text) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iis", $project_id, $_SESSION['user_id'], $update_text);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "Update added successfully!";
        header("Location: edit_project.php?id=" . $project_id);
        exit;
    } else {
        $error_msg = "Error adding update.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Project</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Edit Project: <?php echo htmlspecialchars($project['name']); ?></h1>
            <a href="manage_projects.php" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Projects
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
            <!-- Edit Project Form -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">Project Details</h2>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Project Name</label>
                                <input type="text" name="name" 
                                       value="<?php echo htmlspecialchars($project['name']); ?>" 
                                       required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Deadline</label>
                                <input type="date" name="deadline" 
                                       value="<?php echo date('Y-m-d', strtotime($project['deadline'])); ?>" 
                                       required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3" required
                                      class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($project['description']); ?></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Status</label>
                            <select name="status" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="pending" <?php echo $project['status'] === 'pending' ? 'selected' : ''; ?>>
                                    Pending
                                </option>
                                <option value="in_progress" <?php echo $project['status'] === 'in_progress' ? 'selected' : ''; ?>>
                                    In Progress
                                </option>
                                <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>
                                    Completed
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Assigned Departments</label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php 
                                $current_departments = explode(',', $project['departments']);
                                $all_departments = ['payments', 'ui_ux', 'frontend', 'backend'];
                                foreach ($all_departments as $dept): 
                                ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="departments[]" 
                                           value="<?php echo $dept; ?>"
                                           <?php echo in_array($dept, $current_departments) ? 'checked' : ''; ?>
                                           class="form-checkbox">
                                    <span><?php echo ucfirst(str_replace('_', '/', $dept)); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" name="update_project"
                                    class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                                Update Project
                            </button>
                        </div>
                    </form>
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
                                        By <?php echo htmlspecialchars($update['username']); ?> • 
                                        <?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?>
                                    </p>
                                </div>
                                <?php if ($_SESSION['user_id'] == $update['user_id'] || is_admin()): ?>
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