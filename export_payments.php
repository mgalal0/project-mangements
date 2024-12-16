<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in and is admin
check_login();
if (!is_admin()) {
    header("Location: dashboard.php");
    exit;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.xls"');

// Get all payments with related information
$sql = "SELECT 
        p.payment_date,
        pr.name as project_name,
        pr.total_budget,
        pr.paid_amount as total_paid,
        p.amount,
        u.username as recipient_name,
        u.department as recipient_department,
        p.description
        FROM payments p 
        JOIN projects pr ON p.project_id = pr.id 
        JOIN users u ON p.recipient_id = u.id 
        ORDER BY p.payment_date DESC, p.id DESC";

$result = mysqli_query($conn, $sql);

// Create Excel content
echo "Payment Date\tProject\tProject Budget\tProject Paid Amount\tPayment Amount\tRecipient\tDepartment\tDescription\n";

while ($row = mysqli_fetch_assoc($result)) {
    echo implode("\t", [
        date('Y-m-d', strtotime($row['payment_date'])),
        str_replace("\t", " ", $row['project_name']),
        number_format($row['total_budget'], 2),
        number_format($row['total_paid'], 2),
        number_format($row['amount'], 2),
        str_replace("\t", " ", $row['recipient_name']),
        ucfirst(str_replace('_', '/', $row['recipient_department'])),
        str_replace("\t", " ", $row['description'])
    ]) . "\n";
}
?>