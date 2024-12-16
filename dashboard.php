<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
check_login();

// Get user's department
$user_id = $_SESSION['user_id'];
$sql = "SELECT department FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
$department = $user['department'];

// Get unread notifications count
$sql = "SELECT COUNT(*) as count FROM notifications WHERE to_user_id = ? AND is_read = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notification_count = mysqli_fetch_assoc($result)['count'];

// Get active projects for user's department
$sql = "SELECT p.* FROM projects p 
        JOIN project_departments pd ON p.id = pd.project_id 
        WHERE pd.department = ? ORDER BY p.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $department);
mysqli_stmt_execute($stmt);
$projects = mysqli_stmt_get_result($stmt);





// Get unread messages count
$sql = "SELECT COUNT(*) as count FROM messages WHERE to_user_id = ? AND is_read = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$message_count = mysqli_fetch_assoc($result)['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-800">Dashboard</h1>
                    <span class="ml-4 px-3 py-1 bg-blue-100 text-blue-800 rounded-full">
                        <?php echo htmlspecialchars($department); ?>
                    </span>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <a href="notifications.php" class="relative">
                        <i class="fas fa-bell text-gray-600 text-xl"></i>
                        <?php if($notification_count > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                            <?php echo $notification_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <!-- Messages -->
<!-- Messages -->
<a href="chat.php" class="relative text-gray-600">
    <i class="fas fa-comments text-xl"></i>
    <?php if($message_count > 0): ?>
    <span class="absolute -top-2 -right-2 bg-blue-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
        <?php echo $message_count; ?>
    </span>
    <?php endif; ?>
</a>
                    <!-- User Menu -->
                    <div class="relative group">
                        <button class="flex items-center space-x-2">
                            <img src="<?php echo get_avatar($_SESSION['user_id']); ?>" 
                                 class="w-8 h-8 rounded-full">
                            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </button>
                        <div class="absolute right-0 w-48 py-2 mt-2 bg-white rounded-lg shadow-xl hidden group-hover:block">
                            <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Profile</a>
                            <a href="logout.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Admin Only Section -->
        <?php if(is_admin()): ?>
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold mb-4">Admin Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="manage_users.php" 
                       class="p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-users mb-2 text-blue-500"></i>
                        <h3 class="font-semibold">Manage Users</h3>
                        <p class="text-sm text-gray-600">Add or manage team members</p>
                    </a>
                    <a href="manage_projects.php" 
                       class="p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-project-diagram mb-2 text-green-500"></i>
                        <h3 class="font-semibold">Manage Projects</h3>
                        <p class="text-sm text-gray-600">Create and manage projects</p>
                    </a>
                    <a href="manage_payments.php" 
                       class="p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                        <i class="fas fa-money-bill-wave mb-2 text-purple-500"></i>
                        <h3 class="font-semibold">Payment Records</h3>
                        <p class="text-sm text-gray-600">Track project payments</p>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>


        
        <!-- Projects Section -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Active Projects</h2>
                    <?php if(is_admin()): ?>
                    <a href="manage_projects.php" 
                       class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        Create Project
                    </a>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php while($project = mysqli_fetch_assoc($projects)): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="font-semibold"><?php echo htmlspecialchars($project['name']); ?></h3>
                            <span class="px-2 py-1 bg-<?php echo get_status_color($project['status']); ?>-100 
                                       text-<?php echo get_status_color($project['status']); ?>-800 rounded-full text-sm">
                                <?php echo htmlspecialchars($project['status']); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            <?php echo htmlspecialchars($project['description']); ?>
                        </p>
                        <div class="flex justify-between items-center">
                            <a href="project_details.php?id=<?php echo $project['id']; ?>" 
                               class="text-blue-500 hover:text-blue-700">View Details</a>
                            <span class="text-sm text-gray-500">
                                Due: <?php echo date('M d, Y', strtotime($project['deadline'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

<!-- Add this section to your dashboard -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-tasks text-blue-500"></i>
                    My Tasks
                </h2>
                <p class="text-gray-600 text-sm">Today's focus</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="todo_list.php" 
                   class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span>New Task</span>
                </a>

            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Today's Tasks -->
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-blue-600">Today</p>
                        <p class="text-2xl font-bold text-blue-700">
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM todos WHERE user_id = ? AND due_date = CURDATE()";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                            mysqli_stmt_execute($stmt);
                            echo mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];
                            ?>
                        </p>
                    </div>
                    <i class="fas fa-calendar-day text-blue-500"></i>
                </div>
            </div>

            <!-- Upcoming Tasks -->
            <div class="bg-purple-50 rounded-lg p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-purple-600">Upcoming</p>
                        <p class="text-2xl font-bold text-purple-700">
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM todos WHERE user_id = ? AND due_date > CURDATE()";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                            mysqli_stmt_execute($stmt);
                            echo mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];
                            ?>
                        </p>
                    </div>
                    <i class="fas fa-calendar-alt text-purple-500"></i>
                </div>
            </div>

            <!-- Completed Tasks -->
            <div class="bg-green-50 rounded-lg p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-green-600">Completed</p>
                        <p class="text-2xl font-bold text-green-700">
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM todos WHERE user_id = ? AND status = 'completed'";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                            mysqli_stmt_execute($stmt);
                            echo mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];
                            ?>
                        </p>
                    </div>
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
            </div>

            <!-- Overdue Tasks -->
            <div class="bg-red-50 rounded-lg p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-red-600">Overdue</p>
                        <p class="text-2xl font-bold text-red-700">
                            <?php
                            $sql = "SELECT COUNT(*) as count FROM todos WHERE user_id = ? AND due_date < CURDATE() AND status != 'completed'";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                            mysqli_stmt_execute($stmt);
                            echo mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];
                            ?>
                        </p>
                    </div>
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                </div>
            </div>
        </div>

        <!-- Today's Tasks Section -->
        <div>
            <h3 class="font-semibold text-gray-700 mb-4">Today's Tasks</h3>
            <?php
            $sql = "SELECT * FROM todos WHERE user_id = ? AND due_date = CURDATE() ORDER BY priority DESC";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 0):
            ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                    <p class="text-gray-500 mb-2">All caught up! No tasks for today.</p>
                    <a href="todo_list.php" 
                       class="text-blue-500 hover:text-blue-700 inline-flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        <span>Add New Task</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php while ($task = mysqli_fetch_assoc($result)): ?>
                        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <input type="checkbox" 
                                   class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   onchange="updateTaskStatus(<?php echo $task['id']; ?>, this.checked)">
                            <span class="flex-1"><?php echo htmlspecialchars($task['title']); ?></span>
                            <?php if ($task['priority'] === 'high' || $task['priority'] === 'urgent'): ?>
                                <span class="px-2 py-1 rounded-full text-xs <?php echo $task['priority'] === 'urgent' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'; ?>">
                                    <?php echo ucfirst($task['priority']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateTaskStatus(taskId, completed) {
    fetch('todo_list.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `update_status=1&todo_id=${taskId}&status=${completed ? 'completed' : 'pending'}`
    }).then(() => window.location.reload());
}
</script>

