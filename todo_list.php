<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

check_login();
$user_id = $_SESSION['user_id'];

// Create todos table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS todos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'archived') DEFAULT 'pending',
    due_date DATE,
    reminder_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    color_code VARCHAR(7) DEFAULT '#3B82F6',
    estimated_time INT, /* in minutes */
    actual_time INT, /* in minutes */
    tags TEXT,
    parent_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES todos(id) ON DELETE SET NULL
)";
mysqli_query($conn, $create_table);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_todo'])) {
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        $category = sanitize_input($_POST['category']);
        $priority = sanitize_input($_POST['priority']);
        $due_date = sanitize_input($_POST['due_date']);
        $reminder_date = !empty($_POST['reminder_date']) ? sanitize_input($_POST['reminder_date']) : null;
        $color_code = sanitize_input($_POST['color_code']);
        $estimated_time = !empty($_POST['estimated_time']) ? (int)$_POST['estimated_time'] : null;
        $tags = !empty($_POST['tags']) ? sanitize_input($_POST['tags']) : null;

        $sql = "INSERT INTO todos (user_id, title, description, category, priority, due_date, reminder_date, color_code, estimated_time, tags) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssssssss", $user_id, $title, $description, $category, $priority, $due_date, $reminder_date, $color_code, $estimated_time, $tags);
        mysqli_stmt_execute($stmt);
    }

    if (isset($_POST['update_status'])) {
        $todo_id = (int)$_POST['todo_id'];
        $status = sanitize_input($_POST['status']);
        $completed_at = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        
        $sql = "UPDATE todos SET status = ?, completed_at = ? WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $status, $completed_at, $todo_id, $user_id);
        mysqli_stmt_execute($stmt);
    }

    if (isset($_POST['delete_todo'])) {
        $todo_id = (int)$_POST['todo_id'];
        $sql = "DELETE FROM todos WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $todo_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
}

// Get user's todos
$todos = [];
$sql = "SELECT * FROM todos WHERE user_id = ? ORDER BY 
        CASE 
            WHEN status = 'in_progress' THEN 1
            WHEN status = 'pending' THEN 2
            WHEN status = 'completed' THEN 3
            ELSE 4
        END,
        CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        due_date ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $todos[] = $row;
}

// Get statistics
$stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'overdue' => 0
];

