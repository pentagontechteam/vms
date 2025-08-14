<?php
// Configure session to never timeout - NO BOM, NO WHITESPACE BEFORE THIS
ini_set('session.gc_maxlifetime', 0);
ini_set('session.cookie_lifetime', 0);
ini_set('session.cache_expire', 0);

session_start();

if (isset($_SESSION['cso_id'])) {
    setcookie(session_name(), session_id(), 0, '/');
}

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection with error handling
try {
    $conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Check authentication EARLY
if (!isset($_SESSION['cso_id'])) {
    header("Location: cso_login.php");
    exit();
}

// Handle ALL AJAX requests BEFORE any HTML output
if (isset($_GET['sse'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('Access-Control-Allow-Origin: *');
    
    function sendNotification($message) {
        echo "data: " . json_encode($message) . "\n\n";
        ob_flush();
        flush();
    }

    function checkNewRequests($conn, $last_check) {
        $query = "SELECT COUNT(*) as new_requests FROM visitors 
                  WHERE status='pending' AND UNIX_TIMESTAMP(created_at) > ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $last_check);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['new_requests'];
    }

    $last_check = isset($_GET['last_check']) ? intval($_GET['last_check']) : (time() - 60);
    
    set_time_limit(30);
    $start_time = time();
    
    while (time() - $start_time < 25) {
        $newRequests = checkNewRequests($conn, $last_check);
        if ($newRequests > 0) {
            sendNotification([
                'type' => 'new_request',
                'message' => "$newRequests new visitor request(s)",
                'count' => $newRequests,
                'timestamp' => time()
            ]);
            break;
        }
        sleep(2);
    }
    $conn->close();
    exit();
}

// Handle AJAX requests BEFORE any HTML
if (isset($_GET['ajax'])) {
    // Clear any output buffer to prevent header issues
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if ($_GET['ajax'] == 'visitors') {
        header('Content-Type: text/html; charset=utf-8');
        
        $pending_result = $conn->query("
            SELECT v.*, r.name as receptionist_name 
            FROM visitors v
            LEFT JOIN receptionists r ON v.receptionist_id = r.id
            WHERE v.status='pending' 
            ORDER BY v.created_at DESC, v.time_of_visit DESC
        ");
        
        if ($pending_result && $pending_result->num_rows > 0) {
            while ($row = $pending_result->fetch_assoc()) {
                echo '<div class="visitor-item">';
                echo '<div class="visitor-info">';
                echo '<h5>' . htmlspecialchars($row['name']) . '</h5>';
                echo '<span class="badge bg-warning text-dark mb-1">Pending</span>';
                echo '<small><i class="fas fa-phone me-1"></i> ' . htmlspecialchars($row['phone']) . '</small>';
                if (!empty($row['email'])) {
                    echo '<small><i class="fas fa-envelope me-1"></i> ' . htmlspecialchars($row['email']) . '</small>';
                }
                echo '<small><i class="fas fa-user-shield me-1"></i> Visiting: ' . htmlspecialchars($row['host_name']) . '</small>';
                if (!empty($row['time_of_visit'])) {
                    echo '<small><i class="fas fa-calendar-day me-1"></i> ' . date('M j, Y g:i A', strtotime($row['time_of_visit'])) . '</small>';
                }
                if (!empty($row['organization'])) {
                    echo '<small><i class="fas fa-building me-1"></i> Organization: ' . htmlspecialchars($row['organization']) . '</small>';
                }
                if (!empty($row['reason'])) {
                    echo '<small><i class="fas fa-info-circle me-1"></i> Reason: ' . htmlspecialchars($row['reason']) . '</small>';
                }
                if (!empty($row['floor_of_visit'])) {
                    echo '<small><i class="fas fa-map-marker-alt me-1"></i> Floor: ' . htmlspecialchars($row['floor_of_visit']) . '</small>';
                }
                if (!empty($row['receptionist_name'])) {
                    echo '<small><i class="fas fa-user-tie me-1"></i> Registered by: ' . htmlspecialchars($row['receptionist_name']) . '</small>';
                }
                echo '</div>';
                echo '<div class="action-buttons">';
                echo '<a href="approve.php?id=' . $row['id'] . '" class="btn btn-success btn-sm">';
                echo '<i class="fas fa-check me-1"></i> Approve';
                echo '</a>';
                echo '<a href="deny.php?id=' . $row['id'] . '" class="btn btn-danger btn-sm">';
                echo '<i class="fas fa-times me-1"></i> Deny';
                echo '</a>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="empty-state">';
            echo '<i class="fas fa-user-check fa-4x mb-3"></i>';
            echo '<h4>No pending approvals</h4>';
            echo '<p>All visitor requests have been processed</p>';
            echo '</div>';
        }
        $conn->close();
        exit();
    }
    
    // AJAX endpoint for guest history
    if ($_GET['ajax'] == 'history') {
        header('Content-Type: application/json');
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
        
        error_log("History Debug - Search: '$search', Page: $page, Debug Mode: " . ($debug_mode ? 'ON' : 'OFF'));
        
        // Check what data exists in the visitors table
        $debug_query = "SELECT status, COUNT(*) as count FROM visitors GROUP BY status";
        $debug_result = $conn->query($debug_query);
        $status_counts = [];
        if ($debug_result) {
            while ($row = $debug_result->fetch_assoc()) {
                $status_counts[$row['status']] = $row['count'];
            }
        }
        
        // Check available columns
        $columns_query = "SHOW COLUMNS FROM visitors";
        $columns_result = $conn->query($columns_query);
        $available_columns = [];
        if ($columns_result) {
            while ($row = $columns_result->fetch_assoc()) {
                $available_columns[] = $row['Field'];
            }
        }
        
        error_log("History Debug - Available columns: " . implode(', ', $available_columns));
        error_log("History Debug - Status counts: " . json_encode($status_counts));
        
        // Build search conditions
        $search_conditions = [];
        $search_params = [];
        $param_types = '';
        
        if (!empty($search)) {
            $search_conditions[] = "(v.name LIKE ? OR v.phone LIKE ? OR COALESCE(v.email, '') LIKE ? OR COALESCE(v.host_name, '') LIKE ? OR COALESCE(v.organization, '') LIKE ? OR COALESCE(v.reason, '') LIKE ?)";
            $search_term = "%$search%";
            for ($i = 0; $i < 6; $i++) {
                $search_params[] = $search_term;
                $param_types .= 's';
            }
        }
        
        // NEW APPROACH: Show visitors who have been approved at some point
        if ($debug_mode) {
            $where_clause = empty($search_conditions) ? "WHERE 1=1" : "WHERE " . implode(' AND ', $search_conditions);
        } else {
            if (in_array('approved_at', $available_columns)) {
                $where_clause = empty($search_conditions) ? 
                    "WHERE v.approved_at IS NOT NULL" : 
                    "WHERE v.approved_at IS NOT NULL AND " . implode(' AND ', $search_conditions);
            } else {
                $approval_condition = "(v.status='approved' OR v.check_in_time IS NOT NULL)";
                $where_clause = empty($search_conditions) ? 
                    "WHERE $approval_condition" : 
                    "WHERE $approval_condition AND " . implode(' AND ', $search_conditions);
            }
        }
        
        error_log("History Debug - Where clause: $where_clause");
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as total FROM visitors v $where_clause";
        $total_records = 0;
        
        try {
            if (!empty($search_params)) {
                $count_stmt = $conn->prepare($count_query);
                if ($count_stmt) {
                    $count_stmt->bind_param($param_types, ...$search_params);
                    $count_stmt->execute();
                    $result = $count_stmt->get_result();
                    if ($result) {
                        $total_records = $result->fetch_assoc()['total'] ?? 0;
                    }
                    $count_stmt->close();
                }
            } else {
                $result = $conn->query($count_query);
                if ($result) {
                    $total_records = $result->fetch_assoc()['total'] ?? 0;
                }
            }
        } catch (Exception $e) {
            error_log("History count query error: " . $e->getMessage());
        }
        
        error_log("History Debug - Total records found: $total_records");
        
        // Build the SELECT clause based on available columns
        $select_fields = "v.*, ";
        if (in_array('approved_at', $available_columns)) {
            $select_fields .= "v.approved_at as approval_timestamp, ";
        } else {
            $select_fields .= "v.created_at as approval_timestamp, ";
        }
        $select_fields .= "COALESCE(v.check_in_time, '') as check_in_time, ";
        $select_fields .= "COALESCE(v.check_out_time, '') as check_out_time";
        
        $order_clause = in_array('approved_at', $available_columns) ? 
            "ORDER BY v.approved_at DESC, v.created_at DESC, v.id DESC" :
            "ORDER BY v.created_at DESC, v.id DESC";
        
        $query = "
            SELECT $select_fields,
                   COALESCE(r.name, 'System') as receptionist_name
            FROM visitors v
            LEFT JOIN receptionists r ON v.receptionist_id = r.id
            $where_clause
            $order_clause
            LIMIT ? OFFSET ?
        ";
        
        error_log("History Debug - Main query: " . str_replace(["\n", "  "], [" ", " "], $query));
        
        $guests = [];
        
        try {
            $stmt = $conn->prepare($query);
            if ($stmt) {
                if (!empty($search_params)) {
                    $param_types .= 'ii';
                    $search_params[] = $limit;
                    $search_params[] = $offset;
                    $stmt->bind_param($param_types, ...$search_params);
                } else {
                    $stmt->bind_param('ii', $limit, $offset);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $guests[] = $row;
                    }
                }
                $stmt->close();
            } else {
                error_log("History Debug - Failed to prepare statement: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log("History main query error: " . $e->getMessage());
        }
        
        error_log("History Debug - Guests found: " . count($guests));
        
        $response = [
            'guests' => $guests,
            'total' => $total_records,
            'page' => $page,
            'pages' => max(1, ceil($total_records / $limit)),
            'limit' => $limit,
            'debug' => [
                'search' => $search,
                'where_clause' => $where_clause,
                'query_params' => !empty($search_params) ? count($search_params) : 0,
                'status_counts' => $status_counts,
                'available_columns' => $available_columns,
                'debug_mode' => $debug_mode,
                'sql_query' => str_replace(["\n", "  "], [" ", " "], $query),
                'approach' => 'Shows visitors who have EVER been approved, regardless of current status'
            ]
        ];
        
        echo json_encode($response);
        $conn->close();
        exit();
    }
    
    if ($_GET['ajax'] == 'stats') {
        header('Content-Type: application/json');
        echo json_encode(fetchStatistics($conn));
        $conn->close();
        exit();
    }
    
    if ($_GET['ajax'] == 'hosting_trends') {
        error_log("DEBUG: Fetching hosting trends data");
        
        $result = $conn->query("
            SELECT 
                host_name,
                COUNT(*) as total_visitors,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' OR status = 'denied' THEN 1 ELSE 0 END) as rejected,
                COUNT(DISTINCT DATE(created_at)) as hosting_days
            FROM visitors
            WHERE host_name != 'Walk-In' AND host_name IS NOT NULL AND host_name != ''
            GROUP BY host_name
            ORDER BY total_visitors DESC
            LIMIT 10
        ");
        
        if (!$result) {
            error_log("DEBUG: Query failed - " . $conn->error);
            die(json_encode(["error" => $conn->error]));
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        error_log("DEBUG: Returned " . count($data) . " records");
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    if ($_GET['ajax'] == 'traffic_heatmap') {
        $result = $conn->query("
            SELECT 
                HOUR(check_in_time) as hour,
                floor_of_visit,
                COUNT(*) as visitor_count
            FROM visitors
            WHERE check_in_time IS NOT NULL AND floor_of_visit IS NOT NULL
            GROUP BY HOUR(check_in_time), floor_of_visit
            ORDER BY hour, floor_of_visit
        ");
        
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

// Function to fetch statistics
function fetchStatistics($conn) {
    $stats = [
        'total_today' => 0,
        'approved_today' => 0,
        'denied_today' => 0,
        'pending_count' => 0,
        'hourly_data' => [],
        'repeat_visitors' => 0,
        'top_hosts' => [],
        'weekly_data' => [],
        'approval_rate' => 0,
        'total_visitors' => 0
    ];

    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    $result = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE DATE(created_at) = '$today'");
    if ($result) {
        $stats['total_today'] = $result->fetch_assoc()['count'] ?? 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE DATE(created_at) = '$today' AND status='approved'");
    if ($result) {
        $stats['approved_today'] = $result->fetch_assoc()['count'] ?? 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE DATE(created_at) = '$today' AND status='denied'");
    if ($result) {
        $stats['denied_today'] = $result->fetch_assoc()['count'] ?? 0;
    }

    $result = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status='pending'");
    if ($result) {
        $stats['pending_count'] = $result->fetch_assoc()['count'] ?? 0;
    }

    $hourly_result = $conn->query("SELECT HOUR(created_at) as hour, COUNT(*) as count FROM visitors WHERE DATE(created_at) = '$today' GROUP BY HOUR(created_at)");
    if ($hourly_result) {
        while ($row = $hourly_result->fetch_assoc()) {
            $stats['hourly_data'][$row['hour']] = $row['count'];
        }
    }

    $result = $conn->query("SELECT COUNT(*) as count FROM (SELECT phone, COUNT(*) as visits FROM visitors WHERE status='approved' GROUP BY phone HAVING visits > 1) as repeat_visitors");
    if ($result) {
        $stats['repeat_visitors'] = $result->fetch_assoc()['count'] ?? 0;
    }

    $top_hosts_result = $conn->query("SELECT host_name, COUNT(*) as count FROM visitors WHERE status='approved' GROUP BY host_name ORDER BY count DESC LIMIT 5");
    if ($top_hosts_result) {
        while ($row = $top_hosts_result->fetch_assoc()) {
            $stats['top_hosts'][] = $row;
        }
    }
    
    $weekly_result = $conn->query("
        SELECT DAYNAME(created_at) as day, COUNT(*) as count 
        FROM visitors 
        WHERE DATE(created_at) BETWEEN '$week_start' AND '$week_end'
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
        ORDER BY DAYOFWEEK(created_at)
    ");
    
    $days = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0];
    if ($weekly_result) {
        while ($row = $weekly_result->fetch_assoc()) {
            $days[$row['day']] = $row['count'];
        }
    }
    $stats['weekly_data'] = array_values($days);
    
    $last_week_start = date('Y-m-d', strtotime('monday last week'));
    $last_week_end = date('Y-m-d', strtotime('sunday last week'));
    
    $last_weekly_result = $conn->query("
        SELECT DAYNAME(created_at) as day, COUNT(*) as count 
        FROM visitors 
        WHERE DATE(created_at) BETWEEN '$last_week_start' AND '$last_week_end'
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
        ORDER BY DAYOFWEEK(created_at)
    ");
    
    $last_days = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0];
    if ($last_weekly_result) {
        while ($row = $last_weekly_result->fetch_assoc()) {
            $last_days[$row['day']] = $row['count'];
        }
    }
    $stats['last_weekly_data'] = array_values($last_days);
    
    $total_processed = $stats['approved_today'] + $stats['denied_today'];
    $stats['approval_rate'] = $total_processed > 0 ? round(($stats['approved_today'] / $total_processed) * 100, 2) : 0;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status='approved'");
    if ($result) {
        $stats['total_visitors'] = $result->fetch_assoc()['count'] ?? 0;
    }

    return $stats;
}

// Fetch initial statistics
$stats = fetchStatistics($conn);

// Check active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'approvals';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management Dashboard</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #07AF8B;
            --accent-color: #FFCA00;
            --dark-green: #007570;
            --google-blue: #4285f4;
            --google-grey: #5f6368;
            --google-light-grey: #f8f9fa;
            --google-border: #dadce0;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Google Sans', 'Segoe UI', sans-serif;
        }

        .container {
            max-width: 1200px;
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            height: 100%;
        }

        .stat-card {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-green);
        }

        .stat-card .label {
            font-size: 0.9rem;
            color: #666;
        }

        .visitor-list {
            background: white;
            border-radius: 16px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.07);
            padding: 2rem;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .header-bar h1 {
            font-size: 1.6rem;
            color: var(--dark-green);
            font-weight: 700;
        }

        .nav-tabs .nav-link {
            color: var(--dark-green);
            font-weight: 500;
            border: none;
            padding: 0.75rem 1.5rem;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }

        .concentric-chart {
            max-width: 300px;
            margin: 0 auto;
        }

        .visitor-item {
            display: flex;
            flex-direction: column;
            padding: 1rem 0;
            border-bottom: 1px solid #eaeaea;
            transition: background-color 0.2s;
        }

        .visitor-item:hover {
            background-color: #f9f9f9;
        }

        @media (min-width: 576px) {
            .visitor-item {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        .visitor-info {
            flex-grow: 1;
        }

        .visitor-info h5 {
            margin-bottom: 0.5rem;
            color: var(--dark-green);
            font-weight: 600;
        }

        .visitor-info small {
            display: block;
            color: #555;
            margin-bottom: 0.2rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        @media (min-width: 576px) {
            .action-buttons {
                margin-top: 0;
            }
        }

        .btn-success {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: #777;
        }

        .empty-state i {
            color: #ccc;
        }

        .badge.bg-primary {
            background-color: var(--accent-color) !important;
            color: #000;
        }

        .badge.bg-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .logout-btn {
            color: var(--dark-green);
            border: 1px solid var(--dark-green);
        }
        
        .logout-btn:hover {
            background-color: var(--dark-green);
            color: white;
        }

        .analytics-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-green));
            color: white;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(7, 175, 139, 0.3);
        }

        .analytics-btn:hover {
            background: linear-gradient(135deg, var(--dark-green), #005a56);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(7, 175, 139, 0.4);
        }
        
        .tab-content {
            padding-top: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-green);
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .stat-card-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .toast {
            max-width: 350px;
            overflow: hidden;
            font-size: 0.875rem;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            opacity: 1;
        }

        .toast-body {
            padding: 1rem;
        }

        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .refresh-indicator.show {
            opacity: 1;
        }

        /* Google-inspired History Section Styles */
        .history-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            overflow: hidden;
        }

        .history-header {
            background: linear-gradient(135deg, var(--google-light-grey), #fff);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--google-border);
        }

        .history-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--google-grey);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .search-container {
            position: relative;
            max-width: 500px;
            margin-top: 1rem;
        }

       .search-input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid var(--google-border);
            border-radius: 24px;
            font-size: 16px;
            transition: all 0.2s ease;
            background: #fff;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--google-blue);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--google-grey);
            font-size: 18px;
        }

        .clear-search {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--google-grey);
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .clear-search.show {
            opacity: 1;
        }

        .clear-search:hover {
            background-color: rgba(95, 99, 104, 0.1);
        }

        .history-content {
            padding: 0;
        }

        .guest-card {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(218, 220, 224, 0.5);
            transition: background-color 0.15s ease;
            position: relative;
        }

        .guest-card:hover {
            background-color: rgba(66, 133, 244, 0.04);
        }

        .guest-card:last-child {
            border-bottom: none;
        }

        .guest-main-info {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .guest-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--google-blue), #34a853);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            flex-shrink: 0;
        }

        .guest-details {
            flex: 1;
            min-width: 0;
        }

        .guest-name {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--google-grey);
            margin: 0 0 0.25rem 0;
        }

        .guest-contact {
            font-size: 0.9rem;
            color: #5f6368;
            margin-bottom: 0.5rem;
        }

        .guest-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #5f6368;
        }

        .meta-icon {
            width: 16px;
            text-align: center;
            color: var(--google-blue);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 500;
            background: rgba(52, 168, 83, 0.1);
            color: #137333;
            border: 1px solid rgba(52, 168, 83, 0.2);
        }

        .status-icon {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34a853;
        }

        .approval-time {
            position: absolute;
            top: 1.5rem;
            right: 2rem;
            font-size: 0.8rem;
            color: #5f6368;
            text-align: right;
        }

        .pagination-container {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--google-border);
            background: var(--google-light-grey);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            font-size: 0.9rem;
            color: var(--google-grey);
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid var(--google-border);
            background: white;
            color: var(--google-grey);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .page-btn:hover:not(:disabled) {
            background: rgba(66, 133, 244, 0.08);
            border-color: var(--google-blue);
        }

        .page-btn.active {
            background: var(--google-blue);
            color: white;
            border-color: var(--google-blue);
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .loading-state {
            text-align: center;
            padding: 3rem;
            color: var(--google-grey);
        }

        .loading-spinner {
            width: 24px;
            height: 24px;
            border: 3px solid rgba(66, 133, 244, 0.1);
            border-top: 3px solid var(--google-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-history {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--google-grey);
        }

        .empty-history-icon {
            font-size: 4rem;
            color: #dadce0;
            margin-bottom: 1rem;
        }

        .empty-history h3 {
            color: var(--google-grey);
            font-weight: 400;
            margin-bottom: 0.5rem;
        }

        .empty-history p {
            color: #5f6368;
            margin: 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .history-header {
                padding: 1rem;
            }
            
            .guest-card {
                padding: 1rem;
            }
            
            .approval-time {
                position: static;
                margin-top: 0.5rem;
                text-align: left;
            }
            
            .guest-meta {
                grid-template-columns: 1fr;
            }
            
            .pagination-container {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
            }
        }

        /* Search results highlighting */
        .search-highlight {
            background-color: rgba(255, 202, 0, 0.3);
            padding: 1px 2px;
            border-radius: 2px;
        }
        
        .overview-btn {
            background: linear-gradient(135deg, var(--accent-color), #e6b800);
            color: #000;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255, 202, 0, 0.3);
        }

        .overview-btn:hover {
            background: linear-gradient(135deg, #e6b800, #cc9900);
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 202, 0, 0.4);
        }
    </style>
</head>

<body>
<div class="refresh-indicator" id="refreshIndicator">
    <i class="fas fa-sync-alt fa-spin me-1"></i> Refreshing...
</div>

<div class="container py-4">
    <div class="d-flex justify-content-between mb-3">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'approvals' ? 'active' : ''; ?>" href="?tab=approvals">Approvals</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'history' ? 'active' : ''; ?>" href="?tab=history">Guest History</a>
            </li>
        </ul>
        <div class="d-flex gap-2">
            <a href="smanalytics.php" class="btn analytics-btn btn-sm" id="analyticsBtn">
                <i class="fas fa-chart-bar me-1"></i> Analytics
            </a>
            <a href="smoverview.php" class="btn overview-btn btn-sm">
                <i class="fas fa-building me-1"></i> Premises Overview
            </a>
            <a href="cso_logout.php" class="btn logout-btn btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>

    <div class="tab-content">
        <?php if ($active_tab == 'approvals'): ?>
            <div class="row mb-4" id="stats-container">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="value" id="total-today"><?php echo $stats['total_today']; ?></div>
                        <div class="label">Visitors Today</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="value" id="pending-count"><?php echo $stats['pending_count']; ?></div>
                        <div class="label">Pending Approvals</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="value" id="approved-today"><?php echo $stats['approved_today']; ?></div>
                        <div class="label">Approved Today</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="value" id="denied-today"><?php echo $stats['denied_today']; ?></div>
                        <div class="label">Denied Today</div>
                    </div>
                </div>
            </div>

            <div class="visitor-list mt-4">
                <div class="header-bar">
                    <h1>
                        Visitor Approvals
                        <span class="badge bg-primary ms-2" id="pending-badge"><?php echo $stats['pending_count']; ?> Pending</span>
                    </h1>
                    <button onclick="refreshAll()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>

                <div id="visitor-container">
                    <?php 
                    $conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
                    $pending_result = $conn->query("
                        SELECT v.*, r.name as receptionist_name 
                        FROM visitors v
                        LEFT JOIN receptionists r ON v.receptionist_id = r.id
                        WHERE v.status='pending' 
                        ORDER BY v.created_at DESC, v.time_of_visit DESC
                    ");
                    if ($pending_result && $pending_result->num_rows > 0): 
                        while ($row = $pending_result->fetch_assoc()): ?>
                            <div class="visitor-item">
                                <div class="visitor-info">
                                    <h5><?php echo htmlspecialchars($row['name']); ?></h5>
                                    <span class="badge bg-warning text-dark mb-1">Pending</span>
                                    <small><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($row['phone']); ?></small>
                                    <?php if (!empty($row['email'])): ?>
                                        <small><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($row['email']); ?></small>
                                    <?php endif; ?>
                                    <small><i class="fas fa-user-shield me-1"></i> Visiting: <?php echo htmlspecialchars($row['host_name']); ?></small>
                                    <?php if (!empty($row['time_of_visit'])): ?>
                                        <small><i class="fas fa-calendar-day me-1"></i> <?php echo date('M j, Y g:i A', strtotime($row['time_of_visit'])); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($row['organization'])): ?>
                                        <small><i class="fas fa-building me-1"></i> Organization: <?php echo htmlspecialchars($row['organization']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($row['reason'])): ?>
                                        <small><i class="fas fa-info-circle me-1"></i> Reason: <?php echo htmlspecialchars($row['reason']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($row['floor_of_visit'])): ?>
                                        <small><i class="fas fa-map-marker-alt me-1"></i> Floor: <?php echo htmlspecialchars($row['floor_of_visit']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($row['receptionist_name'])): ?>
                                        <small><i class="fas fa-user-tie me-1"></i> Registered by: <?php echo htmlspecialchars($row['receptionist_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="action-buttons">
                                    <a href="approve.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-1"></i> Approve
                                    </a>
                                    <a href="deny.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">
                                        <i class="fas fa-times me-1"></i> Deny
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; 
                        $conn->close();
                    else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-check fa-4x mb-3"></i>
                            <h4>No pending approvals</h4>
                            <p>All visitor requests have been processed</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab == 'history'): ?>
            <div class="history-container">
                <div class="history-header">
                    <h2 class="history-title">
                        <i class="fas fa-history"></i>
                        Guest History
                        <small class="text-muted ms-2" style="font-size: 0.7em;">Shows all guests who have been approved</small>
                    </h2>
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            class="search-input" 
                            placeholder="Search guests by name, phone, email, host, organization..."
                            id="historySearch"
                        >
                        <button class="clear-search" id="clearSearch">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="fetchGuestHistory(1, '', false)">
                            <i class="fas fa-user-check me-1"></i> Guest History
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="fetchGuestHistory(1, '', true)">
                            <i class="fas fa-bug me-1"></i> Debug Mode (All Records)
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="showDebugInfo()">
                            <i class="fas fa-info-circle me-1"></i> Show Debug Info
                        </button>
                    </div>
                    <div class="alert alert-info mt-3" style="font-size: 0.9em;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This shows all guests who have been approved at some point, even if their status was later changed. 
                        The history preserves a permanent record of approved visitors.
                    </div>
                </div>
                
                <div class="history-content">
                    <div id="historyContainer">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <p>Loading guest history...</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Real-time refresh functionality - FIXED VERSION
let refreshInterval = 8000;
let visitorRefreshTimeout;
let statsRefreshTimeout;
let eventSource = null;
let lastRefresh = Date.now();
let isRefreshing = false;

// Guest History functionality
let currentPage = 1;
let searchTimeout;
let currentSearch = '';

// Show refresh indicator
function showRefreshIndicator() {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.add('show');
    }
}

// Hide refresh indicator
function hideRefreshIndicator() {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.remove('show');
    }
}

function fetchVisitorList() {
    if (isRefreshing) return;
    
    isRefreshing = true;
    showRefreshIndicator();
    
    console.log('Fetching visitor list...');
    
    fetch('?ajax=visitors&tab=approvals&t=' + Date.now(), {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.text();
    })
    .then(data => {
        const container = document.getElementById('visitor-container');
        if (container) {
            container.innerHTML = data;
        }
        console.log('Visitor list updated successfully');
        lastRefresh = Date.now();
        hideRefreshIndicator();
        isRefreshing = false;
        
        // Schedule next refresh
        if (visitorRefreshTimeout) clearTimeout(visitorRefreshTimeout);
        visitorRefreshTimeout = setTimeout(fetchVisitorList, refreshInterval);
    })
    .catch(error => {
        console.error('Error fetching visitors:', error);
        hideRefreshIndicator();
        isRefreshing = false;
        
        // Retry after a longer interval on error
        if (visitorRefreshTimeout) clearTimeout(visitorRefreshTimeout);
        visitorRefreshTimeout = setTimeout(fetchVisitorList, refreshInterval * 2);
    });
}

function fetchStats() {
    console.log('Fetching stats...');
    
    fetch('?ajax=stats&tab=approvals&t=' + Date.now(), {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        // Update stat cards
        const elements = {
            'total-today': data.total_today,
            'pending-count': data.pending_count,
            'approved-today': data.approved_today,
            'denied-today': data.denied_today
        };
        
        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id];
            }
        });
        
        // Update pending badge
        const pendingBadge = document.getElementById('pending-badge');
        if (pendingBadge) {
            pendingBadge.textContent = data.pending_count + ' Pending';
        }
        
        console.log('Stats updated successfully');
        
        // Schedule next stats refresh
        if (statsRefreshTimeout) clearTimeout(statsRefreshTimeout);
        statsRefreshTimeout = setTimeout(fetchStats, refreshInterval);
    })
    .catch(error => {
        console.error('Error fetching stats:', error);
        
        // Retry after a longer interval on error
        if (statsRefreshTimeout) clearTimeout(statsRefreshTimeout);
        statsRefreshTimeout = setTimeout(fetchStats, refreshInterval * 2);
    });
}

function refreshAll() {
    console.log('Manual refresh triggered');
    
    // Clear existing timeouts
    if (visitorRefreshTimeout) clearTimeout(visitorRefreshTimeout);
    if (statsRefreshTimeout) clearTimeout(statsRefreshTimeout);
    
    // Close existing EventSource
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    
    // Force immediate refresh
    isRefreshing = false;
    fetchVisitorList();
    fetchStats();
    
    // Restart SSE connection
    setTimeout(setupEventSource, 1000);
}

// Guest History Functions
function fetchGuestHistory(page = 1, search = '', debugMode = false) {
    const container = document.getElementById('historyContainer');
    if (!container) {
        console.error('History container not found');
        return;
    }
    
    console.log(`Fetching guest history - Page: ${page}, Search: "${search}", Debug: ${debugMode}`);
    
    // Show loading state
    container.innerHTML = `
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Loading guest history...</p>
        </div>
    `;
    
    const url = `?ajax=history&page=${page}&search=${encodeURIComponent(search)}&debug=${debugMode ? '1' : '0'}&t=${Date.now()}`;
    console.log('Fetching URL:', url);
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text(); // Get as text first to debug
    })
    .then(text => {
        console.log('Raw response length:', text.length);
        console.log('Raw response preview:', text.substring(0, 1000));
        try {
            const data = JSON.parse(text);
            console.log('Parsed data:', data);
            
            // Store debug mode state
            window.currentDebugMode = debugMode;
            
            displayGuestHistory(data);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Full response text:', text);
            container.innerHTML = `
                <div class="empty-history">
                    <div class="empty-history-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>JSON Parse Error</h3>
                    <p>The server response was not valid JSON.</p>
                    <details class="mt-2">
                        <summary>Response Details</summary>
                        <pre style="font-size: 10px; max-height: 200px; overflow: auto;">${text}</pre>
                    </details>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error fetching guest history:', error);
        container.innerHTML = `
            <div class="empty-history">
                <div class="empty-history-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Error Loading History</h3>
                <p>Network error: ${error.message}</p>
                <button onclick="fetchGuestHistory(${page}, '${search}', ${debugMode})" class="btn btn-primary btn-sm mt-2">
                    <i class="fas fa-redo me-1"></i> Retry
                </button>
            </div>
        `;
    });
}

function showDebugInfo() {
    const container = document.getElementById('historyContainer');
    container.innerHTML = `
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Fetching debug information...</p>
        </div>
    `;
    
    fetch('?ajax=history&debug=1&page=1&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            let debugHtml = `
                <div class="debug-info p-4">
                    <h4>Database Debug Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Visitor Status Counts:</h5>
                            <ul>
            `;
            
            if (data.debug && data.debug.status_counts) {
                Object.keys(data.debug.status_counts).forEach(status => {
                    debugHtml += `<li><strong>${status}:</strong> ${data.debug.status_counts[status]} records</li>`;
                });
            } else {
                debugHtml += '<li>No status count data available</li>';
            }
            
            debugHtml += `
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>Available Columns:</h5>
                            <ul>
            `;
            
            if (data.debug && data.debug.available_columns) {
                data.debug.available_columns.forEach(column => {
                    debugHtml += `<li>${column}</li>`;
                });
            }
            
            debugHtml += `
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h5>SQL Query Used:</h5>
                        <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; font-size: 12px;">${data.debug ? data.debug.sql_query : 'N/A'}</pre>
                    </div>
                    <div class="mt-3">
                        <h5>Query Results:</h5>
                        <p><strong>Total Records Found:</strong> ${data.total}</p>
                        <p><strong>Records on This Page:</strong> ${data.guests ? data.guests.length : 0}</p>
                    </div>
                    <div class="mt-3">
                        <button onclick="fetchGuestHistory(1, '', false)" class="btn btn-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Guest History
                        </button>
                        <button onclick="fetchGuestHistory(1, '', true)" class="btn btn-info">
                            <i class="fas fa-bug me-1"></i> View All Records (Debug Mode)
                        </button>
                    </div>
                </div>
            `;
            
            container.innerHTML = debugHtml;
        })
        .catch(error => {
            container.innerHTML = `
                <div class="empty-history">
                    <h3>Debug Error</h3>
                    <p>Could not fetch debug information: ${error.message}</p>
                </div>
            `;
        });
}

function displayGuestHistory(data) {
    const container = document.getElementById('historyContainer');
    
    console.log('Displaying guest history:', data);
    
    // Check for errors in response
    if (data.error) {
        container.innerHTML = `
            <div class="empty-history">
                <div class="empty-history-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Database Error</h3>
                <p>${data.error}</p>
            </div>
        `;
        return;
    }
    
    // Show debug information if available
    if (data.debug) {
        console.log('Debug info:', data.debug);
        console.log('Status counts:', data.debug.status_counts);
        console.log('Available columns:', data.debug.available_columns);
    }
    
    if (!data.guests || data.guests.length === 0) {
        let emptyMessage = '';
        let emptyIcon = 'fas fa-users-slash';
        
        if (data.debug && data.debug.status_counts) {
            const totalRecords = Object.values(data.debug.status_counts).reduce((a, b) => a + b, 0);
            const approvedCount = data.debug.status_counts['approved'] || 0;
            
            if (totalRecords === 0) {
                emptyMessage = 'No visitor records found in the database';
                emptyIcon = 'fas fa-database';
            } else if (approvedCount === 0) {
                emptyMessage = `Found ${totalRecords} visitor records, but none are approved yet`;
                emptyIcon = 'fas fa-clock';
            } else {
                emptyMessage = currentSearch ? 'No matching guests found' : 'No guests match the current filter';
            }
        } else {
            emptyMessage = currentSearch ? 'No matching guests found' : 'No approved guests yet';
        }
        
        container.innerHTML = `
            <div class="empty-history">
                   <div class="empty-history-icon">
                       <i class="${emptyIcon}"></i>
                   </div>
                   <h3>${emptyMessage}</h3>
                   <p>${currentSearch ? 'Try adjusting your search terms' : 'Guest history will appear here once visitors are approved'}</p>
                   ${data.debug ? `
                       <div class="mt-3 p-3 bg-light rounded">
                           <small class="text-muted">
                               <strong>Debug Info:</strong><br>
                               Total DB Records: ${Object.values(data.debug.status_counts || {}).reduce((a, b) => a + b, 0)}<br>
                               Approved Records: ${data.debug.status_counts?.approved || 0}<br>
                               Debug Mode: ${data.debug.debug_mode ? 'ON' : 'OFF'}<br>
                               Search: "${data.debug.search}"
                           </small>
                       </div>
                   ` : ''}
               </div>
           `;
           return;
       }
       
       let html = '';
       
       data.guests.forEach(guest => {
           const initials = (guest.name || 'Unknown').split(' ')
               .map(name => name[0] || '')
               .slice(0, 2)
               .join('')
               .toUpperCase() || 'UN';
           
           const approvalDate = guest.approval_timestamp 
               ? new Date(guest.approval_timestamp)
               : (guest.created_at ? new Date(guest.created_at) : new Date());
           
           const timeAgo = formatTimeAgo(approvalDate);
           const fullDate = approvalDate.toLocaleDateString('en-US', {
               year: 'numeric',
               month: 'long',
               day: 'numeric',
               hour: '2-digit',
               minute: '2-digit'
           });
           
           // Determine status badge and display info
           let statusBadge = '';
           let statusInfo = '';
           
           if (guest.status === 'approved') {
               statusBadge = `
                   <div class="status-badge">
                       <div class="status-icon"></div>
                       Currently Approved
                   </div>
               `;
           } else if (guest.status === 'pending') {
               statusBadge = `<span class="badge bg-warning text-dark">Currently Pending</span>`;
               statusInfo = ' (Was approved, now pending)';
           } else if (guest.status === 'denied') {
               statusBadge = `<span class="badge bg-danger">Currently Denied</span>`;
               statusInfo = ' (Was approved, now denied)';
           } else {
               statusBadge = `<span class="badge bg-secondary">${guest.status || 'Unknown'}</span>`;
               statusInfo = guest.status !== 'approved' ? ' (Was approved)' : '';
           }
           
           // Show approval history info
           let approvalInfo = '';
           if (guest.approval_timestamp && guest.status !== 'approved') {
               approvalInfo = `
                   <div class="meta-item">
                       <i class="fas fa-history meta-icon"></i>
                       <span>Was approved: ${new Date(guest.approval_timestamp).toLocaleString()}</span>
                   </div>
               `;
           }
           
           html += `
               <div class="guest-card">
                   <div class="approval-time">
                       <div>${timeAgo}</div>
                       <div style="font-size: 0.7rem; opacity: 0.7;">${fullDate}</div>
                   </div>
                   
                   <div class="guest-main-info">
                       <div class="guest-avatar">${initials}</div>
                       <div class="guest-details">
                           <h3 class="guest-name">
                               ${highlightSearchTerm(guest.name || 'Unknown', currentSearch)}
                               <small class="text-muted" style="font-size: 0.7em;">${statusInfo}</small>
                           </h3>
                           <div class="guest-contact">
                               <i class="fas fa-phone me-1"></i>
                               ${highlightSearchTerm(guest.phone || 'N/A', currentSearch)}
                               ${guest.email ? `<span class="mx-2">•</span><i class="fas fa-envelope me-1"></i>${highlightSearchTerm(guest.email, currentSearch)}` : ''}
                           </div>
                           ${statusBadge}
                       </div>
                   </div>
                   
                   <div class="guest-meta">
                       <div class="meta-item">
                           <i class="fas fa-user-shield meta-icon"></i>
                           <span>Host: ${highlightSearchTerm(guest.host_name || 'N/A', currentSearch)}</span>
                       </div>
                       ${guest.organization ? `
                           <div class="meta-item">
                               <i class="fas fa-building meta-icon"></i>
                               <span>Organization: ${highlightSearchTerm(guest.organization, currentSearch)}</span>
                           </div>
                       ` : ''}
                       ${guest.reason ? `
                           <div class="meta-item">
                               <i class="fas fa-info-circle meta-icon"></i>
                               <span>Purpose: ${highlightSearchTerm(guest.reason, currentSearch)}</span>
                           </div>
                       ` : ''}
                       ${guest.floor_of_visit ? `
                           <div class="meta-item">
                               <i class="fas fa-map-marker-alt meta-icon"></i>
                               <span>Floor: ${guest.floor_of_visit}</span>
                           </div>
                       ` : ''}
                       ${approvalInfo}
                       ${guest.check_in_time && guest.check_in_time !== '' ? `
                           <div class="meta-item">
                               <i class="fas fa-sign-in-alt meta-icon"></i>
                               <span>Checked in: ${new Date(guest.check_in_time).toLocaleString()}</span>
                           </div>
                       ` : ''}
                       ${guest.check_out_time && guest.check_out_time !== '' ? `
                           <div class="meta-item">
                               <i class="fas fa-sign-out-alt meta-icon"></i>
                               <span>Checked out: ${new Date(guest.check_out_time).toLocaleString()}</span>
                           </div>
                       ` : ''}
                       ${guest.receptionist_name ? `
                           <div class="meta-item">
                               <i class="fas fa-user-tie meta-icon"></i>
                               <span>Registered by: ${guest.receptionist_name}</span>
                           </div>
                       ` : ''}
                   </div>
               </div>
           `;
       });
       
       // Add pagination
       if (data.pages > 1) {
           html += generatePagination(data);
       }
       
       container.innerHTML = html;
   }

   function goToPage(page) {
       currentPage = page;
       const debugMode = window.currentDebugMode || false;
       fetchGuestHistory(page, currentSearch, debugMode);
   }

   function generatePagination(data) {
       let pagination = `
           <div class="pagination-container">
               <div class="pagination-info">
                   Showing ${((data.page - 1) * data.limit) + 1}-${Math.min(data.page * data.limit, data.total)} of ${data.total} guests
               </div>
               <div class="pagination-controls">
       `;
       
       // Previous button
       pagination += `
           <button class="page-btn" ${data.page <= 1 ? 'disabled' : ''} onclick="goToPage(${data.page - 1})">
               <i class="fas fa-chevron-left"></i>
           </button>
       `;
       
       // Page numbers
       const startPage = Math.max(1, data.page - 2);
       const endPage = Math.min(data.pages, data.page + 2);
       
       if (startPage > 1) {
           pagination += `<button class="page-btn" onclick="goToPage(1)">1</button>`;
           if (startPage > 2) {
               pagination += `<span class="page-btn" disabled>...</span>`;
           }
       }
       
       for (let i = startPage; i <= endPage; i++) {
           pagination += `
               <button class="page-btn ${i === data.page ? 'active' : ''}" onclick="goToPage(${i})">
                   ${i}
               </button>
           `;
       }
       
       if (endPage < data.pages) {
           if (endPage < data.pages - 1) {
               pagination += `<span class="page-btn" disabled>...</span>`;
           }
           pagination += `<button class="page-btn" onclick="goToPage(${data.pages})">${data.pages}</button>`;
       }
       
       // Next button
       pagination += `
           <button class="page-btn" ${data.page >= data.pages ? 'disabled' : ''} onclick="goToPage(${data.page + 1})">
               <i class="fas fa-chevron-right"></i>
           </button>
       `;
       
       pagination += `
               </div>
           </div>
       `;
       
       return pagination;
   }

   // Search functionality
   function setupHistorySearch() {
       const searchInput = document.getElementById('historySearch');
       const clearButton = document.getElementById('clearSearch');
       
       if (!searchInput || !clearButton) return;
       
       searchInput.addEventListener('input', function() {
           const value = this.value.trim();
           
           // Show/hide clear button
           if (value) {
               clearButton.classList.add('show');
           } else {
               clearButton.classList.remove('show');
           }
           
           // Debounce search
           clearTimeout(searchTimeout);
           searchTimeout = setTimeout(() => {
               currentSearch = value;
               currentPage = 1;
               const debugMode = window.currentDebugMode || false;
               fetchGuestHistory(1, value, debugMode);
           }, 300);
       });
       
       clearButton.addEventListener('click', function() {
           searchInput.value = '';
           searchInput.focus();
           this.classList.remove('show');
           currentSearch = '';
           currentPage = 1;
           const debugMode = window.currentDebugMode || false;
           fetchGuestHistory(1, '', debugMode);
       });
   }

   function formatTimeAgo(date) {
       const now = new Date();
       const diff = now - date;
       const seconds = Math.floor(diff / 1000);
       const minutes = Math.floor(seconds / 60);
       const hours = Math.floor(minutes / 60);
       const days = Math.floor(hours / 24);
       
       if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
       if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
       if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
       return 'Just now';
   }

   function highlightSearchTerm(text, searchTerm) {
       if (!searchTerm || !text) return text;
       
       const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
       return text.replace(regex, '<span class="search-highlight">$1</span>');
   }

   // Improved SSE setup with better error handling
   function setupEventSource() {
       if (eventSource) {
           eventSource.close();
           eventSource = null;
       }
       
       console.log('Setting up EventSource for real-time notifications...');
       
       try {
           eventSource = new EventSource('?sse=1&last_check=' + Math.floor(Date.now()/1000));
           
           eventSource.onopen = function(e) {
               console.log('SSE connection opened');
           };
           
           eventSource.onmessage = function(e) {
               try {
                   const data = JSON.parse(e.data);
                   console.log('SSE message received:', data);
                   
                   if (data.type === 'new_request') {
                       showNotification(data);
                       // Trigger immediate refresh when new request comes in
                       setTimeout(() => {
                           refreshAll();
                       }, 500);
                   }
               } catch (error) {
                   console.error('Error parsing SSE message:', error);
               }
           };

           eventSource.onerror = function(e) {
               console.log('SSE connection error:', e);
               
               if (eventSource) {
                   eventSource.close();
                   eventSource = null;
               }
               
               // Reconnect after 10 seconds
               setTimeout(() => {
                   console.log('Attempting to reconnect SSE...');
                   setupEventSource();
               }, 10000);
           };
           
       } catch (error) {
           console.error('Error setting up EventSource:', error);
           // Fallback to polling if SSE fails
           setTimeout(setupEventSource, 15000);
       }
   }

   function showNotification(data) {
       // Try to play notification sound
       try {
           const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEeAz2b2u/CaSUEK4LN8tuNOQYZa77ry6FCBQQ6nts9OTY4kNXpRCkGLoa1pQYNcLgaLo0iKlJr9cNKTqg5p8+xkJsFGHXnJVJsYXdKFJNGkLqQhAAGcLcbLY0hLFRn9cJMRKs3p8+1jJ4FGXbnJFVpYXdJEZNJxsKo2EtjVMuxfJDanCCDTskOaKbDWZUNLLkZcqzMhZe9rLm0XP5WVqmKaNHq6+L97YdNQQb0qM6xhAa+WnFVzpFtGwZm8PejppCqNOGGQjsGM5jGiJWRIDGOZXFJzpFcF++2g39EaXGEHhNJaGXBDCqIdEKhgaKdYr8ORKZnk3UeK8WyKC4oOWnQ8h8CXZNPNgSKdEKhhKOiYsANTqJklnc=');
           audio.play().catch(e => console.log('Audio play failed:', e));
       } catch (e) {
           console.log('Audio creation failed:', e);
       }
       
       // Create and show toast notification
       const toastId = 'toast-' + Date.now();
       const toast = document.createElement('div');
       toast.id = toastId;
       toast.className = 'position-fixed bottom-0 end-0 p-3';
       toast.style.zIndex = '9999';
       toast.innerHTML = `
           <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 300px;">
               <div class="toast-header" style="background-color: var(--primary-color); color: white;">
                   <i class="fas fa-bell me-2"></i>
                   <strong class="me-auto">New Visitor Request</strong>
                   <small>Just now</small>
                   <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('${toastId}').remove()" aria-label="Close"></button>
               </div>
               <div class="toast-body">
                   <i class="fas fa-user-plus me-2 text-primary"></i>
                   ${data.message}
                   <div class="mt-2 pt-2 border-top">
                       <button onclick="document.getElementById('${toastId}').remove(); refreshAll();" class="btn btn-sm btn-primary">
                           <i class="fas fa-eye me-1"></i>View Requests
                       </button>
                   </div>
               </div>
           </div>
       `;
       
       document.body.appendChild(toast);
       
       // Auto-remove after 15 seconds
       setTimeout(() => {
           const element = document.getElementById(toastId);
           if (element) {
               element.remove();
           }
       }, 15000);
   }

   // Initialize based on active tab
   document.addEventListener('DOMContentLoaded', function() {
       console.log('DOM loaded, initializing dashboard...');
       
       const activeTab = document.querySelector('.nav-link.active');
       if (activeTab) {
           const tabName = activeTab.textContent.trim();
           console.log('Active tab:', tabName);
           
           if (tabName === 'Approvals') {
               // Initialize approvals functionality
               console.log('Initializing approvals tab...');
               
               // Start auto-refresh
               setTimeout(() => {
                   fetchVisitorList();
                   fetchStats();
                   setupEventSource();
               }, 500);
           } else if (tabName === 'Guest History') {
               // Initialize history functionality
               console.log('Initializing history tab...');
               setupHistorySearch();
               setTimeout(() => {
                   fetchGuestHistory(1, '');
               }, 500);
           }
       }
   });

   // Handle page visibility changes to pause/resume refresh
   document.addEventListener('visibilitychange', function() {
       if (document.hidden) {
           // Page is hidden, pause auto-refresh
           console.log('Page hidden, pausing auto-refresh');
           if (visitorRefreshTimeout) clearTimeout(visitorRefreshTimeout);
           if (statsRefreshTimeout) clearTimeout(statsRefreshTimeout);
           if (eventSource) {
               eventSource.close();
               eventSource = null;
           }
       } else {
           // Page is visible, resume auto-refresh
           console.log('Page visible, resuming auto-refresh');
           const activeTab = document.querySelector('.nav-link.active');
           if (activeTab && activeTab.textContent.trim() === 'Approvals') {
               setTimeout(() => {
                   refreshAll();
               }, 1000);
           }
       }
   });

   // Handle window beforeunload to cleanup
   window.addEventListener('beforeunload', function() {
       if (visitorRefreshTimeout) clearTimeout(visitorRefreshTimeout);
       if (statsRefreshTimeout) clearTimeout(statsRefreshTimeout);
       if (eventSource) {
           eventSource.close();
           eventSource = null;
       }
   });

   // Make refresh functions globally available for debugging
   window.manualRefreshVisitors = fetchVisitorList;
   window.manualRefreshStats = fetchStats;
   window.manualRefreshAll = refreshAll;

   // Make history functions globally available
   window.fetchGuestHistory = fetchGuestHistory;
   window.goToPage = goToPage;

</script>

</body>
</html>