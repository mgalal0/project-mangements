<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in and is admin
check_login();
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

$success_msg = $error_msg = '';
// For creating new project
if (isset($_POST['create_project'])) {
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $total_budget = sanitize_input($_POST['total_budget']);
    $deadline = sanitize_input($_POST['deadline']); // Add this line
    $departments = isset($_POST['departments']) ? $_POST['departments'] : [];
    
    mysqli_begin_transaction($conn);
    try {
        // Updated INSERT query to include deadline
        $sql = "INSERT INTO projects (name, description, total_budget, paid_amount, status, deadline) 
                VALUES (?, ?, ?, 0, 'pending', ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssds", $name, $description, $total_budget, $deadline);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating project");
        }
        
        $project_id = mysqli_insert_id($conn);

        // Insert project departments
        foreach ($departments as $department) {
            $sql = "INSERT INTO project_departments (project_id, department) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "is", $project_id, $department);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);
        $success_msg = "Project created successfully!";
        
        // Redirect to prevent form resubmission
        header("Location: manage_projects.php");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}

// For updating existing project
if (isset($_POST['update_project'])) {
    $project_id = sanitize_input($_POST['project_id']);
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $total_budget = sanitize_input($_POST['total_budget']);
    $departments = isset($_POST['departments']) ? $_POST['departments'] : [];
    
    mysqli_begin_transaction($conn);
    try {
        // Check if new budget is less than amount already paid
        $check_sql = "SELECT paid_amount FROM projects WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $project_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $current_project = mysqli_fetch_assoc($result);

        if ($total_budget < $current_project['paid_amount']) {
            throw new Exception("New budget cannot be less than amount already paid ($" . number_format($current_project['paid_amount'], 2) . ")");
        }

        // Update project
        $sql = "UPDATE projects SET name = ?, description = ?, total_budget = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssdi", $name, $description, $total_budget, $project_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating project");
        }

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
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}
// Handle project deletion
if (isset($_GET['delete'])) {
    $project_id = (int)$_GET['delete'];
    
    mysqli_begin_transaction($conn);
    try {
        // Delete project departments first (foreign key constraint)
        $sql = "DELETE FROM project_departments WHERE project_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);

        // Delete project
        $sql = "DELETE FROM projects WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);
        $success_msg = "Project deleted successfully!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Error deleting project: " . $e->getMessage();
    }
}

// Get all projects with their departments
$sql = "SELECT p.*, GROUP_CONCAT(pd.department) as departments 
        FROM projects p 
        LEFT JOIN project_departments pd ON p.id = pd.project_id 
        GROUP BY p.id 
        ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Projects</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Manage Projects</h1>
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

        <!-- Create Project Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Create New Project</h2>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Project Name</label>
                        <input type="text" name="name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <!-- Add this budget field -->
<div class="mb-4">
    <label class="block text-gray-700 mb-2">Project Budget ($)</label>
    <input type="number" name="total_budget" step="0.01" min="0"
           value="<?php echo isset($project) ? number_format($project['total_budget'], 2, '.', '') : '0.00'; ?>"
           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
</div>
                    <div>
                        <label class="block text-gray-700 mb-2">Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" required
                             class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Assigned Departments</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="departments[]" value="payments" class="form-checkbox">
                            <span>Payments</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="departments[]" value="ui_ux" class="form-checkbox">
                            <span>UI/UX</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="departments[]" value="frontend" class="form-checkbox">
                            <span>Frontend</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="departments[]" value="backend" class="form-checkbox">
                            <span>Backend</span>
                        </label>
                    </div>
                </div>
                <button type="submit" name="create_project"
                        class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                    Create Project
                </button>
            </form>
        </div>

        <!-- Projects List -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Current Projects</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left">Project Name</th>
                            <th class="px-6 py-3 text-left">Departments</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-left">Deadline</th>
                            <th class="px-6 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($project = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($project['description']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                $dept_array = explode(',', $project['departments']);
                                foreach ($dept_array as $dept): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch($dept) {
                                            case 'payments': echo 'bg-green-100 text-green-800'; break;
                                            case 'ui_ux': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'frontend': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'backend': echo 'bg-yellow-100 text-yellow-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($dept); ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    switch($project['status']) {
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($project['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo date('M d, Y', strtotime($project['deadline'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="edit_project.php?id=<?php echo $project['id']; ?>" 
                                   class="text-blue-500 hover:text-blue-700 mr-3">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?delete=<?php echo $project['id']; ?>" 
                                   class="text-red-500 hover:text-red-700"
                                   onclick="return confirm('Are you sure you want to delete this project?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
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