foreach ($todos as $todo) {
    $stats['total']++;
    $stats[$todo['status']]++;
    
    if ($todo['status'] !== 'completed' && 
        $todo['due_date'] && 
        strtotime($todo['due_date']) < strtotime('today')) {
        $stats['overdue']++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Todo List</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .todo-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .todo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .priority-indicator {
            width: 4px;
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
            border-radius: 4px 0 0 4px;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .urgent {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold">My Todo List</h1>
                <p class="text-gray-600">Organize your tasks and boost productivity</p>
            </div>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700 flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Tasks</p>
                        <p class="text-2xl font-bold"><?php echo $stats['total']; ?></p>
                    </div>
                    <i class="fas fa-tasks text-blue-500 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">In Progress</p>
                        <p class="text-2xl font-bold"><?php echo $stats['in_progress']; ?></p>
                    </div>
                    <i class="fas fa-spinner text-yellow-500 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Completed</p>
                        <p class="text-2xl font-bold"><?php echo $stats['completed']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending</p>
                        <p class="text-2xl font-bold"><?php echo $stats['pending']; ?></p>
                    </div>
                    <i class="fas fa-clock text-purple-500 text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Overdue</p>
                        <p class="text-2xl font-bold text-red-500"><?php echo $stats['overdue']; ?></p>
                    </div>
                    <i class="fas fa-exclamation-circle text-red-500 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Add Todo Form -->
            <div class="md:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-4">Add New Task</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Title</label>
                            <input type="text" name="title" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Category</label>
                            <select name="category" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="work">Work</option>
                                <option value="personal">Personal</option>
                                <option value="shopping">Shopping</option>
                                <option value="health">Health</option>
                                <option value="study">Study</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Priority</label>
                            <select name="priority" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Due Date</label>
                            <input type="date" name="due_date" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Reminder (Optional)</label>
                            <input type="datetime-local" name="reminder_date"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Color</label>
                            <input type="color" name="color_code" value="#3B82F6"
                                   class="w-full h-10 rounded-lg">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Estimated Time (minutes)</label>
                            <input type="number" name="estimated_time" min="0"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Tags (comma separated)</label>
                            <input type="text" name="tags" placeholder="work, important, meeting"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <button type="submit" name="add_todo"
                                class="w-full bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                            Add Task
                        </button>
                    </form>
                </div>
            </div>

            <!-- Todo List -->
            <div class="md:col-span-3">
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-bold">My Tasks</h2>
            <div class="flex gap-2">
                <div class="relative">
                    <select id="filterStatus" 
                            class="appearance-none px-4 py-2 pr-8 bg-gray-50 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:bg-gray-100">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400"></i>
                </div>
                <div class="relative">
                    <select id="filterPriority" 
                            class="appearance-none px-4 py-2 pr-8 bg-gray-50 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:bg-gray-100">
                        <option value="all">All Priorities</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400"></i>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">View:</span>
            <button onclick="setView('grid')" id="gridViewBtn" class="p-2 rounded-lg hover:bg-gray-100">
                <i class="fas fa-grid-2 text-gray-600"></i>
            </button>
            <button onclick="setView('list')" id="listViewBtn" class="p-2 rounded-lg hover:bg-gray-100">
                <i class="fas fa-list text-gray-600"></i>
            </button>
        </div>
    </div>
</div>

<!-- Todo Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="todoList">
    <?php foreach ($todos as $todo): ?>
    <div class="todo-card relative bg-white rounded-lg border shadow-sm overflow-hidden" 
         data-status="<?php echo $todo['status']; ?>"
         data-priority="<?php echo $todo['priority']; ?>"
         data-category="<?php echo $todo['category']; ?>">
        <!-- Priority Indicator -->
        <div class="priority-indicator" style="background-color: <?php echo $todo['color_code']; ?>"></div>
        
        <!-- Card Content -->
        <div class="p-4 pl-6">
            <!-- Header -->
            <div class="flex justify-between items-start mb-2">
                <h3 class="font-semibold text-lg pr-8"><?php echo htmlspecialchars($todo['title']); ?></h3>
                <div class="flex gap-2">
                    <!-- <button onclick="editTodo(<?php echo $todo['id']; ?>)" 
                            class="text-blue-500 hover:text-blue-700">
                        <i class="fas fa-edit"></i>
                    </button> -->
                    <button onclick="deleteTodo(<?php echo $todo['id']; ?>)" 
                            class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Description -->
            <?php if ($todo['description']): ?>
            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($todo['description']); ?></p>
            <?php endif; ?>

            <!-- Meta Information -->
            <div class="flex flex-wrap gap-2 mb-3">
                <!-- Category -->
                <span class="px-2 py-1 bg-gray-100 rounded-full text-sm">
                    <?php echo ucfirst($todo['category']); ?>
                </span>

                <!-- Priority -->
                <span class="px-2 py-1 rounded-full text-sm <?php
                    echo match($todo['priority']) {
                        'urgent' => 'bg-red-100 text-red-800',
                        'high' => 'bg-orange-100 text-orange-800',
                        'medium' => 'bg-yellow-100 text-yellow-800',
                        'low' => 'bg-green-100 text-green-800',
                    };
                ?>">
                    <?php echo ucfirst($todo['priority']); ?>
                </span>

                <!-- Due Date -->
                <span class="px-2 py-1 rounded-full text-sm <?php
                    echo (strtotime($todo['due_date']) < strtotime('today') && $todo['status'] !== 'completed') 
                        ? 'bg-red-100 text-red-800' 
                        : 'bg-blue-100 text-blue-800';
                ?>">
                    <i class="far fa-calendar-alt mr-1"></i>
                    <?php echo date('M d, Y', strtotime($todo['due_date'])); ?>
                </span>

                <!-- Estimated Time -->
                <?php if ($todo['estimated_time']): ?>
                <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">
                    <i class="far fa-clock mr-1"></i>
                    <?php echo $todo['estimated_time']; ?> min
                </span>
                <?php endif; ?>
            </div>

            <!-- Tags -->
            <?php if ($todo['tags']): ?>
            <div class="flex flex-wrap gap-1 mb-3">
                <?php foreach (explode(',', $todo['tags']) as $tag): ?>
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs">
                    #<?php echo trim($tag); ?>
                </span>
                <?php endforeach; ?>
            </div>

            
            <?php endif; ?>

            <!-- Status Control -->
            <div class="flex justify-between items-center mt-4">
                <select onchange="updateStatus(<?php echo $todo['id']; ?>, this.value)"
                        class="px-3 py-1 border rounded-lg text-sm">
                    <option value="pending" <?php echo $todo['status'] === 'pending' ? 'selected' : ''; ?>>
                        Pending
                    </option>
                    <option value="in_progress" <?php echo $todo['status'] === 'in_progress' ? 'selected' : ''; ?>>
                        In Progress
                    </option>
                    <option value="completed" <?php echo $todo['status'] === 'completed' ? 'selected' : ''; ?>>
                        Completed
                    </option>
                </select>

                <?php if ($todo['reminder_date']): ?>
                <span class="text-sm text-gray-500">
                    <i class="far fa-bell mr-1"></i>
                    Reminder: <?php echo date('M d, g:i A', strtotime($todo['reminder_date'])); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>
</div>
</div>
</div>



<!-- Edit Todo Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
<div class="bg-white rounded-xl p-6 w-full max-w-md">
<!-- Edit form will be injected here via JavaScript -->
</div>
</div>

<script>
// Initialize date pickers
flatpickr('input[type="date"]', {
dateFormat: 'Y-m-d',
minDate: 'today'
});

flatpickr('input[type="datetime-local"]', {
enableTime: true,
dateFormat: 'Y-m-d H:i',
minDate: 'today'
});

// Filtering functionality
const todoList = document.getElementById('todoList');
const filterStatus = document.getElementById('filterStatus');
const filterPriority = document.getElementById('filterPriority');

function applyFilters() {
const status = filterStatus.value;
const priority = filterPriority.value;

document.querySelectorAll('.todo-card').forEach(card => {
const cardStatus = card.dataset.status;
const cardPriority = card.dataset.priority;

const statusMatch = status === 'all' || cardStatus === status;
const priorityMatch = priority === 'all' || cardPriority === priority;

card.style.display = statusMatch && priorityMatch ? 'block' : 'none';
});
}

filterStatus.addEventListener('change', applyFilters);
filterPriority.addEventListener('change', applyFilters);

// Update todo status
function updateStatus(todoId, status) {
const formData = new FormData();
formData.append('update_status', 1);
formData.append('todo_id', todoId);
formData.append('status', status);

fetch(window.location.href, {
method: 'POST',
body: formData
}).then(() => {
window.location.reload();
});
}

// Delete todo
function deleteTodo(todoId) {
if (confirm('Are you sure you want to delete this task?')) {
const formData = new FormData();
formData.append('delete_todo', 1);
formData.append('todo_id', todoId);

fetch(window.location.href, {
method: 'POST',
body: formData
}).then(() => {
window.location.reload();
});
}
}

// Check for overdue tasks and reminder notifications
function checkReminders() {
const todos = document.querySelectorAll('.todo-card');
todos.forEach(todo => {
const dueDate = todo.querySelector('.due-date')?.dataset.date;
const reminder = todo.querySelector('.reminder')?.dataset.date;
const status = todo.dataset.status;

if (status !== 'completed') {
// Check for overdue
if (dueDate && new Date(dueDate) < new Date()) {
    todo.classList.add('overdue');
}

// Check for reminder
if (reminder && new Date(reminder) <= new Date()) {
    showNotification(todo.querySelector('h3').textContent);
}
}
});
}

// Browser notifications
function showNotification(title) {
if (Notification.permission === 'granted') {
new Notification('Task Reminder', {
body: title,
icon: '/path/to/icon.png'
});
} else if (Notification.permission !== 'denied') {
Notification.requestPermission().then(permission => {
if (permission === 'granted') {
    showNotification(title);
}
});
}
}

// Initialize notifications and checks
document.addEventListener('DOMContentLoaded', () => {
// Request notification permission
if ('Notification' in window) {
Notification.requestPermission();
}

// Check reminders every minute
checkReminders();
setInterval(checkReminders, 60000);
});
</script>


<?php include 'loader.php'; ?>
</body>
</html>