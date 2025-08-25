<?php
// File: process_group_checkout.php
session_start();

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

require 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['group_id'])) {
    $group_id = $conn->real_escape_string($_POST['group_id']);

    // Update all group members to checked_out status
    $stmt = $conn->prepare("UPDATE visitors SET status = 'checked_out', check_out_time = NOW() WHERE group_id = ? AND status = 'checked_in'");
    $stmt->bind_param("s", $group_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        header("Location: vmc_dashboard.php?success=group_checked_out&count=$affected_rows&active_tab=checkedout");
    } else {
        header("Location: vmc_dashboard.php?error=group_checkout_failed&active_tab=checkedin");
    }

    $stmt->close();
}

$conn->close();
