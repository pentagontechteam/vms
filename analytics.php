<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.html");
    exit();
}

// Check if user role exists in session, if not fetch from database
if (!isset($_SESSION['employee_role'])) {
    $rec_id = $_SESSION['employee_id'];
    $stmt = $conn->prepare("SELECT role FROM employees WHERE id = ?");
    $stmt->bind_param("i", $rec_id);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $_SESSION['employee_role'] = $role;
    $stmt->close();
}

// Check if user is a super user
$user_role = $_SESSION['employee_role'] ?? 'staff';
if ($user_role !== 'super_user') {
    // Redirect to dashboard with error message
    header("Location: staff_dashboard.php?error=access_denied");
    exit();
}

// Function to get date range based on period
function getDateRange($period) {
    switch ($period) {
        case 'week':
            return [
                'start' => date('Y-m-d', strtotime('monday this week')),
                'end' => date('Y-m-d', strtotime('sunday this week'))
            ];
        case 'month':
            return [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ];
        case 'quarter':
            $quarter = ceil(date('n') / 3);
            $start_month = ($quarter - 1) * 3 + 1;
            return [
                'start' => date('Y-' . sprintf('%02d', $start_month) . '-01'),
                'end' => date('Y-m-t', strtotime(date('Y') . '-' . sprintf('%02d', $start_month + 2) . '-01'))
            ];
        default: // today
            return [
                'start' => date('Y-m-d'),
                'end' => date('Y-m-d')
            ];
    }
}

// Get selected period
$period = $_GET['period'] ?? 'today';
$dateRange = getDateRange($period);

