<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['receptionist_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require 'db_connection.php';

if (isset($_GET['group_id'])) {
    $group_id = $conn->real_escape_string($_GET['group_id']);

    $stmt = $conn->prepare("SELECT id, name, phone, email, status, is_group_leader, check_in_time, check_out_time 
                          FROM visitors 
                          WHERE group_id = ? 
                          ORDER BY is_group_leader DESC, name ASC");
    $stmt->bind_param("s", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }

    echo json_encode(['members' => $members]);
    $stmt->close();
} else {
    echo json_encode(['error' => 'Group ID not provided']);
}

$conn->close();
