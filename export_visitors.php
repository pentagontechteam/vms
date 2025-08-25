<?php
session_start();

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

// DB Connection
require 'db_connection.php';

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

// Validate dates
if (strtotime($start_date) === false || strtotime($end_date) === false) {
    die("Invalid date format");
}

// Swap dates if start is after end
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Query visitors within date range
$query = "SELECT 
            name, organization, reason, check_in_time, check_out_time, host_name, floor_of_visit, phone, email, visit_date 
            status 
          FROM visitors 
          WHERE DATE(visit_date) BETWEEN ? AND ?
          ORDER BY visit_date DESC, check_in_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$visitors = $result->fetch_all(MYSQLI_ASSOC);

if ($format === 'excel') {
    // Export as Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="visitors_' . date('Y-m-d') . '.xls"');

    echo "<table border='1'>";
    echo "<tr>
            <th>Visitor Name</th>
            <th>Company Name</th>
            <th>Visit Purpose</th>
            <th>In</th>
            <th>Out</th>
            <th>Host Name</th>
            <th>Floor/Venue</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Visit Date</th>
            <th>Status</th>
          </tr>";

    foreach ($visitors as $visitor) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($visitor['name']) . "</td>";
        echo "<td>" . htmlspecialchars($visitor['organization']) . "</td>";
        echo "<td>" . htmlspecialchars($visitor['reason'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($visitor['check_in_time'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($visitor['check_out_time'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($visitor['host_name']) . "</td>";
        echo "<td>" . htmlspecialchars($visitor['floor_of_visit'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($visitor['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($visitor['email']) . "</td>";

        echo "<td>" . htmlspecialchars($visitor['visit_date']) . "</td>";
        echo "<td>" . htmlspecialchars($visitor['status']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    // Export as CSV (default)
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="visitors_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Write headers
    fputcsv($output, array(
        'Name',
        'Company Name',
        'Visit Purpose',
        'In',
        'Out',
        'Host Name',
        'Floor/Venue',
        'Phone',
        'Email',
        'Visit Date',
        'Status'
    ));

    // Write data
    foreach ($visitors as $visitor) {
        fputcsv($output, array(
            $visitor['name'],
            $visitor['organization'],
            $visitor['reason'] ?? 'N/A',
            $visitor['check_in_time'] ?? 'N/A',
            $visitor['check_out_time'] ?? 'N/A',
            $visitor['host_name'],
            $visitor['floor_of_visit'] ?? 'N/A',
            $visitor['phone'],
            $visitor['email'],
            $visitor['visit_date'],
            $visitor['status']
        ));
    }

    fclose($output);
}

exit();