<!-- Add this JavaScript at the end of your dashboard file -->
<script>
    function quickUpdateStatus(todoId, completed) {
        const formData = new FormData();
        formData.append('update_status', 1);
        formData.append('todo_id', todoId);
        formData.append('status', completed ? 'completed' : 'pending');

        fetch('todo_list.php', {
            method: 'POST',
            body: formData
        }).then(() => {
            window.location.reload();
        });
    }

    function quickDeleteTodo(todoId) {
        if (confirm('Are you sure you want to delete this task?')) {
            const formData = new FormData();
            formData.append('delete_todo', 1);
            formData.append('todo_id', todoId);

            fetch('todo_list.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                window.location.reload();
            });
        }
    }
</script>
        
        <!-- Team Chat Section -->
  <!-- Team Chat Section -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <div class="col-span-2">
      <!-- Replace your existing chat form with this updated version -->
<div class="chat-box bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-xl font-bold mb-4 flex items-center">
        <i class="fas fa-comments text-blue-500 mr-2"></i>
        General Chat
    </h2>
    <div class="chat-messages-container">
        <div class="chat-container" id="chat-messages">
            <!-- Chat messages loading here -->
        </div>
    </div>

    <!-- Updated form design -->
    <form id="chat-form" class="mt-4" enctype="multipart/form-data">
        <div id="image-preview" class="hidden mb-3">
            <div class="relative inline-block">
                <img src="" alt="Preview" class="max-h-32 rounded-lg border border-gray-200">
                <button type="button" onclick="removeImage()" 
                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1.5 hover:bg-red-600 transition-colors shadow-lg">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <input type="text" id="message" 
                   class="flex-1 px-4 py-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Type your message...">
            
            <!-- Updated file upload button -->
            <div class="flex-shrink-0">
                <label for="file-input" class="cursor-pointer flex items-center justify-center w-12 h-12 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">
                    <i class="fas fa-image text-gray-600 text-lg"></i>
                    <input type="file" id="file-input" class="hidden" accept="image/*">
                </label>
            </div>
            
            <button type="submit" class="flex-shrink-0 px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center gap-2">
                <span>Send</span>
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </form>
</div>

