<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

check_login();

// Get latest messages
$sql = "SELECT m.*, u.username, u.department 
        FROM general_chat_messages m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.created_at DESC 
        LIMIT 50";
$result = mysqli_query($conn, $sql);
$messages = array();

while($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

$messages = array_reverse($messages);

foreach($messages as $message) {
    $isOwn = $message['user_id'] == $_SESSION['user_id'];
    ?>
    <div class="flex <?php echo $isOwn ? 'justify-end' : 'justify-start'; ?> mb-4">
        <div class="flex items-start max-w-[70%] <?php echo $isOwn ? 'flex-row-reverse' : ''; ?>">
            <img src="<?php echo get_avatar($message['user_id']); ?>" 
                 class="w-8 h-8 rounded-full <?php echo $isOwn ? 'ml-2' : 'mr-2'; ?>">
            <div class="<?php echo $isOwn ? 'bg-blue-500 text-white' : 'bg-gray-100'; ?> rounded-lg p-3">
                <div class="flex items-center <?php echo $isOwn ? 'justify-end' : ''; ?> mb-1">
                    <span class="font-medium text-sm <?php echo $isOwn ? 'text-blue-100' : 'text-gray-600'; ?>">
                        <?php echo htmlspecialchars($message['username']); ?>
                        <span class="<?php echo $isOwn ? 'text-blue-200' : 'text-gray-500'; ?> text-xs ml-2">
                            <?php echo $message['department']; ?>
                        </span>
                    </span>
                </div>
                <?php if($message['file_name']): ?>
                    <div class="mb-2">
                        <img src="uploads/general_chat/<?php echo $message['file_name']; ?>" 
                             alt="Shared image" 
                             class="max-w-full rounded cursor-pointer hover:opacity-90"
                             onclick="window.open(this.src)"
                             style="max-height: 200px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <?php if($message['message']): ?>
                    <p class="break-words"><?php echo htmlspecialchars($message['message']); ?></p>
                <?php endif; ?>
                <p class="text-xs mt-1 <?php echo $isOwn ? 'text-blue-100' : 'text-gray-500'; ?>">
                    <?php echo date('M d, g:i a', strtotime($message['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>
    <?php
}
?>