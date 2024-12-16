<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

check_login();

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/chat/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get all users for chat
$sql = "SELECT id, username, department FROM users WHERE id != ? ORDER BY username";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$users = mysqli_stmt_get_result($stmt);

// Get chat messages
$selected_user = isset($_GET['user']) ? (int)$_GET['user'] : null;

// Handle file upload and message sending

if (isset($_POST['send_message'])) {
    $to_user = (int)$_POST['to_user'];
    $message = sanitize_input($_POST['message']);
    $file_name = null;
    $file_type = null;
    
    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($filetype, $allowed)) {
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $file_name = $new_filename;
                $file_type = $filetype;
            }
        }
    }
    
    // Insert message
    $sql = "INSERT INTO messages (from_user_id, to_user_id, message, file_name, file_type) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iisss", $_SESSION['user_id'], $to_user, $message, $file_name, $file_type);
    
    if (mysqli_stmt_execute($stmt)) {
        $message_id = mysqli_insert_id($conn);
        
        // Get the created message details for AJAX response
        $sql = "SELECT m.*, u.username, u.department 
                FROM messages m 
                JOIN users u ON m.from_user_id = u.id 
                WHERE m.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $message_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $message_data = mysqli_fetch_assoc($result);
        
        // Return JSON response for AJAX request
        if (isset($_POST['ajax'])) {
            echo json_encode([
                'success' => true,
                'message' => $message_data
            ]);
            exit;
        }
    } else {
        if (isset($_POST['ajax'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save message'
            ]);
            exit;
        }
    }
    
    header("Location: chat.php?user=" . $to_user);
    exit;
}

// Get new messages (for AJAX)
if (isset($_GET['get_messages']) && $selected_user) {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    $sql = "SELECT m.*, u.username, u.department 
            FROM messages m 
            JOIN users u ON m.from_user_id = u.id
            WHERE ((m.from_user_id = ? AND m.to_user_id = ?) 
               OR (m.from_user_id = ? AND m.to_user_id = ?))
               AND m.id > ?
            ORDER BY m.created_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiiii", $_SESSION['user_id'], $selected_user, $selected_user, $_SESSION['user_id'], $last_id);
    mysqli_stmt_execute($stmt);
    $new_messages = mysqli_stmt_get_result($stmt);
    
    $messages_array = [];
    while ($msg = mysqli_fetch_assoc($new_messages)) {
        $messages_array[] = $msg;
    }
    
    echo json_encode($messages_array);
    exit;
}

// Get initial messages when joining chat
if ($selected_user) {
    $messages = null; // Initialize messages variable
    
    try {
        $sql = "SELECT m.*, u.username, u.department 
                FROM messages m 
                JOIN users u ON m.from_user_id = u.id
                WHERE (m.from_user_id = ? AND m.to_user_id = ?) 
                   OR (m.from_user_id = ? AND m.to_user_id = ?)
                ORDER BY m.created_at ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiii", $_SESSION['user_id'], $selected_user, $selected_user, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        // Store all messages in an array
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
    } catch (Exception $e) {
        echo "Error loading messages: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Team Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .message-container {
        scroll-behavior: smooth;
    }
    .image-preview {
        max-width: 200px;
        max-height: 200px;
        object-fit: contain;
    }
    .image-preview img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

        /* Main container styles */
        .message-container {
        scroll-behavior: smooth;
        height: calc(100vh - 300px);
        max-height: 600px;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 6px;
    }

    /* Custom Scrollbar for Webkit browsers (Chrome, Safari, Edge) */
    .message-container::-webkit-scrollbar {
        width: 6px;
    }

    .message-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 8px;
    }

    .message-container::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 8px;
        transition: background-color 0.2s ease;
    }

    .message-container::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }

    /* Firefox scrollbar */
    .message-container {
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 #f1f5f9;
    }

    /* Messages spacing and animations */
    #messages {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    #messages > div {
        animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Image preview styles */
    .image-preview {
        max-width: 200px;
        max-height: 200px;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .image-preview img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        transition: transform 0.2s ease;
    }

    .image-preview img:hover {
        transform: scale(1.02);
    }

    /* Loading state */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    /* Message bubbles enhancement */
    .message-bubble {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        position: relative;
    }

    /* Users list enhancement */
    .space-y-2 > a {
        transition: background-color 0.2s ease, transform 0.2s ease;
    }

    .space-y-2 > a:hover {
        transform: translateX(4px);
    }

    /* Chat container shadow enhancement */
    .shadow-md {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                    0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    /* Input area styles */
    #messageForm {
        border-top: 1px solid #e5e7eb;
        padding: 1rem;
        background: white;
    }

    /* Make sure the chat container is a flex container */
    .flex-col {
        display: flex;
        flex-direction: column;
    }

    /* Ensure proper spacing between messages */
    .space-y-4 > * + * {
        margin-top: 1rem;
    }

    /* Smooth transitions for all interactive elements */
    button, a, input {
        transition: all 0.2s ease;
    }

    /* Message timestamp style */
    .text-xs {
        font-size: 0.75rem;
        opacity: 0.8;
    }

    /* Active user highlight */
    .bg-blue-50 {
        position: relative;
    }

    .bg-blue-50::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 3px;
        background: #3b82f6;
        border-radius: 0 4px 4px 0;
    }
