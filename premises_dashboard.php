<?php
// premises_dashboard.php - Management overview
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
$today = date('Y-m-d');

// Get today's summary
$premises_query = "SELECT total_entries FROM daily_premises_entries WHERE entry_date = '$today'";
$premises_result = $conn->query($premises_query);
$premises_count = $premises_result->fetch_assoc()['total_entries'] ?? 0;

$office_query = "
    SELECT COUNT(*) as count 
    FROM visitors 
    WHERE DATE(COALESCE(check_in_time, created_at)) = '$today' 
    AND status IN ('checked_in', 'checked_out', 'approved')
";
$office_result = $conn->query($office_query);
$office_count = $office_result->fetch_assoc()['count'] ?? 0;

$hotel_other = max(0, $premises_count - $office_count);

// Get weekly data for trends
$weekly_query = "
    SELECT 
        DATE(entry_date) as date,
        total_entries,
        (SELECT COUNT(*) FROM visitors WHERE DATE(check_in_time) = DATE(dpe.entry_date) AND status IN ('checked_in', 'checked_out')) as office_visitors
    FROM daily_premises_entries dpe 
    WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY entry_date DESC
";
$weekly_result = $conn->query($weekly_query);
$weekly_data = [];
while ($row = $weekly_result->fetch_assoc()) {
    $weekly_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premises Overview Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Premises Overview</h1>
                <p class="text-muted"><?= date('F j, Y') ?></p>
            </div>
            <div>
                <button class="btn btn-outline-primary" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Today's Summary Cards -->
        <div class="row mb-4">
            <!-- Total Premises Entries -->
            <div class="col-md-4">
                <div class="card bg-primary text-white stat-card shadow">
                    <div class="card-body text-center">
                        <i class="bi bi-door-open display-4 mb-3"></i>
                        <h1 class="display-3"><?= $premises_count ?></h1>
                        <h5 class="card-title">Total Premises Entries</h5>
                        <p class="card-text">All people entering today</p>
                    </div>
                </div>
            </div>
            
            <!-- Office Visitors -->
            <div class="col-md-4">
                <div class="card bg-success text-white stat-card shadow">
                    <div class="card-body text-center">
                        <i class="bi bi-building display-4 mb-3"></i>
                        <h1 class="display-3"><?= $office_count ?></h1>
                        <h5 class="card-title">Office Visitors</h5>
                        <p class="card-text">Registered at reception</p>
                    </div>
                </div>
            </div>
            
            <!-- Hotel & Other Traffic -->
            <div class="col-md-4">
                <div class="card bg-info text-white stat-card shadow">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-4 mb-3"></i>
                        <h1 class="display-3"><?= $hotel_other ?></h1>
                        <h5 class="card-title">Hotel & Other</h5>
                        <p class="card-text">Hotel guests, staff, delivery</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Traffic Breakdown Pie Chart -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h5><i class="bi bi-pie-chart"></i> Today's Traffic Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trafficChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Weekly Trend Line Chart -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h5><i class="bi bi-graph-up"></i> 7-Day Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions and Recent Activity -->
        <div class="row">
            <!-- Quick Actions -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h5><i class="bi bi-lightning"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body text-center">
                        <a href="scanner_page.php" class="btn btn-primary btn-lg me-3 mb-2">
                            <i class="bi bi-qr-code-scan"></i> Gate Scanner
                        </a>
                        <a href="vmc_dashboard.php" class="btn btn-success btn-lg mb-2">
                            <i class="bi bi-building"></i> Office Reception
                        </a>
                        <br>
                        <a href="export_premises_report.php" class="btn btn-outline-secondary">
                            <i class="bi bi-download"></i> Export Report
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Summary -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h5><i class="bi bi-graph-up-arrow"></i> Key Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h6 class="text-muted">Office Visitor Rate</h6>
                                <h4 class="text-primary">
                                    <?= $premises_count > 0 ? round(($office_count / $premises_count) * 100, 1) : 0 ?>%
                                </h4>
                            </div>
                            <div class="col-6">
                                <h6 class="text-muted">Hotel/Other Rate</h6>
                                <h4 class="text-info">
                                    <?= $premises_count > 0 ? round(($hotel_other / $premises_count) * 100, 1) : 0 ?>%
                                </h4>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> Last updated: <?= date('g:i A') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Entries Table -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5><i class="bi bi-list"></i> Recent Entries (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Total Entries</th>
                                        <th>Office Visitors</th>
                                        <th>Hotel/Other</th>
                                        <th>Office Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weekly_data as $day): ?>
                                        <?php 
                                            $daily_hotel_other = max(0, $day['total_entries'] - $day['office_visitors']);
                                            $daily_rate = $day['total_entries'] > 0 ? round(($day['office_visitors'] / $day['total_entries']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($day['date'])) ?></td>
                                            <td><span class="badge bg-primary"><?= $day['total_entries'] ?></span></td>
                                            <td><span class="badge bg-success"><?= $day['office_visitors'] ?></span></td>
                                            <td><span class="badge bg-info"><?= $daily_hotel_other ?></span></td>
                                            <td><?= $daily_rate ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Traffic Breakdown Doughnut Chart
        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        new Chart(trafficCtx, {
            type: 'doughnut',
            data: {
                labels: ['Office Visitors', 'Hotel & Other Traffic'],
                datasets: [{
                    data: [<?= $office_count ?>, <?= $hotel_other ?>],
                    backgroundColor: ['#28a745', '#17a2b8'],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Weekly Trend Line Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const weeklyData = <?= json_encode(array_reverse($weekly_data)) ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: weeklyData.map(day => {
                    const date = new Date(day.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Total Entries',
                        data: weeklyData.map(day => day.total_entries),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Office Visitors',
                        data: weeklyData.map(day => day.office_visitors),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Auto-refresh every 5 minutes
        setInterval(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>