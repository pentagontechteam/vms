<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone for consistent date handling
date_default_timezone_set('Africa/Lagos');

// Check if admin is logged in (you may need to adjust this based on your admin authentication)
//if (!isset($_SESSION['admin_id']) && !isset($_SESSION['cso_id'])) {
//    header("Location: admin_login.php");
//    exit();
//}

// Get admin info
$admin_name = "Admin";
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    // Fetch admin name from your admin table
} elseif (isset($_SESSION['cso_id'])) {
    $admin_name = "CSO Admin";
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        if ($_GET['ajax'] == 'dashboard_stats') {
            $today = date('Y-m-d');
            
            // Get comprehensive statistics with error checking
            $stats = [];
            
            // Visitors today
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE DATE(created_at) = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['visitors_today'] = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            // Approved visitors
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'approved'");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['approved_visitors'] = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            // Pending requests
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'pending'");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['pending_requests'] = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            // Checked out today
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out' AND DATE(check_out_time) = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['checked_out'] = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            // Currently inside
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_in'");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats['currently_inside'] = $result->fetch_assoc()['count'] ?? 0;
            $stmt->close();
            
            // Status breakdown for donut chart - Fixed query
            $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM visitors WHERE status IS NOT NULL GROUP BY status ORDER BY count DESC");
            $stmt->execute();
            $status_result = $stmt->get_result();
            $status_data = [];
            while ($row = $status_result->fetch_assoc()) {
                $status_data[] = ['status' => $row['status'], 'count' => (int)$row['count']];
            }
            $stats['status_breakdown'] = $status_data;
            $stmt->close();
            
            // Hourly traffic for today - Fixed query
            $stmt = $conn->prepare("
                SELECT HOUR(created_at) as hour, COUNT(*) as count 
                FROM visitors 
                WHERE DATE(created_at) = ? 
                GROUP BY HOUR(created_at) 
                ORDER BY hour
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $hourly_result = $stmt->get_result();
            
            // Initialize 24-hour array
            $hourly_data = array_fill(0, 24, 0);
            while ($row = $hourly_result->fetch_assoc()) {
                $hourly_data[(int)$row['hour']] = (int)$row['count'];
            }
            $stats['hourly_traffic'] = $hourly_data;
            $stmt->close();
            
            echo json_encode($stats);
            exit();
        }
        
        if ($_GET['ajax'] == 'upcoming_visitors') {
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            
            $stmt = $conn->prepare("
                SELECT name, organization, visit_date, time_of_visit, host_name, status
                FROM visitors 
                WHERE visit_date >= ? AND visit_date <= ?
                AND status IN ('pending', 'approved')
                ORDER BY visit_date, time_of_visit 
                LIMIT 10
            ");
            $stmt->bind_param("ss", $today, $tomorrow);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $upcoming = [];
            while ($row = $result->fetch_assoc()) {
                $upcoming[] = [
                    'name' => $row['name'] ?? '',
                    'organization' => $row['organization'] ?? '',
                    'visit_date' => $row['visit_date'] ?? '',
                    'time_of_visit' => $row['time_of_visit'] ?? '',
                    'host_name' => $row['host_name'] ?? '',
                    'status' => $row['status'] ?? ''
                ];
            }
            $stmt->close();
            
            echo json_encode($upcoming);
            exit();
        }
        
        if ($_GET['ajax'] == 'live_visitors') {
            $stmt = $conn->prepare("
                SELECT name, organization, check_in_time, host_name, floor_of_visit, status
                FROM visitors 
                WHERE status IN ('checked_in', 'approved') 
                ORDER BY check_in_time DESC 
                LIMIT 15
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $live_visitors = [];
            while ($row = $result->fetch_assoc()) {
                $live_visitors[] = [
                    'name' => $row['name'] ?? '',
                    'organization' => $row['organization'] ?? '',
                    'check_in_time' => $row['check_in_time'] ?? '',
                    'host_name' => $row['host_name'] ?? '',
                    'floor_of_visit' => $row['floor_of_visit'] ?? '',
                    'status' => $row['status'] ?? ''
                ];
            }
            $stmt->close();
            
            echo json_encode($live_visitors);
            exit();
        }
        
        if ($_GET['ajax'] == 'activity_feed') {
            $stmt = $conn->prepare("
                SELECT name, status, updated_at, host_name, organization
                FROM visitors 
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                ORDER BY updated_at DESC 
                LIMIT 20
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $activities = [];
            while ($row = $result->fetch_assoc()) {
                $activities[] = [
                    'name' => $row['name'] ?? '',
                    'status' => $row['status'] ?? '',
                    'updated_at' => $row['updated_at'] ?? '',
                    'host_name' => $row['host_name'] ?? '',
                    'organization' => $row['organization'] ?? ''
                ];
            }
            $stmt->close();
            
            echo json_encode($activities);
            exit();
        }
        
        if ($_GET['ajax'] == 'visitor_locations') {
            $stmt = $conn->prepare("
                SELECT name, floor_of_visit, status, check_in_time, organization
                FROM visitors 
                WHERE status = 'checked_in' AND floor_of_visit IS NOT NULL AND floor_of_visit != ''
                ORDER BY check_in_time DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $locations = [];
            while ($row = $result->fetch_assoc()) {
                $locations[] = [
                    'name' => $row['name'] ?? '',
                    'floor_of_visit' => $row['floor_of_visit'] ?? '',
                    'status' => $row['status'] ?? '',
                    'check_in_time' => $row['check_in_time'] ?? '',
                    'organization' => $row['organization'] ?? ''
                ];
            }
            $stmt->close();
            
            echo json_encode($locations);
            exit();
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    
    // If no matching ajax parameter
    http_response_code(400);
    echo json_encode(['error' => 'Invalid AJAX request']);
    exit();
}

// Get basic stats for initial load with error handling
try {
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $visitors_today = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'approved'");
    $stmt->execute();
    $result = $stmt->get_result();
    $approved_visitors = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_requests = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out' AND DATE(check_out_time) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $checked_out = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_in'");
    $stmt->execute();
    $result = $stmt->get_result();
    $currently_inside = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
} catch (Exception $e) {
    // Fallback values if database queries fail
    $visitors_today = 0;
    $approved_visitors = 0;
    $pending_requests = 0;
    $checked_out = 0;
    $currently_inside = 0;
    error_log("Dashboard stats error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AATC Visitor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/admin-dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="bi bi-arrow-clockwise"></i> Refreshing...
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="assets/logo-green-yellow.png" alt="AATC Logo">
            <h4>Admin Dashboard</h4>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="visitors_management.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Visitors</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="employees.php" class="nav-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Employees</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="receptionists.php" class="nav-link">
                    <i class="bi bi-person-workspace"></i>
                    <span>Receptionists</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="floors_offices.php" class="nav-link">
                    <i class="bi bi-building"></i>
                    <span>Offices & Floors</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-tags"></i>
                    <span>Visitor Types</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-camera"></i>
                    <span>Photo Gallery</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-geo-alt"></i>
                    <span>Map View</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="crud_control_panel.php" class="nav-link">
                    <i class="bi bi-database"></i>
                    <span>Database Control</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1>Admin Dashboard</h1>
            </div>
            <div class="topbar-right">
                <div class="notifications">
                    <button class="btn btn-light position-relative">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount">
                            <?= $pending_requests ?>
                        </span>
                    </button>
                </div>
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <?= strtoupper(substr($admin_name, 0, 1)) ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?= htmlspecialchars($admin_name) ?></div>
                        <div class="admin-role">Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-number" id="visitorsToday"><?= $visitors_today ?></div>
                    <div class="stat-label">Visitors Today</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-check-circle stat-icon"></i>
                    <div class="stat-number" id="approvedVisitors"><?= $approved_visitors ?></div>
                    <div class="stat-label">Approved Visitors</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-clock stat-icon"></i>
                    <div class="stat-number" id="pendingRequests"><?= $pending_requests ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-box-arrow-right stat-icon"></i>
                    <div class="stat-number" id="checkedOut"><?= $checked_out ?></div>
                    <div class="stat-label">Checked Out</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-geo-alt stat-icon"></i>
                    <div class="stat-number" id="currentlyInside"><?= $currently_inside ?></div>
                    <div class="stat-label">Currently Inside</div>
                </div>
            </div>

            <!-- Charts and Widgets -->
            <div class="widget-grid">
                <!-- Visitor Status Donut Chart -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Visitor Status</h3>
                    </div>
                    <div class="widget-content">
                        <canvas id="statusChart" width="400" height="300"></canvas>
                    </div>
                </div>

                <!-- Traffic Chart -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Today's Traffic</h3>
                    </div>
                    <div class="widget-content">
                        <canvas id="trafficChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Additional Widgets -->
            <div class="widget-grid">
                <!-- Upcoming Visitors -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Upcoming Visitors</h3>
                    </div>
                    <div class="widget-content">
                        <div id="upcomingVisitors" class="loading">
                            <div class="spinner"></div>
                            Loading upcoming visitors...
                        </div>
                    </div>
                </div>

                <!-- Live Visitors Log -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Live Visitors</h3>
                    </div>
                    <div class="widget-content">
                        <div id="liveVisitors" class="loading">
                            <div class="spinner"></div>
                            Loading live visitors...
                        </div>
                    </div>
                </div>
            </div>

            <div class="widget-grid">
                <!-- Activity Feed -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Recent Activity</h3>
                    </div>
                    <div class="widget-content">
                        <div id="activityFeed" class="loading">
                            <div class="spinner"></div>
                            Loading activity...
                        </div>
                    </div>
                </div>

                <!-- Visitor Locations Map -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Visitor Locations</h3>
                    </div>
                    <div class="widget-content">
                        <div id="visitorLocations" class="loading">
                            <div class="spinner"></div>
                            Loading locations...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="register_walkin.php" class="quick-action-btn">
                    <i class="bi bi-person-plus"></i>
                    Add Visitor
                </a>
                <a href="export_visitors.php" class="quick-action-btn">
                    <i class="bi bi-download"></i>
                    Export Data
                </a>
                <button class="quick-action-btn" onclick="printBadge()">
                    <i class="bi bi-printer"></i>
                    Print Badge
                </button>
                <a href="scanner_page.php" class="quick-action-btn">
                    <i class="bi bi-qr-code-scan"></i>
                    Scan QR
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/admin-dashboard.js"></script>
    
    <!-- Debug Console Output -->
    <script>
        console.log('Dashboard loaded at:', new Date().toLocaleString());
        console.log('Initial stats loaded:', {
            visitorsToday: <?= $visitors_today ?>,
            approvedVisitors: <?= $approved_visitors ?>,
            pendingRequests: <?= $pending_requests ?>,
            checkedOut: <?= $checked_out ?>,
            currentlyInside: <?= $currently_inside ?>
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>