</style>
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Team Chat</h1>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Users List -->
<!-- Users List -->
<div class="bg-white rounded-lg shadow-md p-4">
    <h2 class="font-bold mb-4">Team Members</h2>
    <div class="space-y-2">
        <?php 
        mysqli_data_seek($users, 0); 
        while ($user = mysqli_fetch_assoc($users)): 
            $messages_from = 0;
            $is_active = $selected_user == $user['id'];

            // Only get message count if not currently selected
            if (!$is_active) {
                $sql = "SELECT COUNT(*) as count FROM messages WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $user['id'], $_SESSION['user_id']);
                mysqli_stmt_execute($stmt);
                $messages_from = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];
            } else {
                // Update messages to read when user is selected
                $sql = "UPDATE messages SET is_read = 1 
                        WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $user['id'], $_SESSION['user_id']);
                mysqli_stmt_execute($stmt);
            }
            
            // Get latest message
            $sql = "SELECT created_at FROM messages 
                   WHERE (from_user_id = ? AND to_user_id = ?) 
                   OR (from_user_id = ? AND to_user_id = ?) 
                   ORDER BY created_at DESC LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiii", $user['id'], $_SESSION['user_id'], $_SESSION['user_id'], $user['id']);
            mysqli_stmt_execute($stmt);
            $last_message = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        ?>
            <a href="?user=<?php echo $user['id']; ?>" 
               data-user-id="<?php echo $user['id']; ?>"
               class="flex items-start p-3 rounded-lg hover:bg-gray-100 transition-colors <?php echo $is_active ? 'bg-blue-50' : ''; ?>">
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <span class="font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                        <?php if ($messages_from > 0 && !$is_active): ?>
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full message-count"
                              data-user-id="<?php echo $user['id']; ?>">
                            <?php echo $messages_from; ?> messages
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-sm text-gray-500"><?php echo ucfirst($user['department']); ?></span>
                        <?php if ($last_message): ?>
                        <span class="text-xs text-gray-400">
                            <?php echo date('M d', strtotime($last_message['created_at'])); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to chat links
    const chatLinks = document.querySelectorAll('a[data-user-id]');
    chatLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const userId = this.getAttribute('data-user-id');
            const messageCount = this.querySelector('.message-count');
            if (messageCount) {
                messageCount.remove();
            }
        });
    });
});