<style>
/* Add these styles to your existing CSS */
#chat-form {
    position: relative;
    margin-top: 1rem;
}

#image-preview {
    background: #f8fafc;
    padding: 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

#image-preview img {
    max-width: 100%;
    height: auto;
    max-height: 150px;
    object-fit: contain;
}

.file-upload-btn {
    position: relative;
    overflow: hidden;
}

.file-upload-btn input[type=file] {
    position: absolute;
    top: 0;
    right: 0;
    min-width: 100%;
    min-height: 100%;
    opacity: 0;
    cursor: pointer;
}

/* Animation for preview */
#image-preview.hidden {
    display: none;
}

#image-preview {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
        </div>


        
    <!-- Team Members Sidebar -->
    <div class="h-[800px] bg-white rounded-xl shadow-sm p-6 overflow-hidden">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-users text-green-500 mr-2"></i>
            Your peers        </h2>
        <div class="overflow-y-auto h-full pr-2" style="scrollbar-width: thin; scrollbar-color: #94a3b8 #e2e8f0;">
            <div class="space-y-3">
            <div class="space-y-4">
                    <?php
                    $sql = "SELECT u.*, 
                           (SELECT MAX(timestamp) FROM user_activity WHERE user_id = u.id) as last_active 
                           FROM users u 
                           WHERE u.department = ? 
                           ORDER BY last_active DESC";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "s", $department);
                    mysqli_stmt_execute($stmt);
                    $team_members = mysqli_stmt_get_result($stmt);
                    
                    while($member = mysqli_fetch_assoc($team_members)):
                        $is_online = (time() - strtotime($member['last_active'])) < 300; // 5 minutes
                    ?>
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <img src="<?php echo get_avatar($member['id']); ?>" 
                                 class="w-10 h-10 rounded-full">
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full 
                                       <?php echo $is_online ? 'bg-green-500' : 'bg-gray-300'; ?>">
                            </span>
                        </div>
                        <div>
                            <p class="font-medium">
                                <?php echo htmlspecialchars($member['username']); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <?php echo $is_online ? 'Online' : 'Last seen ' . time_ago($member['last_active']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>            </div>
        </div>
    </div>
</div>




<!-- Add this before the closing div of your main content section in dashboard.php -->
<?php if(is_admin()): ?>
<div class="fixed bottom-6 right-6 flex flex-col gap-4">
    <!-- Analytics Button -->
    <a href="analytics.php" 
       class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-xl hover:from-purple-600 hover:to-indigo-700 transition-all duration-200 shadow-lg hover:shadow-xl group">
        <i class="fas fa-chart-line fa-spin-hover text-xl"></i>
        <span class="font-medium">Analytics</span>
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
        </span>
    </a>

    <!-- Chat Management Button -->
    <a href="chat_management.php" 
       class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-blue-600 text-white rounded-xl hover:from-indigo-600 hover:to-blue-700 transition-all duration-200 shadow-lg hover:shadow-xl group">
        <i class="fas fa-cog fa-spin-hover text-xl"></i>
        <span class="font-medium">Chat Management</span>
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
        </span>
    </a>

    
</div>

<style>
.fa-spin-hover {
    transition: transform 0.3s ease;
}

.group:hover .fa-spin-hover {
    transform: rotate(180deg);
}

@keyframes gradient-shift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.fixed.bottom-6.right-6 a {
    background-size: 200% 200%;
    animation: gradient-shift 5s ease infinite;
}

.fixed.bottom-6.right-6 a:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

/* Add stagger animation for buttons */
.fixed.bottom-6.right-6 a:nth-child(1) {
    animation-delay: 0.1s;
}

.fixed.bottom-6.right-6 a:nth-child(2) {
    animation-delay: 0.2s;
}
</style>
<?php endif; ?>


<style>
    /* Chat Box Styles */
    .chat-box {
        max-height: 800px;
        display: flex;
        flex-direction: column;
    }

    .chat-messages-container {
        position: relative;
        flex: 1;
        min-height: 0;
    }

    .chat-container {
        height: 600px;
        max-height: 600px;
        overflow-y: auto;
        padding: 1rem;
        background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
    }

    .chat-input-form {
        display: flex;
        gap: 0.5rem;
        margin-top: auto;
        flex-shrink: 0;
    }

    .image-preview {
        margin-top: 0.75rem;
    }

    /* Team Members Box */
    .team-members-box {
        max-height: 800px;
        display: flex;
        flex-direction: column;
    }

    .team-members-container {
        flex: 1;
        overflow-y: auto;
        padding-right: 0.5rem;
    }

    /* Scrollbar Styles */
    .chat-container::-webkit-scrollbar,
    .team-members-container::-webkit-scrollbar {
        width: 5px;
    }

    .chat-container::-webkit-scrollbar-track,
    .team-members-container::-webkit-scrollbar-track {
        background: #e2e8f0;
        border-radius: 10px;
    }

    .chat-container::-webkit-scrollbar-thumb,
    .team-members-container::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 10px;
        transition: background-color 0.2s ease;
    }

    .chat-container::-webkit-scrollbar-thumb:hover,
    .team-members-container::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }

    /* Firefox Scrollbar */
    .chat-container,
    .team-members-container {
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #e2e8f0;
    }

    /* Message hover effect */
    .chat-container:hover::-webkit-scrollbar-thumb {
        background: #64748b;
    }

    /* Shadow effect */
    .shadow-sm {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 
                    0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* Input focus effect */
    #message:focus {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }

    /* Smooth transitions */
    .transition-all {
        transition: all 0.2s ease;
    }

    /* Message container background */
    #chat-messages {
        background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
    }

    /* Ensure content doesn't overflow */
    img, video, iframe {
        max-width: 100%;
        height: auto;
    }
