<?php
/**
 * Test endpoint to verify API is working
 */
function handleTest() {
    global $conn;
    
    $response = [
        'success' => true,
        'message' => 'API is working correctly',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'database_status' => 'connected'
    ];
    
    // Test database connection
    $result = $conn->query("SELECT COUNT(*) as total FROM receptionists");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['receptionists_count'] = (int)$row['total'];
    } else {
        $response['database_error'] = $conn->error;
        $response['database_status'] = 'error';
    }
    
    // Test visitors table
    $result = $conn->query("SELECT COUNT(*) as total FROM visitors WHERE receptionist_id IS NOT NULL");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['visitors_with_receptionist'] = (int)$row['total'];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}

/**
 * Receptionists Management API
 * File: receptionists_api.php
 * Handles all CRUD operations for receptionists management
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session for admin authentication
session_start();

// Database connection
require 'db_connection.php';

// Set charset to prevent encoding issues
$conn->set_charset("utf8");

// Set timezone
date_default_timezone_set('Africa/Lagos');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the incoming request for debugging
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
error_log("Action: " . ($_GET['action'] ?? 'none'));

// Check if admin is logged in (optional - uncomment if authentication is required)
/*
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['cso_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
*/

// Get the action from query parameters
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'test':
            handleTest();
            break;
        case 'get_all':
            handleGetAll();
            break;
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        case 'change_password':
            handleChangePassword();
            break;
        case 'bulk_delete':
            handleBulkDelete();
            break;
        case 'bulk_reset_password':
            handleBulkResetPassword();
            break;
        case 'export':
            handleExport();
            break;
        case 'get_statistics':
            handleGetStatistics();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action specified',
                'available_actions' => ['test', 'get_all', 'create', 'update', 'delete', 'change_password', 'bulk_delete', 'bulk_reset_password', 'export', 'get_statistics']
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Receptionists API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
} finally {
    $conn->close();
}

/**
 * Get all receptionists with their statistics
 */
function handleGetAll() {
    global $conn;
    
    // First, let's check if there are any receptionists at all
    $testQuery = "SELECT COUNT(*) as total FROM receptionists";
    $testResult = $conn->query($testQuery);
    $totalReceptionists = $testResult ? $testResult->fetch_assoc()['total'] : 0;
    
    error_log("Total receptionists in database: " . $totalReceptionists);
    
    $sql = "
        SELECT 
            r.id,
            r.name,
            r.username,
            r.profile_completed,
            COALESCE(v.guests_processed, 0) as guests_processed,
            COALESCE(v.guests_today, 0) as guests_today,
            CASE 
                WHEN r.profile_completed = 1 THEN 'active'
                ELSE 'inactive'
            END as status,
            CASE 
                WHEN COALESCE(v.guests_processed, 0) >= 20 THEN 'High'
                WHEN COALESCE(v.guests_processed, 0) >= 5 THEN 'Medium'
                ELSE 'Low'
            END as performance_level,
            r.profile_completed as is_setup_complete
        FROM receptionists r
        LEFT JOIN (
            SELECT 
                receptionist_id,
                COUNT(*) as guests_processed,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as guests_today
            FROM visitors 
            WHERE receptionist_id IS NOT NULL 
            AND requested_by_receptionist = 1
            GROUP BY receptionist_id
        ) v ON r.id = v.receptionist_id
        ORDER BY guests_processed DESC, r.name ASC
    ";
    
    error_log("Executing SQL: " . $sql);
    
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("SQL Error: " . $conn->error);
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $receptionists = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure all fields are properly typed
        $receptionists[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?: 'Unknown',
            'username' => $row['username'] ?: 'N/A',
            'profile_completed' => (int)$row['profile_completed'],
            'guests_processed' => (int)$row['guests_processed'],
            'guests_today' => (int)$row['guests_today'],
            'status' => $row['status'],
            'performance_level' => $row['performance_level'],
            'is_setup_complete' => (int)$row['is_setup_complete']
        ];
    }
    
    error_log("Found " . count($receptionists) . " receptionists");
    
    // Get overall statistics
    $statistics = getOverallStatistics();
    
    $response = [
        'success' => true,
        'data' => $receptionists,
        'statistics' => $statistics,
        'count' => count($receptionists),
        'debug_info' => [
            'total_in_db' => $totalReceptionists,
            'returned_count' => count($receptionists),
            'query_executed' => true
        ]
    ];
    
    error_log("Response: " . json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR));
    
    echo json_encode($response, JSON_NUMERIC_CHECK);
}