// Handle WebSocket message updates
ws.onmessage = (event) => {
    const message = JSON.parse(event.data);
    handleNewMessage(message);
    
    // Update message count for the sender if not in active chat
    if (message.from_user_id != <?php echo $selected_user ?? 0; ?>) {
        const userLink = document.querySelector(`a[data-user-id="${message.from_user_id}"]`);
        if (userLink) {
            let messageCount = userLink.querySelector('.message-count');
            if (!messageCount) {
                messageCount = document.createElement('span');
                messageCount.className = 'text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full message-count';
                const messageContainer = userLink.querySelector('.flex.items-center.justify-between');
                messageContainer.appendChild(messageCount);
            }
            const currentCount = parseInt(messageCount.textContent) || 0;
            messageCount.textContent = `${currentCount + 1} messages`;
        }
    }
};
</script>
<style>
    /* Add to your existing styles */
    .space-y-2 > a {
        border-left: 3px solid transparent;
    }

    .space-y-2 > a.bg-blue-50 {
        border-left-color: #3b82f6;
    }

    .space-y-2 > a:hover {
        border-left-color: #93c5fd;
    }

    /* Optional: Add pulse animation for unread messages */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>
    <!-- Chat Area -->
    <div class="col-span-3">
        <?php if ($selected_user): ?>
            <div class="bg-white rounded-lg shadow-md h-[600px] flex flex-col">
                <!-- Chat Messages -->
                <div class="flex-1 p-4 overflow-y-auto message-container" id="messageContainer">
                    <div class="space-y-4" id="messages">
                        <?php if ($selected_user && !empty($messages)): ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="flex <?php echo $message['from_user_id'] == $_SESSION['user_id'] ? 'justify-end' : 'justify-start'; ?>"
                                     data-message-id="<?php echo $message['id']; ?>">
                                    <div class="max-w-[70%] <?php echo $message['from_user_id'] == $_SESSION['user_id'] ? 'bg-blue-500 text-white' : 'bg-gray-100'; ?> rounded-lg p-3">
                                        <?php if ($message['file_name']): ?>
                                            <div class="mb-2">
                                                <img src="<?php echo $upload_dir . $message['file_name']; ?>" 
                                                     alt="Shared image" 
                                                     class="image-preview mb-2 rounded cursor-pointer"
                                                     onclick="window.open(this.src)">
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($message['message']): ?>
                                            <p class="break-words"><?php echo htmlspecialchars($message['message']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-xs mt-1 <?php echo $message['from_user_id'] == $_SESSION['user_id'] ? 'text-blue-100' : 'text-gray-500'; ?>">
                                            <?php echo date('M d, g:i a', strtotime($message['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="border-t p-4">
                    <form id="messageForm" method="POST" enctype="multipart/form-data" class="space-y-2">
                        <input type="hidden" name="to_user" value="<?php echo $selected_user; ?>">
                        <div class="flex space-x-2">
                            <input type="text" name="message" required
                                   class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                   placeholder="Type your message...">
                            <label class="cursor-pointer bg-gray-100 px-4 py-2 rounded-lg hover:bg-gray-200">
                                <i class="fas fa-image"></i>
                                <input type="file" name="image" accept="image/*" class="hidden" id="imageInput">
                            </label>
                            <button type="submit" name="send_message"
                                    class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                                Send
                            </button>
                        </div>
                        <div id="imagePreview" class="hidden mt-2">
                            <img src="" alt="Preview" class="max-h-32 rounded">
                            <button type="button" class="text-red-500 text-sm mt-1" onclick="removeImage()">
                                Remove Image
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center text-gray-500">
                Select a team member to start chatting
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Add to your existing styles */
    .space-y-2 > a {
        border-left: 3px solid transparent;
    }

    .space-y-2 > a.bg-blue-50 {
        border-left-color: #3b82f6;
    }

    .space-y-2 > a:hover {
        border-left-color: #93c5fd;
    }
</style>
    <script>
    // DOM Elements
    const messageContainer = document.getElementById('messageContainer');
    const messages = document.getElementById('messages');
    const messageForm = document.getElementById('messageForm');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const messageInput = messageForm.querySelector('input[name="message"]');
    let ws;

// Get initial last message ID
let lastMessageId = 0;
const messageElements = document.querySelectorAll('[data-message-id]');
if (messageElements.length > 0) {
    const lastElement = messageElements[messageElements.length - 1];
    if (lastElement) {
        const id = parseInt(lastElement.getAttribute('data-message-id'));
        if (!isNaN(id)) {
            lastMessageId = id;
        }
    }
}
    // Initialize WebSocket connection
    function initWebSocket() {
        ws = new WebSocket('ws://localhost:8080');

        ws.onopen = () => {
            console.log('Connected to chat server');
            // Send user information on connection
            ws.send(JSON.stringify({
                type: 'init',
                userId: <?php echo $_SESSION['user_id']; ?>,
                username: '<?php echo htmlspecialchars($_SESSION['username']); ?>'
            }));
        };

        ws.onmessage = (event) => {
            const message = JSON.parse(event.data);
            handleNewMessage(message);
        };

        ws.onclose = () => {
            console.log('Disconnected from chat server');
            setTimeout(initWebSocket, 3000); // Reconnect after 3 seconds
        };

        ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }

    // Handle new messages
    function handleNewMessage(message) {
        if (document.querySelector(`[data-message-id="${message.id}"]`)) {
            return; // Skip if message already exists
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `flex ${message.from_user_id == <?php echo $_SESSION['user_id']; ?> ? 'justify-end' : 'justify-start'}`;
        messageDiv.setAttribute('data-message-id', message.id);

        let content = `
            <div class="max-w-[70%] ${message.from_user_id == <?php echo $_SESSION['user_id']; ?> ? 'bg-blue-500 text-white' : 'bg-gray-100'} rounded-lg p-3">
        `;

        if (message.file_name) {
            content += `
                <div class="mb-2">
                    <img src="uploads/chat/${message.file_name}" 
                         alt="Shared image" 
                         class="image-preview rounded cursor-pointer max-w-full"
                         onclick="window.open(this.src)"
                         onerror="this.onerror=null; this.src='placeholder.jpg';">
                </div>
            `;
        }

        content += `
                ${message.message ? `<p class="break-words">${escapeHtml(message.message)}</p>` : ''}
                <p class="text-xs mt-1 ${message.from_user_id == <?php echo $_SESSION['user_id']; ?> ? 'text-blue-100' : 'text-gray-500'}">
                    ${formatDate(message.created_at)}
                </p>
            </div>
        `;

        messageDiv.innerHTML = content;
        messages.appendChild(messageDiv);
        scrollToBottom();
    }

    // Form submission handler
    messageForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        const toUser = this.querySelector('input[name="to_user"]').value;
        
        if (!message && !imageInput.files.length) return;

        if (ws.readyState === WebSocket.OPEN) {
            setLoadingState(true);
            
            try {
                let fileName = null;
                
                // Handle image upload first if there's an image
                if (imageInput.files.length > 0) {
                    const formData = new FormData();
                    formData.append('image', imageInput.files[0]);
                    
                    const response = await fetch('upload_image.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            fileName = result.file_name;
                        } else {
                            throw new Error('Failed to upload image');
                        }
                    }
                }

                // Send message with or without image
                const messageData = {
                    from_user_id: <?php echo $_SESSION['user_id']; ?>,
                    to_user: toUser,
                    message: message,
                    file_name: fileName,
                    created_at: new Date().toISOString()
                };

                ws.send(JSON.stringify(messageData));
                
                // Clear form
                messageInput.value = '';
                if (fileName) {
                    removeImage();
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error sending message. Please try again.');
            } finally {
                setLoadingState(false);
            }
        }
    });

    // Image handling
    imageInput.addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            // Validate file size (5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum size is 5MB.');
                this.value = '';
                return;
            }

            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file type. Please use JPG, PNG or GIF.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = imagePreview.querySelector('img');
                img.src = e.target.result;
                img.onload = function() {
                    imagePreview.classList.remove('hidden');
                }
            }
            reader.readAsDataURL(file);
        }
    });

    // Utility functions
    function removeImage() {
        imageInput.value = '';
        imagePreview.classList.add('hidden');
        imagePreview.querySelector('img').src = '';
    }

    function scrollToBottom() {
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const today = now.toDateString() === date.toDateString();
        
        const options = {
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        };

        if (!today) {
            options.month = 'short';
            options.day = 'numeric';
        }

        return date.toLocaleString('en-US', options);
    }

    function setLoadingState(loading) {
        const submitButton = messageForm.querySelector('button[type="submit"]');
        submitButton.disabled = loading;
        submitButton.innerHTML = loading ? 
            '<i class="fas fa-spinner fa-spin"></i> Sending...' : 
            'Send';
    }

    // Handle enter key
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim() || imageInput.files.length) {
                messageForm.dispatchEvent(new Event('submit'));
            }
        }
    });

    // Handle page visibility
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            if (ws.readyState !== WebSocket.OPEN) {
                initWebSocket();
            }
        }
    });

    // Initialize
    initWebSocket();
    scrollToBottom();

    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (ws) {
            ws.close();
        }
    });
</script>







<?php include 'loader.php'; ?>
</body>
</html>