</style>

    
    <!-- JavaScript for real-time features -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Update chat messages every 5 seconds
        function updateChat() {
            $.get('get_messages.php', function(messages) {
                $('#chat-messages').html(messages);
            });
        }
        setInterval(updateChat, 5000);
        updateChat();

        // Handle chat form submission
        $('#chat-form').on('submit', function(e) {
            e.preventDefault();
            const message = $('#message').val();
            if(message.trim()) {
                $.post('send_message.php', {message: message}, function() {
                    $('#message').val('');
                    updateChat();
                });
            }
        });

        // Handle file attachment
        $('#attach-file').click(function() {
            // Implement file upload functionality
        });

        // Update user activity status
        function updateActivity() {
            $.post('update_activity.php');
        }
        setInterval(updateActivity, 60000); // Every minute
        updateActivity();
    </script>
    <!-- Replace the JavaScript section with this updated version -->
<script>
    const chatForm = document.getElementById('chat-form');
    const messageInput = document.getElementById('message');
    const fileInput = document.getElementById('file-input');
    const imagePreview = document.getElementById('image-preview');
    const messageContainer = document.getElementById('chat-messages');
    let lastScrollTop = 0;

    // Function to check if user is near bottom of chat
    function isNearBottom() {
        const tolerance = 100;
        return (messageContainer.scrollHeight - messageContainer.scrollTop - messageContainer.clientHeight) <= tolerance;
    }

    // Update chat messages
    function updateChat() {
        const wasNearBottom = isNearBottom();
        $.get('get_general_messages.php', function(html) {
            $('#chat-messages').html(html);
            if (wasNearBottom) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        });
    }

    // Handle file selection
    fileInput.addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum size is 5MB.');
                this.value = '';
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                alert('Invalid file type. Please use JPG, PNG or GIF.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = imagePreview.querySelector('img') || new Image();
                img.src = e.target.result;
                if (!imagePreview.querySelector('img')) {
                    imagePreview.querySelector('.relative').appendChild(img);
                }
                imagePreview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    });

    // Remove image preview
    function removeImage() {
        fileInput.value = '';
        imagePreview.classList.add('hidden');
        const img = imagePreview.querySelector('img');
        if (img) img.src = '';
    }

    // Handle form submission
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        const file = fileInput.files[0];
        
        if (!message && !file) return;

        try {
            const formData = new FormData();
            if (message) formData.append('message', message);
            if (file) formData.append('file', file);

            const response = await fetch('send_general_message.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                messageInput.value = '';
                removeImage();
                updateChat();
                messageContainer.scrollTop = messageContainer.scrollHeight;
            } else {
                throw new Error(result.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Failed to send message. Please try again.');
        }
    });

    // Handle enter key press
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const message = this.value.trim();
            const file = fileInput.files[0];
            if (message || file) {
                chatForm.dispatchEvent(new Event('submit'));
            }
        }
    });

    // Initialize chat updates
    updateChat();
    setInterval(updateChat, 3000);

    // Update user activity status
    function updateActivity() {
        fetch('update_activity.php', { method: 'POST' });
    }
    setInterval(updateActivity, 60000);
    updateActivity();
</script>
            







<?php include 'loader.php'; ?>

</body>
</html>