// AJAX endpoint for data
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'metrics':
            $metrics = [];

            // Total visitors for period
            $result = $conn->query("
                SELECT COUNT(*) as total 
                FROM visitors 
                WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
            ");
            $metrics['totalVisitors'] = $result->fetch_assoc()['total'];

            // Currently inside (checked in but not checked out)
            $result = $conn->query("
                SELECT COUNT(*) as total 
                FROM visitors 
                WHERE status = 'checked_in' AND check_out_time IS NULL
            ");
            $metrics['currentlyInside'] = $result->fetch_assoc()['total'];

            // Pending approvals
            $result = $conn->query("
                SELECT COUNT(*) as total 
                FROM visitors 
                WHERE status = 'pending'
            ");
            $metrics['pendingApprovals'] = $result->fetch_assoc()['total'];

            // Security alerts (using rejected visitors and suspicious patterns)
            $result = $conn->query("
                SELECT COUNT(*) as total 
                FROM visitors 
                WHERE (status = 'rejected' OR status = 'denied') 
                AND DATE(created_at) = CURDATE()
            ");
            $metrics['securityAlerts'] = $result->fetch_assoc()['total'];

            echo json_encode($metrics);
            break;

        case 'traffic_trend':
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dayName = date('D', strtotime($date));

                // Total visitors
                $result = $conn->query("
                    SELECT COUNT(*) as total 
                    FROM visitors 
                    WHERE DATE(created_at) = '$date'
                ");
                $total = $result->fetch_assoc()['total'];

                // Security alerts (rejected/denied)
                $result = $conn->query("
                    SELECT COUNT(*) as alerts 
                    FROM visitors 
                    WHERE DATE(created_at) = '$date' 
                    AND (status = 'rejected' OR status = 'denied')
                ");
                $alerts = $result->fetch_assoc()['alerts'];

                $data[] = [
                    'date' => $date,
                    'day' => $dayName,
                    'visitors' => (int)$total,
                    'alerts' => (int)$alerts
                ];
            }
            echo json_encode($data);
            break;

        case 'visitor_types':
            // Categorize visitors by organization or purpose
            $result = $conn->query("
                SELECT 
                    CASE 
                        WHEN LOWER(organization) LIKE '%bank%' OR LOWER(organization) LIKE '%financial%' THEN 'Business'
                        WHEN LOWER(reason) LIKE '%delivery%' OR LOWER(reason) LIKE '%courier%' THEN 'Delivery'
                        WHEN LOWER(reason) LIKE '%maintenance%' OR LOWER(reason) LIKE '%repair%' THEN 'Maintenance'
                        WHEN LOWER(organization) LIKE '%government%' OR LOWER(organization) LIKE '%official%' THEN 'Official'
                        ELSE 'Other'
                    END as visitor_type,
                    COUNT(*) as count
                FROM visitors 
                WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
                AND status IN ('approved', 'checked_in', 'checked_out')
                GROUP BY visitor_type
                ORDER BY count DESC
            ");

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode($data);
            break;

        case 'peak_hours':
            $result = $conn->query("
                SELECT 
                    HOUR(check_in_time) as hour,
                    COUNT(*) as checkins,
                    (SELECT COUNT(*) FROM visitors v2 WHERE HOUR(v2.check_out_time) = HOUR(v1.check_in_time) AND DATE(v2.check_out_time) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}') as checkouts
                FROM visitors v1
                WHERE DATE(check_in_time) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
                AND check_in_time IS NOT NULL
                GROUP BY HOUR(check_in_time)
                ORDER BY hour
            ");

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'hour' => (int)$row['hour'],
                    'checkins' => (int)$row['checkins'],
                    'checkouts' => (int)$row['checkouts']
                ];
            }
            echo json_encode($data);
            break;

        case 'floor_heatmap':
            $result = $conn->query("
                SELECT 
                    floor_of_visit,
                    HOUR(check_in_time) as hour,
                    COUNT(*) as count
                FROM visitors 
                WHERE DATE(check_in_time) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
                AND check_in_time IS NOT NULL 
                AND floor_of_visit IS NOT NULL
                GROUP BY floor_of_visit, HOUR(check_in_time)
                ORDER BY floor_of_visit, hour
            ");

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'floor' => $row['floor_of_visit'],
                    'hour' => (int)$row['hour'],
                    'count' => (int)$row['count']
                ];
            }
            echo json_encode($data);
            break;

        case 'status_distribution':
            $result = $conn->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM visitors 
                WHERE DATE(created_at) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
                GROUP BY status
                ORDER BY count DESC
            ");

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode($data);
            break;

        case 'visit_duration':
            $result = $conn->query("
                SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) < 30 THEN '< 30min'
                        WHEN TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) < 60 THEN '30min-1h'
                        WHEN TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) < 120 THEN '1h-2h'
                        WHEN TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) < 240 THEN '2h-4h'
                        WHEN TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) < 360 THEN '4h-6h'
                        ELSE '6h+'
                    END as duration_range,
                    COUNT(*) as count
                FROM visitors 
                WHERE check_in_time IS NOT NULL 
                AND check_out_time IS NOT NULL
                AND DATE(check_in_time) BETWEEN '{$dateRange['start']}' AND '{$dateRange['end']}'
                GROUP BY duration_range
                ORDER BY 
                    CASE duration_range
                        WHEN '< 30min' THEN 1
                        WHEN '30min-1h' THEN 2
                        WHEN '1h-2h' THEN 3
                        WHEN '2h-4h' THEN 4
                        WHEN '4h-6h' THEN 5
                        WHEN '6h+' THEN 6
                    END
            ");

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode($data);
            break;

        case 'recent_activity':
            $result = $conn->query("
                SELECT 
                    name,
                    status,
                    floor_of_visit,
                    host_name,
                    check_in_time,
                    check_out_time,
                    TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) as duration_minutes,
                    created_at,
                    CASE 
                        WHEN status = 'rejected' OR status = 'denied' THEN 1
                        WHEN TIMESTAMPDIFF(HOUR, check_in_time, NOW()) > 8 AND check_out_time IS NULL THEN 1
                        ELSE 0
                    END as flagged
                FROM visitors 
                WHERE DATE(created_at) = CURDATE()
                ORDER BY 
                    CASE 
                        WHEN check_out_time IS NOT NULL THEN check_out_time
                        WHEN check_in_time IS NOT NULL THEN check_in_time
                        ELSE created_at
                    END DESC
                LIMIT 10
            ");

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode($data);
            break;
    }

    $conn->close();
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #07AF8B;
            --primary-dark: #007570;
            --accent: #FFCA00;
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #198754;
            --info: #0dcaf0;
            --dark: #212529;
            --light: #f8f9fa;
            --border-radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12);
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .metric-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .metric-card.danger {
            border-left-color: var(--danger);
        }

        .metric-card.warning {
            border-left-color: var(--warning);
        }

        .metric-card.success {
            border-left-color: var(--success);
        }

        .metric-card.info {
            border-left-color: var(--info);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .metric-card.danger .metric-value {
            color: var(--danger);
        }

        .metric-card.warning .metric-value {
            color: var(--warning);
        }

        .metric-card.success .metric-value {
            color: var(--success);
        }

        .metric-card.info .metric-value {
            color: var(--info);
        }

        .metric-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: 450px;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .canvas-container {
            position: relative;
            height: 350px;
        }

        .time-filter {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .btn-filter {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
        }

        .btn-filter:hover,
        .btn-filter.active {
            background: var(--primary);
            color: white;
        }

        .security-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-normal {
            background: var(--success);
        }

        .status-warning {
            background: var(--warning);
        }

        .status-critical {
            background: var(--danger);
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
        }

        .visitor-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .visitor-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }

        .visitor-item.flagged {
            border-left-color: var(--danger);
            background: rgba(220, 53, 69, 0.02);
        }

        .refresh-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .heatmap-table {
            font-size: 0.8rem;
        }

        .heatmap-cell {
            min-width: 25px;
            height: 30px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .dashboard-title {
                font-size: 2rem;
            }

            .metric-value {
                font-size: 2rem;
            }

            .chart-container {
                height: 350px;
            }

            .canvas-container {
                height: 250px;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="dashboard-title">
                        <i class="bi bi-graph-up me-3"></i>Analytics
                    </h1>
                </div>
            </div>
        </div>
        <!--<div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="dashboard-title">
                        <i class="bi bi-shield-check me-3"></i>Analytics
                    </h1>
                    <p class="dashboard-subtitle">Real-time insights</p>
                </div>-->
        <!--<div class="col-md-4 text-end">
                    <div class="security-status" id="securityStatus">
                        <span class="status-indicator status-normal"></span>
                        Security Status: Normal
                    </div>
                    <small class="d-block mt-1" id="lastUpdate">Loading...</small>
                </div>-->
    </div>
    </div>
    </div>

    <div class="container-fluid">
        <!-- Time Filter -->
        <div class="time-filter">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar3 me-2"></i>
                        Time Period
                    </h5>
                </div>
                <div class="col-md-6 text-end">
                    <a href="staff_dashboard.php" class="btn btn-outline-primary me-2" style="color: #07AF8B !important; border-color: #07AF8B !important;"
                        onmouseover="this.style.backgroundColor='#07AF8B'; this.style.color='white';"
                        onmouseout="this.style.backgroundColor='transparent'; this.style.color='#07AF8B';">
                        <i class="bi bi-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    <button class="refresh-btn" onclick="refreshDashboard()">
                        <i class="bi bi-arrow-clockwise"></i>
                        Refresh
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn-filter active" data-period="today">Today</button>
                <button class="btn-filter" data-period="week">This Week</button>
                <button class="btn-filter" data-period="month">This Month</button>
                <button class="btn-filter" data-period="quarter">Quarter</button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4" justify-content-center" id="metricsContainer">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="metric-card">
                    <div class="metric-value" id="totalVisitors">Loading...</div>
                    <div class="metric-label">
                        <i class="bi bi-people me-1"></i>
                        Total Visitors
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="metric-card info">
                    <div class="metric-value" id="currentlyInside">Loading...</div>
                    <div class="metric-label">
                        <i class="bi bi-building me-1"></i>
                        Currently Inside
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="metric-card warning">
                    <div class="metric-value" id="pendingApprovals">Loading...</div>
                    <div class="metric-label">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Pending Approvals
                    </div>
                </div>
            </div>
            <!--<div class="col-lg-3 col-md-6 mb-3">
                <div class="metric-card danger">
                    <div class="metric-value" id="securityAlerts">Loading...</div>
                    <div class="metric-label">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        Security Alerts
                    </div>
                </div>
            </div>-->
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-lg-12 mb-3">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="bi bi-graph-up"></i>
                        Visitor Traffic Trends (Last 7 Days)
                    </h3>
                    <div class="canvas-container">
                        <canvas id="trafficTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="bi bi-building"></i>
                        Floor Occupancy Heatmap
                    </h3>
                    <div id="floorHeatmap" class="mt-3"></div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="bi bi-clock-history"></i>
                        Peak Hours Analysis
                    </h3>
                    <div class="canvas-container">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="bi bi-person-badge"></i>
                        Visitor Status Distribution
                    </h3>
                    <div class="canvas-container">
                        <canvas id="statusDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="bi bi-stopwatch"></i>
                        Visit Duration Analysis
                    </h3>
                    <div class="canvas-container">
                        <canvas id="visitDurationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="bi bi-activity"></i>
                        Recent Visitor Activity
                    </h3>
                    <div class="visitor-list" id="recentActivity">
                        Loading recent activity...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let charts = {};
        let currentPeriod = 'today';

        // Color scheme
        const colors = {
            primary: '#07AF8B',
            primaryDark: '#007570',
            accent: '#FFCA00',
            danger: '#dc3545',
            warning: '#ffc107',
            success: '#198754',
            info: '#0dcaf0'
        };

        // Chart defaults
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#212529';

        // Fetch data from API
        async function fetchData(endpoint) {
            try {
                const response = await fetch(`?ajax=${endpoint}&period=${currentPeriod}`);
                return await response.json();
            } catch (error) {
                console.error(`Error fetching ${endpoint}:`, error);
                return null;
            }
        }

        // Update metrics
        async function updateMetrics() {
            const data = await fetchData('metrics');
            if (data) {
                document.getElementById('totalVisitors').textContent = data.totalVisitors;
                document.getElementById('currentlyInside').textContent = data.currentlyInside;
                document.getElementById('pendingApprovals').textContent = data.pendingApprovals;
                document.getElementById('securityAlerts').textContent = data.securityAlerts;

                updateSecurityStatus(data.securityAlerts);
            }
        }

        // Update security status
        function updateSecurityStatus(alertCount) {
            const statusElement = document.getElementById('securityStatus');
            let statusClass, statusText;

            if (alertCount === 0) {
                statusClass = 'status-normal';
                statusText = 'Normal';
            } else if (alertCount <= 3) {
                statusClass = 'status-warning';
                statusText = 'Caution';
            } else {
                statusClass = 'status-critical';
                statusText = 'Alert';
            }

            statusElement.innerHTML = `
                <span class="status-indicator ${statusClass}"></span>
                Security Status: ${statusText}
            `;
        }

        // Traffic Trend Chart
        async function updateTrafficTrendChart() {
            const data = await fetchData('traffic_trend');
            if (!data) return;

            const ctx = document.getElementById('trafficTrendChart').getContext('2d');

            if (charts.trafficTrend) {
                charts.trafficTrend.destroy();
            }

            charts.trafficTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.day),
                    datasets: [{
                        label: 'Total Visitors',
                        data: data.map(d => d.visitors),
                        borderColor: colors.primary,
                        backgroundColor: colors.primary + '20',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Visitor Types Chart
        async function updateVisitorTypesChart() {
            const data = await fetchData('visitor_types');
            if (!data) return;

            const ctx = document.getElementById('visitorTypesChart').getContext('2d');

            if (charts.visitorTypes) {
                charts.visitorTypes.destroy();
            }

            charts.visitorTypes = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.visitor_type),
                    datasets: [{
                        data: data.map(d => d.count),
                        backgroundColor: [
                            colors.primary,
                            colors.accent,
                            colors.info,
                            colors.warning,
                            colors.success
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Peak Hours Chart
        async function updatePeakHoursChart() {
            const data = await fetchData('peak_hours');
            if (!data) return;

            const ctx = document.getElementById('peakHoursChart').getContext('2d');

            if (charts.peakHours) {
                charts.peakHours.destroy();
            }

            // Create 24-hour array with data
            const hours = Array.from({
                length: 24
            }, (_, i) => i);
            const checkins = hours.map(hour => {
                const found = data.find(d => d.hour === hour);
                return found ? found.checkins : 0;
            });
            const checkouts = hours.map(hour => {
                const found = data.find(d => d.hour === hour);
                return found ? found.checkouts : 0;
            });

            charts.peakHours = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hours.map(h => h + ':00'),
                    datasets: [{
                        label: 'Check-ins',
                        data: checkins,
                        backgroundColor: colors.primary + '80',
                        borderColor: colors.primary
                    }, {
                        label: 'Check-outs',
                        data: checkouts,
                        backgroundColor: colors.accent + '80',
                        borderColor: colors.accent
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Floor Heatmap - Updated with vertical scrolling
        async function updateFloorHeatmap() {
            const data = await fetchData('floor_heatmap');
            if (!data) return;

            const floors = [...new Set(data.map(d => d.floor))].sort();
            const hours = Array.from({
                length: 24
            }, (_, i) => i);

            // Create scrollable container with fixed height and hidden scrollbars
            let html = '<div class="heatmap-scroll-container" style="max-height: 350px; overflow-y: auto; overflow-x: auto; border: 1px solid #dee2e6; border-radius: 8px; scrollbar-width: none; -ms-overflow-style: none;">';
            html += '<table class="table table-sm heatmap-table mb-0" style="min-width: max-content;">';
            html += '<thead class="sticky-top" style="background-color: white; z-index: 10;"><tr><th style="position: sticky; left: 0; background-color: white; z-index: 11; border-right: 2px solid #dee2e6;">Floor</th>';

            hours.forEach(hour => {
                html += `<th class="text-center" style="min-width: 40px;">${hour}</th>`;
            });
            html += '</tr></thead><tbody>';

            floors.forEach(floor => {
                html += `<tr><td style="position: sticky; left: 0; background-color: white; font-weight: bold; border-right: 2px solid #dee2e6; z-index: 5;">${floor}</td>`;

                hours.forEach(hour => {
                    const found = data.find(d => d.floor === floor && d.hour === hour);
                    const count = found ? found.count : 0;

                    let bgColor = '#f8f9fa';
                    let textColor = '#212529';
                    if (count > 0) {
                        const intensity = Math.min(count / 10, 1);
                        bgColor = `rgba(7, 175, 139, ${0.1 + intensity * 0.7})`;
                        // Use white text for higher intensity cells
                        if (intensity > 0.5) {
                            textColor = 'white';
                        }
                    }

                    html += `<td class="heatmap-cell text-center" style="background-color: ${bgColor}; color: ${textColor}; min-width: 40px; height: 35px; vertical-align: middle; border: 1px solid #dee2e6; cursor: pointer;" title="Floor: ${floor}, Hour: ${hour}:00, Count: ${count}">${count > 0 ? count : ''}</td>`;
                });

                html += '</tr>';
            });

            html += '</tbody></table></div>';

            // Add CSS to hide webkit scrollbars
            html += `<style>
        .heatmap-scroll-container::-webkit-scrollbar {
            display: none;
        }
        .heatmap-scroll-container {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer and Edge */
        }
    </style>`;

            // Add a small legend below the heatmap
            html += '<div class="mt-2 d-flex align-items-center justify-content-center flex-wrap gap-2">';
            html += '<small class="text-muted me-2">Intensity:</small>';
            for (let i = 0; i <= 4; i++) {
                const intensity = i / 4;
                const bgColor = i === 0 ? '#f8f9fa' : `rgba(7, 175, 139, ${0.1 + intensity * 0.7})`;
                const textColor = intensity > 0.5 ? 'white' : '#212529';
                html += `<span class="badge" style="background-color: ${bgColor}; color: ${textColor}; border: 1px solid #dee2e6;">${i === 0 ? '0' : `${i * 2}+`}</span>`;
            }
            html += '</div>';

            document.getElementById('floorHeatmap').innerHTML = html;
        }

        // Status Distribution Chart
        async function updateStatusDistributionChart() {
            const data = await fetchData('status_distribution');
            if (!data) return;

            const ctx = document.getElementById('statusDistributionChart').getContext('2d');

            if (charts.statusDistribution) {
                charts.statusDistribution.destroy();
            }

            const statusColors = {
                'approved': colors.success,
                'pending': colors.warning,
                'checked_in': colors.info,
                'checked_out': colors.primary,
                'rejected': colors.danger,
                'denied': colors.danger
            };

            charts.statusDistribution = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                    datasets: [{
                        data: data.map(d => d.count),
                        backgroundColor: data.map(d => statusColors[d.status] || colors.primary),
                        borderColor: data.map(d => statusColors[d.status] || colors.primary)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Visit Duration Chart
        async function updateVisitDurationChart() {
            const data = await fetchData('visit_duration');
            if (!data) return;

            const ctx = document.getElementById('visitDurationChart').getContext('2d');

            if (charts.visitDuration) {
                charts.visitDuration.destroy();
            }

            // Ensure proper order
            const orderedDurations = ['< 30min', '30min-1h', '1h-2h', '2h-4h', '4h-6h', '6h+'];
            const orderedData = orderedDurations.map(duration => {
                const found = data.find(d => d.duration_range === duration);
                return found ? found.count : 0;
            });

            charts.visitDuration = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: orderedDurations,
                    datasets: [{
                        label: 'Number of Visits',
                        data: orderedData,
                        borderColor: colors.primary,
                        backgroundColor: colors.primary + '20',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Recent Activity
        async function updateRecentActivity() {
            const data = await fetchData('recent_activity');
            if (!data) return;

            let html = '';
            data.forEach(visitor => {
                const flaggedClass = visitor.flagged == 1 ? 'flagged' : '';
                let statusBadge = '';
                let timeInfo = '';

                switch (visitor.status) {
                    case 'checked_in':
                        statusBadge = '<span class="badge bg-success ms-2">Checked In</span>';
                        timeInfo = `<div class="small text-muted">Floor: ${visitor.floor_of_visit || 'Unknown'} - Host: ${visitor.host_name || 'Unknown'}</div>`;
                        break;
                    case 'checked_out':
                        statusBadge = '<span class="badge bg-primary ms-2">Checked Out</span>';
                        timeInfo = visitor.duration_minutes ?
                            `<div class="small text-muted">Duration: ${Math.floor(visitor.duration_minutes / 60)}h ${visitor.duration_minutes % 60}m</div>` :
                            '<div class="small text-muted">Duration: Unknown</div>';
                        break;
                    case 'pending':
                        statusBadge = '<span class="badge bg-warning ms-2">Pending</span>';
                        timeInfo = `<div class="small text-muted">Awaiting approval - Host: ${visitor.host_name || 'Unknown'}</div>`;
                        break;
                    case 'approved':
                        statusBadge = '<span class="badge bg-success ms-2">Approved</span>';
                        timeInfo = `<div class="small text-muted">Floor: ${visitor.floor_of_visit || 'Unknown'} - Host: ${visitor.host_name || 'Unknown'}</div>`;
                        break;
                    case 'rejected':
                    case 'denied':
                        statusBadge = '<span class="badge bg-danger ms-2">Rejected</span>';
                        timeInfo = '<div class="small text-muted">Security alert triggered</div>';
                        break;
                }

                const timeAgo = getTimeAgo(visitor.check_out_time || visitor.check_in_time || visitor.created_at);

                html += `
                    <div class="visitor-item ${flaggedClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${visitor.name}</strong>
                                ${statusBadge}
                                ${timeInfo}
                            </div>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                    </div>
                `;
            });

            document.getElementById('recentActivity').innerHTML = html || '<p class="text-muted">No recent activity</p>';
        }

        // Helper function to calculate time ago
        function getTimeAgo(timestamp) {
            if (!timestamp) return 'Unknown';

            const now = new Date();
            const time = new Date(timestamp);
            const diffMs = now - time;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;

            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;

            const diffDays = Math.floor(diffHours / 24);
            return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
        }

        // Update last refresh time
        function updateLastRefreshTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('lastUpdate').textContent = `Last updated: ${timeString}`;
        }

        // Time filter functionality
        document.querySelectorAll('.btn-filter').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                currentPeriod = this.dataset.period;
                refreshDashboard();
            });
        });

        // Refresh dashboard
        async function refreshDashboard() {
            const refreshBtn = document.querySelector('.refresh-btn');
            const originalHTML = refreshBtn.innerHTML;

            refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;

            document.getElementById('metricsContainer').classList.add('loading');

            try {
                await Promise.all([
                    updateMetrics(),
                    updateTrafficTrendChart(),
                    updateVisitorTypesChart(),
                    updatePeakHoursChart(),
                    updateFloorHeatmap(),
                    updateStatusDistributionChart(),
                    updateVisitDurationChart(),
                    updateRecentActivity()
                ]);

                updateLastRefreshTime();
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
            } finally {
                refreshBtn.innerHTML = originalHTML;
                refreshBtn.disabled = false;
                document.getElementById('metricsContainer').classList.remove('loading');
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            updateMetrics();
            updateRecentActivity();
            updateLastRefreshTime();
        }, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        refreshDashboard();
                        break;
                    case '1':
                        e.preventDefault();
                        document.querySelector('[data-period="today"]').click();
                        break;
                    case '2':
                        e.preventDefault();
                        document.querySelector('[data-period="week"]').click();
                        break;
                    case '3':
                        e.preventDefault();
                        document.querySelector('[data-period="month"]').click();
                        break;
                    case '4':
                        e.preventDefault();
                        document.querySelector('[data-period="quarter"]').click();
                        break;
                }
            }
        });

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            refreshDashboard();
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            Object.values(charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        });

        // Export functionality
        function exportData(format = 'json') {
            const data = {
                timestamp: new Date().toISOString(),
                period: currentPeriod,
                metrics: {
                    totalVisitors: document.getElementById('totalVisitors').textContent,
                    currentlyInside: document.getElementById('currentlyInside').textContent,
                    pendingApprovals: document.getElementById('pendingApprovals').textContent,
                    securityAlerts: document.getElementById('securityAlerts').textContent
                }
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `security-analytics-${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>

</html>