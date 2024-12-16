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

// Handle payment deletion
if (isset($_GET['delete'])) {
    $payment_id = (int)$_GET['delete'];
    
    mysqli_begin_transaction($conn);
    try {
        // Get payment details before deletion
        $get_payment = mysqli_prepare($conn, "SELECT amount, project_id FROM payments WHERE id = ?");
        mysqli_stmt_bind_param($get_payment, "i", $payment_id);
        mysqli_stmt_execute($get_payment);
        $payment_info = mysqli_stmt_get_result($get_payment)->fetch_assoc();

        if ($payment_info) {
            // Update project paid_amount
            $update_project = mysqli_prepare($conn, "UPDATE projects SET paid_amount = paid_amount - ? WHERE id = ?");
            mysqli_stmt_bind_param($update_project, "di", $payment_info['amount'], $payment_info['project_id']);
            mysqli_stmt_execute($update_project);

            // Delete payment
            $delete_payment = mysqli_prepare($conn, "DELETE FROM payments WHERE id = ?");
            mysqli_stmt_bind_param($delete_payment, "i", $payment_id);
            mysqli_stmt_execute($delete_payment);

            mysqli_commit($conn);
            $success_msg = "Payment deleted successfully!";
            
            // Redirect to prevent refresh issues
            header("Location: manage_payments.php");
            exit;
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Error deleting payment: " . $e->getMessage();
    }
}

// Handle payment addition
if (isset($_POST['add_payment'])) {
    $project_id = sanitize_input($_POST['project_id']);
    $amount = sanitize_input($_POST['amount']);
    $recipient_id = sanitize_input($_POST['recipient_id']);
    $description = sanitize_input($_POST['description']);
    $payment_date = sanitize_input($_POST['payment_date']);

    mysqli_begin_transaction($conn);
    try {
        // Check budget limit
        $check_budget = mysqli_prepare($conn, "SELECT total_budget, paid_amount FROM projects WHERE id = ?");
        mysqli_stmt_bind_param($check_budget, "i", $project_id);
        mysqli_stmt_execute($check_budget);
        $budget_info = mysqli_stmt_get_result($check_budget)->fetch_assoc();
        
        $remaining = $budget_info['total_budget'] - $budget_info['paid_amount'];
        if ($amount > $remaining) {
            throw new Exception("Payment exceeds remaining budget. Available: $" . number_format($remaining, 2));
        }

        // Insert payment
        $sql = "INSERT INTO payments (project_id, amount, recipient_id, description, payment_date) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "idiss", $project_id, $amount, $recipient_id, $description, $payment_date);
        mysqli_stmt_execute($stmt);

        // Update project paid_amount
        $update_sql = "UPDATE projects SET paid_amount = paid_amount + ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "di", $amount, $project_id);
        mysqli_stmt_execute($update_stmt);

        mysqli_commit($conn);
        $success_msg = "Payment recorded successfully!";
        
        // Redirect to prevent duplicate submissions
        header("Location: manage_payments.php");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
    }
}

// Get project list with budget information
$projects_sql = "SELECT 
                 p.id, 
                 p.name, 
                 p.total_budget,
                 p.paid_amount,
                 (p.total_budget - p.paid_amount) as remaining_budget
                 FROM projects p 
                 WHERE p.status != 'completed' 
                 ORDER BY p.name";
$projects_result = mysqli_query($conn, $projects_sql);

// Get all payments with related information
$payments_sql = "SELECT 
                p.*, 
                p.id as payment_id,
                pr.name as project_name,
                pr.total_budget,
                pr.paid_amount as total_paid,
                u.username as recipient_name,
                u.department as recipient_department
                FROM payments p 
                JOIN projects pr ON p.project_id = pr.id 
                JOIN users u ON p.recipient_id = u.id 
                ORDER BY p.payment_date DESC, p.id DESC";
$payments_result = mysqli_query($conn, $payments_sql);

// Get users for dropdown
$users_sql = "SELECT id, username, department FROM users ORDER BY username";
$users_result = mysqli_query($conn, $users_sql);

// Get project summary with budget information
$project_summary_sql = "SELECT 
                       p.id,
                       p.name,
                       p.total_budget,
                       p.paid_amount,
                       (p.total_budget - p.paid_amount) as remaining
                       FROM projects p
                       WHERE p.total_budget > 0
                       ORDER BY p.name";
$project_summary = mysqli_query($conn, $project_summary_sql);

// Calculate totals for statistics
$total_budget = 0;
$total_paid = 0;
mysqli_data_seek($project_summary, 0);
while($p = mysqli_fetch_assoc($project_summary)) {
    $total_budget += $p['total_budget'];
    $total_paid += $p['paid_amount'];
}

// Function to get department color class
function get_dept_color($department) {
    switch($department) {
        case 'payments': return 'bg-green-100 text-green-800';
        case 'ui_ux': return 'bg-blue-100 text-blue-800';
        case 'frontend': return 'bg-purple-100 text-purple-800';
        case 'backend': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold">Payment Management</h1>
        <p class="text-gray-600">Manage project payments and track budgets</p>
    </div>
    <div class="flex space-x-4">
        <!-- Export Button -->
        <a href="export_payments.php" 
           class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 flex items-center gap-2 transition-colors">
            <i class="fas fa-file-excel"></i>
            <span>Export to Excel</span>
        </a>
        <!-- Existing Back Button -->
        <a href="dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 flex items-center gap-2">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-500 text-sm">Total Projects</p>
                <p class="text-2xl font-bold"><?php echo mysqli_num_rows($project_summary); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-500 text-sm">Total Budget</p>
                <p class="text-2xl font-bold">$<?php echo number_format($total_budget, 2); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-500 text-sm">Total Paid</p>
                <p class="text-2xl font-bold">$<?php echo number_format($total_paid, 2); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-500 text-sm">Remaining Budget</p>
                <p class="text-2xl font-bold text-<?php echo ($total_budget - $total_paid) > 0 ? 'green' : 'red'; ?>-600">
                    $<?php echo number_format($total_budget - $total_paid, 2); ?>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Add Payment Form -->
            <div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Record Payment</h2>
                    <form method="POST" id="paymentForm" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Project</label>
                            <select name="project_id" required id="projectSelect"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">Select Project</option>
                                <?php while ($project = mysqli_fetch_assoc($projects_result)): 
                                    $remaining = $project['total_budget'] - $project['paid_amount'];
                                ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            data-budget="<?php echo $project['total_budget']; ?>"
                                            data-paid="<?php echo $project['paid_amount']; ?>"
                                            data-remaining="<?php echo $remaining; ?>">
                                        <?php echo htmlspecialchars($project['name']); ?> 
                                        (Budget: $<?php echo number_format($project['total_budget'], 2); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <p id="budgetInfo" class="text-sm text-gray-500 mt-1"></p>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Amount ($)</label>
                            <input type="number" name="amount" step="0.01" required id="amountInput"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <p id="remainingBudget" class="text-sm text-gray-500 mt-1"></p>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Recipient</label>
                            <select name="recipient_id" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">Select Recipient</option>
                                <?php 
                                mysqli_data_seek($users_result, 0);
                                while ($user = mysqli_fetch_assoc($users_result)): 
                                ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?> 
                                        (<?php echo ucfirst(str_replace('_', '/', $user['department'])); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Payment Date</label>
                            <input type="date" name="payment_date" required
                                   value="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3" required
                                      class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"></textarea>
                        </div>

                        <button type="submit" name="add_payment"
                                class="w-full bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                            Record Payment
                        </button>
                    </form>
                </div>
            </div>

            <!-- Payment Records -->
            <div class="md:col-span-2">
                <!-- Project Budget Progress -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">Project Payment Progress</h2>
                    <div class="space-y-4">
                        <?php
                        mysqli_data_seek($project_summary, 0);
                        while ($project = mysqli_fetch_assoc($project_summary)): 
                            $percentage = ($project['total_budget'] > 0) ? 
                                        ($project['paid_amount'] / $project['total_budget'] * 100) : 0;
                        ?>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="font-medium"><?php echo htmlspecialchars($project['name']); ?></span>
                                <span class="text-sm text-gray-600">
                                    $<?php echo number_format($project['paid_amount'], 2); ?> / 
                                    $<?php echo number_format($project['total_budget'], 2); ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" 
                                     style="width: <?php echo min(100, $percentage); ?>%"></div>
                            </div>
                            <div class="flex justify-between text-sm text-gray-500 mt-1">
                                <span><?php echo number_format($percentage, 1); ?>% Complete</span>
                                <span>Remaining: $<?php echo number_format($project['remaining'], 2); ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Payment History Table -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Payment History</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget/Paid</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($payment = mysqli_fetch_assoc($payments_result)): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($payment['project_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm">
                                            <div>Budget: $<?php echo number_format($payment['total_budget'], 2); ?></div>
                                            <div>Paid: $<?php echo number_format($payment['total_paid'], 2); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">
                                        $<?php echo number_format($payment['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php echo get_dept_color($payment['recipient_department']); ?>">
                                            <?php echo htmlspecialchars($payment['recipient_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($payment['description']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="?delete=<?php echo $payment['payment_id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this payment? This action cannot be undone.')"
                                           class="text-red-600 hover:text-red-900 flex items-center gap-1">
                                            <i class="fas fa-trash"></i>
                                            <span>Delete</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Get DOM elements
    const projectSelect = document.getElementById('projectSelect');
    const amountInput = document.getElementById('amountInput');
    const remainingBudget = document.getElementById('remainingBudget');
    const budgetInfo = document.getElementById('budgetInfo');
    const paymentForm = document.getElementById('paymentForm');

    // Auto-hide success/error messages after 3 seconds
    document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(msg => {
        setTimeout(() => {
            msg.style.display = 'none';
        }, 3000);
    });

    // Project selection handling
    projectSelect.addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        if (selectedOption.value) {
            const totalBudget = parseFloat(selectedOption.dataset.budget);
            const paidAmount = parseFloat(selectedOption.dataset.paid);
            const remaining = parseFloat(selectedOption.dataset.remaining);
            
            // Update budget information display
            budgetInfo.innerHTML = `
                <div class="space-y-1 mt-2">
                    <div class="flex justify-between">
                        <span>Total Budget:</span>
                        <span class="font-semibold">$${totalBudget.toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Already Paid:</span>
                        <span class="font-semibold">$${paidAmount.toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between font-medium ${remaining > 0 ? 'text-green-600' : 'text-red-600'}">
                        <span>Remaining:</span>
                        <span>$${remaining.toFixed(2)}</span>
                    </div>
                </div>
            `;

            // Set max amount to remaining budget
            amountInput.max = remaining;
            validateAmount();
        } else {
            budgetInfo.innerHTML = '';
            remainingBudget.textContent = '';
            amountInput.max = '';
        }
    });

    // Amount validation
    amountInput.addEventListener('input', validateAmount);
    amountInput.addEventListener('blur', function() {
        if (this.value) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });

    function validateAmount() {
        const amount = parseFloat(amountInput.value);
        const selectedOption = projectSelect.selectedOptions[0];
        
        if (selectedOption.value && amount) {
            const remaining = parseFloat(selectedOption.dataset.remaining);
            const totalBudget = parseFloat(selectedOption.dataset.budget);
            
            if (amount > remaining) {
                amountInput.setCustomValidity(`Amount exceeds remaining budget of $${remaining.toFixed(2)}`);
                remainingBudget.className = 'text-sm text-red-500 mt-1';
                remainingBudget.textContent = `Exceeds budget by $${(amount - remaining).toFixed(2)}`;
            } else if (amount <= 0) {
                amountInput.setCustomValidity('Amount must be greater than 0');
                remainingBudget.className = 'text-sm text-red-500 mt-1';
                remainingBudget.textContent = 'Amount must be greater than 0';
            } else {
                amountInput.setCustomValidity('');
                remainingBudget.className = 'text-sm text-green-500 mt-1';
                remainingBudget.textContent = `Remaining after payment: $${(remaining - amount).toFixed(2)}`;
            }
        }
    }

    // Form submission handling
    paymentForm.addEventListener('submit', function(e) {
        const amount = parseFloat(amountInput.value);
        const selectedOption = projectSelect.selectedOptions[0];
        
        if (selectedOption.value && amount) {
            const remaining = parseFloat(selectedOption.dataset.remaining);
            if (amount > remaining) {
                e.preventDefault();
                alert(`Payment amount ($${amount.toFixed(2)}) exceeds remaining budget ($${remaining.toFixed(2)})`);
            } else if (amount <= 0) {
                e.preventDefault();
                alert('Payment amount must be greater than 0');
            }
        }
    });

    // Delete confirmation handling
    document.querySelectorAll('a[href*="delete"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const amount = this.closest('tr').querySelector('td:nth-child(4)').textContent;
            if (!confirm(`Are you sure you want to delete this payment of ${amount}? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });

    // Table row hover effects
    document.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('mouseover', function() {
            this.classList.add('bg-gray-50');
        });
        row.addEventListener('mouseout', function() {
            this.classList.remove('bg-gray-50');
        });
    });

    // Add form reset functionality
    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'w-full bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 mt-2';
    resetButton.textContent = 'Clear Form';
    resetButton.onclick = function() {
        paymentForm.reset();
        budgetInfo.innerHTML = '';
        remainingBudget.textContent = '';
        amountInput.setCustomValidity('');
    };
    paymentForm.appendChild(resetButton);

    // Initialize date picker with default value
    const dateInput = document.querySelector('input[type="date"]');
    if (!dateInput.value) {
        dateInput.valueAsDate = new Date();
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Alt + N for new payment
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            projectSelect.focus();
        }
        // Alt + S to submit form
        if (e.altKey && e.key === 's' && document.activeElement.closest('form')) {
            e.preventDefault();
            if (paymentForm.checkValidity()) {
                paymentForm.submit();
            } else {
                paymentForm.reportValidity();
            }
        }
    });

    // Format numbers as currency
    function formatCurrency(number) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(number);
    }

    // Add tooltips for budget information
    const addTooltip = (element, text) => {
        let tooltip = null;
        
        element.addEventListener('mouseenter', (e) => {
            tooltip = document.createElement('div');
            tooltip.className = 'absolute bg-black text-white px-2 py-1 rounded text-sm z-50';
            tooltip.textContent = text;
            tooltip.style.top = `${e.pageY + 10}px`;
            tooltip.style.left = `${e.pageX + 10}px`;
            document.body.appendChild(tooltip);
        });

        element.addEventListener('mouseleave', () => {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        });
    };

    // Add tooltips to amount cells
    document.querySelectorAll('td:nth-child(4)').forEach(cell => {
        const amount = parseFloat(cell.textContent.replace(/[^0-9.-]+/g, ''));
        addTooltip(cell, `Payment Amount: ${formatCurrency(amount)}`);
    });
</script>

<!-- Add this before </body> tag -->
<div class="fixed bottom-6 left-6">
    <a href="export_payments.php" 
       class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-200 shadow-lg hover:shadow-xl group">
        <i class="fas fa-file-excel fa-spin-hover text-xl"></i>
        <span class="font-medium">Export Payments</span>
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
        </span>
    </a>
</div>

<style>
/* Add this if you don't already have these styles */
.fa-spin-hover {
    transition: transform 0.3s ease;
}

.group:hover .fa-spin-hover {
    transform: rotate(180deg);
}

.fixed.bottom-6.left-6 a {
    background-size: 200% 200%;
    animation: gradient-shift 5s ease infinite;
}

.fixed.bottom-6.left-6 a:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(52, 211, 153, 0.3);
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
</style>
</body>

<?php include 'loader.php'; ?>

</html>