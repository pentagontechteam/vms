<?php
session_start();

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

require 'db_connection.php';

$group_id = isset($_GET['group_id']) ? $conn->real_escape_string($_GET['group_id']) : '';

if (empty($group_id)) {
    die("Group ID not provided");
}

$stmt = $conn->prepare("SELECT name, phone, email, organization, host_name, visit_date, time_of_visit, 
                              floor_of_visit, reason, status, check_in_time, check_out_time, is_group_leader 
                      FROM visitors 
                      WHERE group_id = ? 
                      ORDER BY is_group_leader DESC, name ASC");
$stmt->bind_param("s", $group_id);
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="group_' . $group_id . '_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');

// CSV headers
fputcsv($output, [
    'Group ID',
    'Name',
    'Phone',
    'Email',
    'Organization',
    'Host Name',
    'Visit Date',
    'Time of Visit',
    'Floor/Location',
    'Purpose',
    'Status',
    'Check-in Time',
    'Check-out Time',
    'Role'
]);

// CSV data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $group_id,
        $row['name'],
        $row['phone'],
        $row['email'],
        $row['organization'],
        $row['host_name'],
        $row['visit_date'],
        $row['time_of_visit'],
        $row['floor_of_visit'],
        $row['reason'],
        $row['status'],
        $row['check_in_time'],
        $row['check_out_time'] ?: 'N/A',
        $row['is_group_leader'] == 1 ? 'Group Leader' : 'Group Member'
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