/**
 * Create a new receptionist
 */
function handleCreate() {
    global $conn;
    
    // Validate required fields
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required and cannot be empty']);
        return;
    }
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username is required and cannot be empty']);
        return;
    }
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required and cannot be empty']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Validate username format (alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers and underscores']);
        return;
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM receptionists WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose a different username.']);
        return;
    }
    $stmt->close();
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if (!$hashedPassword) {
        echo json_encode(['success' => false, 'message' => 'Failed to hash password']);
        return;
    }
    
    // Insert new receptionist
    $stmt = $conn->prepare("INSERT INTO receptionists (name, username, password, profile_completed) VALUES (?, ?, ?, 1)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sss", $name, $username, $hashedPassword);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        echo json_encode([
            'success' => true, 
            'message' => 'Receptionist added successfully',
            'id' => $newId
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception("Failed to create receptionist: " . $error);
    }
}
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM receptionists WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        return;
    }
    $stmt->close();
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new receptionist
    $stmt = $conn->prepare("INSERT INTO receptionists (name, username, password, profile_completed) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $name, $username, $hashedPassword);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Receptionist added successfully',
            'id' => $newId
        ]);
    } else {
        throw new Exception("Failed to create receptionist: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Update an existing receptionist
 */
function handleUpdate() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid receptionist ID']);
        return;
    }
    
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    
    if (empty($name) || empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Name and username are required']);
        return;
    }
    
    // Check if username already exists for other receptionists
    $stmt = $conn->prepare("SELECT id FROM receptionists WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        return;
    }
    $stmt->close();
    
    // Update receptionist
    $stmt = $conn->prepare("UPDATE receptionists SET name = ?, username = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $username, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Receptionist updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Receptionist not found or no changes made']);
        }
    } else {
        throw new Exception("Failed to update receptionist: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Delete a receptionist
 */
function handleDelete() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid receptionist ID']);
        return;
    }
    
    // Check if receptionist has processed visitors (optional - you might want to prevent deletion)
    $stmt = $conn->prepare("SELECT COUNT(*) as visitor_count FROM visitors WHERE receptionist_id = ? AND requested_by_receptionist = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $visitorCount = $row['visitor_count'];
    $stmt->close();
    
    if ($visitorCount > 0) {
        // Update visitor records to remove receptionist association instead of preventing deletion
        $stmt = $conn->prepare("UPDATE visitors SET receptionist_id = NULL WHERE receptionist_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete the receptionist
    $stmt = $conn->prepare("DELETE FROM receptionists WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Receptionist deleted successfully' . 
                ($visitorCount > 0 ? " ($visitorCount visitor records updated)" : '')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Receptionist not found']);
        }
    } else {
        throw new Exception("Failed to delete receptionist: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Change receptionist password
 */
function handleChangePassword() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid receptionist ID']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and mark profile as needing completion (optional)
    $stmt = $conn->prepare("UPDATE receptionists SET password = ?, profile_completed = 0 WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Receptionist not found']);
        }
    } else {
        throw new Exception("Failed to change password: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Bulk delete receptionists
 */
function handleBulkDelete() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
        return;
    }
    
    // Sanitize IDs
    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
        return;
    }
    
    $conn->autocommit(FALSE);
    
    try {
        // Update visitor records to remove receptionist associations
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $conn->prepare("UPDATE visitors SET receptionist_id = NULL WHERE receptionist_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $updatedVisitors = $stmt->affected_rows;
        $stmt->close();
        
        // Delete receptionists
        $stmt = $conn->prepare("DELETE FROM receptionists WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully deleted $deletedCount receptionists" . 
            ($updatedVisitors > 0 ? " ($updatedVisitors visitor records updated)" : '')
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    } finally {
        $conn->autocommit(TRUE);
    }
}

/**
 * Bulk reset passwords
 */
function handleBulkResetPassword() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
        return;
    }
    
    // Sanitize IDs
    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
        return;
    }
    
    // Generate default password (you might want to customize this)
    $defaultPassword = 'Reception123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $params = array_merge([$hashedPassword], $ids);
    $types = 's' . str_repeat('i', count($ids));
    
    $stmt = $conn->prepare("UPDATE receptionists SET password = ?, profile_completed = 0 WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        echo json_encode([
            'success' => true, 
            'message' => "Successfully reset passwords for $affectedRows receptionists. Default password: $defaultPassword"
        ]);
    } else {
        throw new Exception("Failed to reset passwords: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Export receptionists data to CSV
 */
function handleExport() {
    global $conn;
    
    try {
        $sql = "
            SELECT 
                r.id,
                r.name,
                r.username,
                CASE 
                    WHEN r.profile_completed = 1 THEN 'Active'
                    ELSE 'Inactive'
                END as status,
                COALESCE(v.guests_processed, 0) as guests_processed,
                COALESCE(v.guests_today, 0) as guests_today,
                CASE 
                    WHEN COALESCE(v.guests_processed, 0) >= 20 THEN 'High'
                    WHEN COALESCE(v.guests_processed, 0) >= 5 THEN 'Medium'
                    ELSE 'Low'
                END as performance_level
            FROM receptionists r
            LEFT JOIN (
                SELECT 
                    receptionist_id,
                    COUNT(*) as guests_processed,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as guests_today
                FROM visitors 
                WHERE receptionist_id IS NOT NULL 
                AND requested_by_receptionist = 1
                AND status IN ('checked_in', 'checked_out', 'approved')
                GROUP BY receptionist_id
            ) v ON r.id = v.receptionist_id
            ORDER BY r.name ASC
        ";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="receptionists_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, [
            'ID',
            'Full Name',
            'Username',
            'Status',
            'Total Guests Processed',
            'Guests Today',
            'Performance Level',
            'Export Date',
            'Export Time'
        ]);
        
        // CSV Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['name'] ?? 'Unknown',
                $row['username'] ?? 'N/A',
                $row['status'],
                $row['guests_processed'],
                $row['guests_today'],
                $row['performance_level'],
                date('Y-m-d'),
                date('H:i:s')
            ]);
        }
        
        fclose($output);
        exit();
        
    } catch (Exception $e) {
        // If export fails, return JSON error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Export failed: ' . $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Get overall statistics
 */
function handleGetStatistics() {
    global $conn;
    
    $statistics = getOverallStatistics();
    
    echo json_encode([
        'success' => true,
        'statistics' => $statistics
    ]);
}

/**
 * Helper function to get overall statistics
 */
function getOverallStatistics() {
    global $conn;
    
    $stats = [];
    
    try {
        // Total receptionists
        $result = $conn->query("SELECT COUNT(*) as total FROM receptionists");
        if ($result) {
            $stats['total_receptionists'] = (int)($result->fetch_assoc()['total'] ?? 0);
        } else {
            $stats['total_receptionists'] = 0;
        }
        
        // Active receptionists (profile completed)
        $result = $conn->query("SELECT COUNT(*) as active FROM receptionists WHERE profile_completed = 1");
        if ($result) {
            $stats['active_receptionists'] = (int)($result->fetch_assoc()['active'] ?? 0);
        } else {
            $stats['active_receptionists'] = 0;
        }
        
        // Receptionists who processed guests today
        $result = $conn->query("
            SELECT COUNT(DISTINCT receptionist_id) as active_today 
            FROM visitors 
            WHERE DATE(created_at) = CURDATE() 
            AND receptionist_id IS NOT NULL 
            AND requested_by_receptionist = 1
            AND status IN ('checked_in', 'checked_out', 'approved')
        ");
        if ($result) {
            $stats['active_today'] = (int)($result->fetch_assoc()['active_today'] ?? 0);
        } else {
            $stats['active_today'] = 0;
        }
        
        // Total guests processed by receptionists
        $result = $conn->query("
            SELECT COUNT(*) as total_processed 
            FROM visitors 
            WHERE receptionist_id IS NOT NULL 
            AND requested_by_receptionist = 1
            AND status IN ('checked_in', 'checked_out', 'approved')
        ");
        if ($result) {
            $stats['total_guests_processed'] = (int)($result->fetch_assoc()['total_processed'] ?? 0);
        } else {
            $stats['total_guests_processed'] = 0;
        }
        
        // Guests processed today
        $result = $conn->query("
            SELECT COUNT(*) as todays_processed 
            FROM visitors 
            WHERE DATE(created_at) = CURDATE() 
            AND receptionist_id IS NOT NULL 
            AND requested_by_receptionist = 1
            AND status IN ('checked_in', 'checked_out', 'approved')
        ");
        if ($result) {
            $stats['todays_processed'] = (int)($result->fetch_assoc()['todays_processed'] ?? 0);
        } else {
            $stats['todays_processed'] = 0;
        }
        
        // Average guests per receptionist
        if ($stats['active_receptionists'] > 0) {
            $stats['average_guests_per_receptionist'] = round($stats['total_guests_processed'] / $stats['active_receptionists'], 1);
        } else {
            $stats['average_guests_per_receptionist'] = 0;
        }
        
        // Performance distribution
        $result = $conn->query("
            SELECT 
                SUM(CASE WHEN COALESCE(v.guests_processed, 0) >= 20 THEN 1 ELSE 0 END) as high_performers,
                SUM(CASE WHEN COALESCE(v.guests_processed, 0) >= 5 AND COALESCE(v.guests_processed, 0) < 20 THEN 1 ELSE 0 END) as medium_performers,
                SUM(CASE WHEN COALESCE(v.guests_processed, 0) < 5 THEN 1 ELSE 0 END) as low_performers
            FROM receptionists r
            LEFT JOIN (
                SELECT 
                    receptionist_id,
                    COUNT(*) as guests_processed
                FROM visitors 
                WHERE receptionist_id IS NOT NULL 
                AND requested_by_receptionist = 1
                AND status IN ('checked_in', 'checked_out', 'approved')
                GROUP BY receptionist_id
            ) v ON r.id = v.receptionist_id
        ");
        
        if ($result) {
            $perfData = $result->fetch_assoc();
            $stats['high_performers'] = (int)($perfData['high_performers'] ?? 0);
            $stats['medium_performers'] = (int)($perfData['medium_performers'] ?? 0);
            $stats['low_performers'] = (int)($perfData['low_performers'] ?? 0);
        } else {
            $stats['high_performers'] = 0;
            $stats['medium_performers'] = 0;
            $stats['low_performers'] = 0;
        }
        
    } catch (Exception $e) {
        error_log("Error getting statistics: " . $e->getMessage());
        // Return default values if there's an error
        $stats = [
            'total_receptionists' => 0,
            'active_receptionists' => 0,
            'active_today' => 0,
            'total_guests_processed' => 0,
            'todays_processed' => 0,
            'average_guests_per_receptionist' => 0,
            'high_performers' => 0,
            'medium_performers' => 0,
            'low_performers' => 0
        ];
    }
    
    return $stats;
}

/**
 * Utility function to validate and sanitize input
 */
function sanitizeInput($input, $type = 'string') {
    if ($input === null || $input === '') {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return intval($input);
        case 'email':
            return filter_var(trim($input), FILTER_VALIDATE_EMAIL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Log activity for audit trail (optional)
 */
function logActivity($action, $receptionistId = null, $details = '') {
    global $conn;
    
    $adminId = $_SESSION['admin_id'] ?? $_SESSION['cso_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // You can implement activity logging if needed
    // This would require creating an audit_log table
    
    /*
    $stmt = $conn->prepare("
        INSERT INTO audit_log (admin_id, action, target_type, target_id, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, 'receptionist', ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isisss", $adminId, $action, $receptionistId, $details, $ipAddress, $userAgent);
    $stmt->execute();
    $stmt->close();
    */
}

?>