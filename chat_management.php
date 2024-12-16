<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is admin
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

// Handle deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_general_messages'])) {
        mysqli_query($conn, "DELETE FROM general_chat_messages");
        $success_message = "All general chat messages deleted successfully";
    }
    
    if (isset($_POST['delete_private_messages'])) {
        mysqli_query($conn, "DELETE FROM messages");
        $success_message = "All private chat messages deleted successfully";
    }
    
    if (isset($_POST['delete_general_uploads'])) {
        // Delete files from directory
        array_map('unlink', glob('uploads/general_chat/*'));
        // Clear database records
        mysqli_query($conn, "UPDATE general_chat_messages SET file_name = NULL, file_type = NULL");
        $success_message = "All general chat uploads deleted successfully";
    }
    
    if (isset($_POST['delete_private_uploads'])) {
        // Delete files from directory
        array_map('unlink', glob('uploads/chat/*'));
        // Clear database records
        mysqli_query($conn, "UPDATE messages SET file_name = NULL, file_type = NULL");
        $success_message = "All private chat uploads deleted successfully";
    }
}

// Get statistics
$general_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM general_chat_messages"))['count'];
$private_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages"))['count'];
$general_uploads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM general_chat_messages WHERE file_name IS NOT NULL"))['count'];
$private_uploads = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE file_name IS NOT NULL"))['count'];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .delete-btn {
            transition: all 0.2s;
        }
        .delete-btn:hover {
            background: #ef4444;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Chat Management</h1>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>

        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo $success_message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm">
                <div class="text-3xl font-bold text-blue-500 mb-2"><?php echo $general_messages; ?></div>
                <div class="text-gray-600">General Messages</div>
            </div>
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm">
                <div class="text-3xl font-bold text-indigo-500 mb-2"><?php echo $private_messages; ?></div>
                <div class="text-gray-600">Private Messages</div>
            </div>
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm">
                <div class="text-3xl font-bold text-green-500 mb-2"><?php echo $general_uploads; ?></div>
                <div class="text-gray-600">General Uploads</div>
            </div>
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm">
                <div class="text-3xl font-bold text-purple-500 mb-2"><?php echo $private_uploads; ?></div>
                <div class="text-gray-600">Private Uploads</div>
            </div>
        </div>

        <!-- Management Sections -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- General Chat Section -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-bold mb-6 flex items-center">
                    <i class="fas fa-comments text-blue-500 mr-2"></i>
                    General Chat Management
                </h2>
                
                <div class="space-y-6">
                    <!-- Messages Management -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold mb-4">Messages</h3>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600">Total messages: <?php echo $general_messages; ?></p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete all general chat messages? This cannot be undone.');">
                                <button type="submit" name="delete_general_messages" 
                                        class="delete-btn px-4 py-2 border border-red-500 text-red-500 rounded-lg">
                                    Delete All Messages
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Uploads Management -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold mb-4">Uploads</h3>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600">Total uploads: <?php echo $general_uploads; ?></p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete all general chat uploads? This cannot be undone.');">
                                <button type="submit" name="delete_general_uploads" 
                                        class="delete-btn px-4 py-2 border border-red-500 text-red-500 rounded-lg">
                                    Delete All Uploads
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Private Chat Section -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-bold mb-6 flex items-center">
                    <i class="fas fa-user-friends text-indigo-500 mr-2"></i>
                    Private Chat Management
                </h2>
                
                <div class="space-y-6">
                    <!-- Messages Management -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold mb-4">Messages</h3>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600">Total messages: <?php echo $private_messages; ?></p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete all private chat messages? This cannot be undone.');">
                                <button type="submit" name="delete_private_messages" 
                                        class="delete-btn px-4 py-2 border border-red-500 text-red-500 rounded-lg">
                                    Delete All Messages
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Uploads Management -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold mb-4">Uploads</h3>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600">Total uploads: <?php echo $private_uploads; ?></p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete all private chat uploads? This cannot be undone.');">
                                <button type="submit" name="delete_private_uploads" 
                                        class="delete-btn px-4 py-2 border border-red-500 text-red-500 rounded-lg">
                                    Delete All Uploads
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confirmation dialog for delete actions
        document.querySelectorAll('form').forEach(form => {
            form.onsubmit = function(e) {
                const action = e.submitter.name.replace('delete_', '').replace('_', ' ');
                return confirm(`Are you sure you want to delete all ${action}? This action cannot be undone.`);
            }
        });
    </script>


<?php include 'loader.php'; ?>
</body>
</html>