<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB Connection - using MySQLi to match your main file
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

header('Content-Type: application/json');

// Log the incoming request for debugging
error_log("Check-in request received. POST data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_id'])) {
    $guest_id = intval($_POST['guest_id']);
    
    try {
        // First, let's check if the guest exists and their current status
        $check_stmt = $conn->prepare("SELECT id, name, status FROM visitors WHERE id = ?");
        $check_stmt->bind_param("i", $guest_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $guest = $result->fetch_assoc();
        
        if (!$guest) {
            echo json_encode(['success' => false, 'message' => 'Guest not found with ID: ' . $guest_id]);
            $check_stmt->close();
            $conn->close();
            exit;
        }
        
        error_log("Found guest: " . print_r($guest, true));
        
        // Update the visitor status to 'checked_in' and set check_in_time
        $stmt = $conn->prepare("UPDATE visitors SET status = 'checked_in', check_in_time = NOW() WHERE id = ? AND status = 'approved'");
        $stmt->bind_param("i", $guest_id);
        $result = $stmt->execute();
        
        if ($result && $conn->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Guest checked in successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Guest not found or not in approved status. Current status: ' . $guest['status']]);
        }
        
        $stmt->close();
        $check_stmt->close();
        
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Method: ' . $_SERVER['REQUEST_METHOD'] . ', guest_id present: ' . (isset($_POST['guest_id']) ? 'yes' : 'no')]);
}

$conn->close();
?>