<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is admin
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

// Get statistics
// Messages Analytics
$sql = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_messages,
            COUNT(DISTINCT user_id) as active_users
        FROM general_chat_messages 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date";
$message_stats = mysqli_query($conn, $sql);

// Project Analytics
$sql = "SELECT 
            status,
            COUNT(*) as count,
            AVG(DATEDIFF(deadline, created_at)) as avg_duration
        FROM projects 
        GROUP BY status";
$project_stats = mysqli_query($conn, $sql);

// Department Activity
$sql = "SELECT 
            u.department,
            COUNT(DISTINCT p.id) as total_projects,
            COUNT(DISTINCT m.id) as total_messages
        FROM users u
        LEFT JOIN project_departments pd ON u.department = pd.department
        LEFT JOIN projects p ON pd.project_id = p.id
        LEFT JOIN general_chat_messages m ON u.id = m.user_id
        GROUP BY u.department";
$department_stats = mysqli_query($conn, $sql);

// User Engagement
$sql = "SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as message_count
        FROM general_chat_messages
        GROUP BY HOUR(created_at)
        ORDER BY hour";
$hourly_activity = mysqli_query($conn, $sql);

// Payment Analytics
$sql = "SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count
        FROM payments
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month";
$payment_stats = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        .chart-container {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .chart-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Analytics Dashboard</h1>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            // Get quick stats
            $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'];
            $total_projects = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM projects"))['count'];
            $total_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM general_chat_messages"))['count'];
            $total_payments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments"))['total'];
            ?>
            <div class="stat-card rounded-xl p-6 shadow-sm">
                <i class="fas fa-users text-blue-500 text-2xl mb-2"></i>
                <p class="text-gray-600 text-sm">Total Users</p>
                <p class="text-2xl font-bold"><?php echo number_format($total_users); ?></p>
            </div>
            <div class="stat-card rounded-xl p-6 shadow-sm">
                <i class="fas fa-project-diagram text-green-500 text-2xl mb-2"></i>
                <p class="text-gray-600 text-sm">Active Projects</p>
                <p class="text-2xl font-bold"><?php echo number_format($total_projects); ?></p>
            </div>
            <div class="stat-card rounded-xl p-6 shadow-sm">
                <i class="fas fa-comments text-purple-500 text-2xl mb-2"></i>
                <p class="text-gray-600 text-sm">Total Messages</p>
                <p class="text-2xl font-bold"><?php echo number_format($total_messages); ?></p>
            </div>
            <div class="stat-card rounded-xl p-6 shadow-sm">
                <i class="fas fa-dollar-sign text-yellow-500 text-2xl mb-2"></i>
                <p class="text-gray-600 text-sm">Total Payments</p>
                <p class="text-2xl font-bold">$<?php echo number_format($total_payments, 2); ?></p>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Message Activity Chart -->
            <div class="chart-container bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Message Activity (Last 30 Days)</h2>
                <div id="messageChart" style="height: 300px;"></div>
            </div>

            <!-- Project Status Chart -->
            <div class="chart-container bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Project Status Distribution</h2>
                <div id="projectChart" style="height: 300px;"></div>
            </div>

            <!-- Department Performance -->
            <div class="chart-container bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Department Activity</h2>
                <div id="departmentChart" style="height: 300px;"></div>
            </div>

            <!-- Hourly Activity Chart -->
            <div class="chart-container bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Hourly Message Distribution</h2>
                <div id="hourlyChart" style="height: 300px;"></div>
            </div>

            <!-- Payment Trends -->
            <div class="chart-container bg-white rounded-xl p-6 shadow-sm md:col-span-2">
                <h2 class="text-xl font-bold mb-4">Payment Trends (Last 12 Months)</h2>
                <div id="paymentChart" style="height: 300px;"></div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart', 'line']});
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // Message Activity Chart
            var messageData = new google.visualization.DataTable();
            messageData.addColumn('date', 'Date');
            messageData.addColumn('number', 'Messages');
            messageData.addColumn('number', 'Active Users');

            <?php
            echo "messageData.addRows([";
            while ($row = mysqli_fetch_assoc($message_stats)) {
                echo sprintf(
                    "[new Date('%s'), %d, %d],",
                    $row['date'],
                    $row['total_messages'],
                    $row['active_users']
                );
            }
            echo "]);";
            ?>

            var messageChart = new google.visualization.LineChart(document.getElementById('messageChart'));
            messageChart.draw(messageData, {
                curveType: 'function',
                legend: { position: 'bottom' },
                colors: ['#3b82f6', '#10b981'],
                chartArea: {width: '80%', height: '70%'}
            });

            // Project Status Chart
            var projectData = new google.visualization.DataTable();
            projectData.addColumn('string', 'Status');
            projectData.addColumn('number', 'Count');

            <?php
            echo "projectData.addRows([";
            while ($row = mysqli_fetch_assoc($project_stats)) {
                echo sprintf(
                    "['%s', %d],",
                    ucfirst($row['status']),
                    $row['count']
                );
            }
            echo "]);";
            ?>

            var projectChart = new google.visualization.PieChart(document.getElementById('projectChart'));
            projectChart.draw(projectData, {
                colors: ['#3b82f6', '#10b981', '#6366f1'],
                chartArea: {width: '80%', height: '80%'}
            });

            // Department Activity Chart
            var deptData = new google.visualization.DataTable();
            deptData.addColumn('string', 'Department');
            deptData.addColumn('number', 'Projects');
            deptData.addColumn('number', 'Messages');

            <?php
            echo "deptData.addRows([";
            while ($row = mysqli_fetch_assoc($department_stats)) {
                echo sprintf(
                    "['%s', %d, %d],",
                    $row['department'],
                    $row['total_projects'],
                    $row['total_messages']
                );
            }
            echo "]);";
            ?>

            var deptChart = new google.visualization.ColumnChart(document.getElementById('departmentChart'));
            deptChart.draw(deptData, {
                isStacked: true,
                colors: ['#3b82f6', '#10b981'],
                chartArea: {width: '80%', height: '70%'}
            });

            // Hourly Activity Chart
            var hourlyData = new google.visualization.DataTable();
            hourlyData.addColumn('string', 'Hour');
            hourlyData.addColumn('number', 'Messages');

            <?php
            echo "hourlyData.addRows([";
            while ($row = mysqli_fetch_assoc($hourly_activity)) {
                echo sprintf(
                    "['%02d:00', %d],",
                    $row['hour'],
                    $row['message_count']
                );
            }
            echo "]);";
            ?>

            var hourlyChart = new google.visualization.AreaChart(document.getElementById('hourlyChart'));
            hourlyChart.draw(hourlyData, {
                colors: ['#6366f1'],
                chartArea: {width: '80%', height: '70%'}
            });

            // Payment Trends Chart
            var paymentData = new google.visualization.DataTable();
            paymentData.addColumn('string', 'Month');
            paymentData.addColumn('number', 'Amount');
            paymentData.addColumn('number', 'Transactions');

            <?php
            echo "paymentData.addRows([";
            while ($row = mysqli_fetch_assoc($payment_stats)) {
                echo sprintf(
                    "['%s', %f, %d],",
                    $row['month'],
                    $row['total_amount'],
                    $row['transaction_count']
                );
            }
            echo "]);";
            ?>

            var paymentChart = new google.visualization.ComboChart(document.getElementById('paymentChart'));
            paymentChart.draw(paymentData, {
                seriesType: 'bars',
                series: {1: {type: 'line'}},
                colors: ['#3b82f6', '#10b981'],
                chartArea: {width: '80%', height: '70%'}
            });
        }

        // Make charts responsive
        window.addEventListener('resize', drawCharts);
    </script>


<?php include 'loader.php'; ?>

</body>
</html>