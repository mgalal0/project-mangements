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
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    header("Location: manage_users.php");
    exit;
}

// Handle user update
if (isset($_POST['update_user'])) {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $department = sanitize_input($_POST['department']);
    $role = sanitize_input($_POST['role']);

    if (!empty($_POST['password'])) {
        // If password is provided, update it
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = ?, email = ?, password = ?, department = ?, role = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssi", $username, $email, $password, $department, $role, $user_id);
    } else {
        // If no password provided, update other fields only
        $sql = "UPDATE users SET username = ?, email = ?, department = ?, role = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $department, $role, $user_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "User updated successfully!";
        // Refresh user data
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
    } else {
        $error_msg = "Error updating user: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Edit User</h1>
            <a href="manage_users.php" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Users
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

        <!-- Edit Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Username</label>
                        <input type="text" name="username" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                               required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                               required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                        <input type="password" name="password"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Only fill this if you want to change the password</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Department</label>
                        <select name="department" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="payments" <?php echo $user['department'] === 'payments' ? 'selected' : ''; ?>>
                                Payments
                            </option>
                            <option value="ui_ux" <?php echo $user['department'] === 'ui_ux' ? 'selected' : ''; ?>>
                                UI/UX
                            </option>
                            <option value="frontend" <?php echo $user['department'] === 'frontend' ? 'selected' : ''; ?>>
                                Frontend
                            </option>
                            <option value="backend" <?php echo $user['department'] === 'backend' ? 'selected' : ''; ?>>
                                Backend
                            </option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Role</label>
                        <select name="role" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>
                                User
                            </option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                                Admin
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-4 pt-4">
                        <button type="submit" name="update_user"
                                class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition duration-200">
                            Update User
                        </button>
                        <a href="manage_users.php" 
                           class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
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