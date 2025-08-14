<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Database connection
    // Database connection
    $host = 'localhost';
    $dbname = 'aatcabuj_visitors_version_2';
    $username = 'aatcabuj_admin';
    $password = 'Sgt.pro@501';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_POST['action'] ?? '';
    $today = date('Y-m-d');
    
    if ($action === 'increment') {
        // Add 1 to today's premises entry count
        $stmt = $pdo->prepare("
            INSERT INTO daily_premises_entries (entry_date, total_entries) 
            VALUES (?, 1) 
            ON DUPLICATE KEY UPDATE total_entries = total_entries + 1
        ");
        $stmt->execute([$today]);
        
        // Log individual entry
        $stmt = $pdo->prepare("
            INSERT INTO premises_entry_log (entry_date, entry_count, entry_type) 
            VALUES (?, 1, 'individual')
        ");
        $stmt->execute([$today]);
        
        $message = "Premises entry recorded";
        
    } elseif ($action === 'bulk_add') {
        // Add multiple entries (group entry)
        $count = intval($_POST['count'] ?? 0);
        $type = $_POST['type'] ?? 'Group';
        
        if ($count < 1 || $count > 50) {
            throw new Exception("Invalid count. Must be between 1 and 50.");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO daily_premises_entries (entry_date, total_entries) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE total_entries = total_entries + ?
        ");
        $stmt->execute([$today, $count, $count]);
        
        // Log group entry
        $stmt = $pdo->prepare("
            INSERT INTO premises_entry_log (entry_date, entry_count, entry_type, notes) 
            VALUES (?, ?, 'group', ?)
        ");
        $stmt->execute([$today, $count, "$type entry"]);
        
        $message = "Recorded $count premises entries";
        
    } elseif ($action === 'decrement') {
        // Subtract 1 from today's count (don't go below 0)
        $stmt = $pdo->prepare("
            UPDATE daily_premises_entries 
            SET total_entries = GREATEST(total_entries - 1, 0) 
            WHERE entry_date = ?
        ");
        $stmt->execute([$today]);
        
        $message = "Premises entry count decreased";
        
    } elseif ($action === 'get_count') {
        $message = "Count retrieved";
    } else {
        throw new Exception("Invalid action");
    }
    
    // Get current premises entry count
    $stmt = $pdo->prepare("
        SELECT total_entries 
        FROM daily_premises_entries 
        WHERE entry_date = ?
    ");
    $stmt->execute([$today]);
    $result = $stmt->fetch();
    $total_entries = $result['total_entries'] ?? 0;
    
    // Get office visitor count (reception + QR check-ins)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as office_visitors 
        FROM visitors 
        WHERE DATE(COALESCE(check_in_time, created_at)) = ? 
        AND status IN ('checked_in', 'checked_out', 'approved')
    ");
    $stmt->execute([$today]);
    $office_result = $stmt->fetch();
    $office_visitors = $office_result['office_visitors'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'total_count' => $total_entries,
        'premises_entries' => $total_entries,
        'office_visitors' => $office_visitors,
        'hotel_other_traffic' => max(0, $total_entries - $office_visitors)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'total_count' => 0
    ]);
}
?>