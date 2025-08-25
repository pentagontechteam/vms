<?php
session_start();

// premises_dashboard.php - Management overview
require 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

// Check if user role exists in session, if not fetch from database
if (!isset($_SESSION['receptionist_role'])) {
    $rec_id = $_SESSION['receptionist_id'];
    $stmt = $conn->prepare("SELECT role FROM receptionists WHERE id = ?");
    $stmt->bind_param("i", $rec_id);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $_SESSION['receptionist_role'] = $role;
    $stmt->close();
}

// Check if user is a super user
$user_role = $_SESSION['receptionist_role'] ?? 'receptionist';
if ($user_role !== 'super_user') {
    // Redirect to dashboard with error message or show 403 error
    header("Location: vmc_dashboard.php?error=access_denied");
    exit();
}
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
    <title>VMC Overview Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-green: #07AF8B;
            --primary-yellow: #FFCA00;
            --primary-dark-green: #007570;
            --background-light: #FAFBFC;
            --surface-white: #FFFFFF;
            --text-primary: #1C1C1E;
            --text-secondary: #8E8E93;
            --border-light: #E5E5EA;
            --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-large: 0 8px 32px rgba(0, 0, 0, 0.12);
            --radius-small: 12px;
            --radius-medium: 16px;
            --radius-large: 24px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--background-light);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .header-section {
            background: var(--surface-white);
            border-radius: var(--radius-large);
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-light);
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .header-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin: 8px 0 0 0;
            font-weight: 500;
        }

        .refresh-btn {
            background: var(--surface-white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-small);
            padding: 12px 20px;
            font-weight: 600;
            color: var(--text-primary);
            transition: all 0.2s cubic-bezier(0.4, 0.0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .refresh-btn:hover {
            background: var(--background-light);
            border-color: var(--primary-green);
            color: var(--primary-green);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--surface-white);
            border-radius: var(--radius-large);
            padding: 32px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            border: 1px solid var(--border-light);
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-large);
        }

        .stat-card-primary::before {
            background: linear-gradient(90deg, var(--primary-green), #05C896);
        }

        .stat-card-secondary::before {
            background: linear-gradient(90deg, var(--primary-yellow), #FFD700);
        }

        .stat-card-tertiary::before {
            background: linear-gradient(90deg, var(--primary-dark-green), #009B94);
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-medium);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .stat-card-primary .stat-icon {
            background: linear-gradient(135deg, var(--primary-green), #05C896);
            color: white;
        }

        .stat-card-secondary .stat-icon {
            background: linear-gradient(135deg, var(--primary-yellow), #FFD700);
            color: var(--text-primary);
        }

        .stat-card-tertiary .stat-icon {
            background: linear-gradient(135deg, var(--primary-dark-green), #009B94);
            color: white;
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .stat-card-primary .stat-number {
            background: linear-gradient(135deg, var(--primary-green), #05C896);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card-secondary .stat-number {
            background: linear-gradient(135deg, var(--primary-yellow), #FFB000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card-tertiary .stat-number {
            background: linear-gradient(135deg, var(--primary-dark-green), #009B94);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 16px 0 8px 0;
            color: var(--text-primary);
        }

        .stat-description {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin: 0;
            font-weight: 500;
        }

        /* Table Styles */
        .data-table-card {
            background: var(--surface-white);
            border-radius: var(--radius-large);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-light);
            overflow: hidden;
            margin-bottom: 32px;
        }

        .table-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, #F8F9FA, #FFFFFF);
        }

        .table-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modern-table {
            width: 100%;
            margin: 0;
        }

        .modern-table thead th {
            background: var(--background-light);
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 20px 24px;
            border: none;
            border-bottom: 1px solid var(--border-light);
        }

        .modern-table tbody td {
            padding: 20px 24px;
            border: none;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            font-weight: 500;
        }

        .modern-table tbody tr:hover {
            background: var(--background-light);
        }

        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-modern {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.02em;
        }

        .badge-primary {
            background: var(--primary-green);
            color: white;
        }

        .badge-secondary {
            background: var(--primary-yellow);
            color: var(--text-primary);
        }

        .badge-tertiary {
            background: var(--primary-dark-green);
            color: white;
        }

        /* Quick Actions */
        .quick-actions-card {
            background: var(--surface-white);
            border-radius: var(--radius-large);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-light);
            overflow: hidden;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary-green), #05C896);
            color: white;
            border: none;
            border-radius: var(--radius-medium);
            padding: 16px 32px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            justify-content: center;
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(7, 175, 139, 0.3);
            background: linear-gradient(135deg, #05C896, var(--primary-green));
        }

        .action-btn i {
            font-size: 1.3rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 16px;
            }

            .header-section {
                padding: 24px;
                text-align: center;
            }

            .header-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .stat-card {
                padding: 24px;
                min-height: 180px;
            }

            .stat-number {
                font-size: 2.8rem;
            }

            .stat-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }

            .table-header {
                padding: 20px;
            }

            .modern-table thead th,
            .modern-table tbody td {
                padding: 16px 12px;
                font-size: 0.9rem;
            }

            .header-controls {
                margin-top: 20px;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .header-title {
                font-size: 1.75rem;
            }

            .stat-number {
                font-size: 2.4rem;
            }

            .modern-table {
                font-size: 0.85rem;
            }

            .modern-table thead th,
            .modern-table tbody td {
                padding: 12px 8px;
            }
        }

        /* Loading Animation */
        .loading-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .5;
            }
        }

        /* Smooth Transitions */
        * {
            transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header-section">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <div>
                    <h1 class="header-title">Premises Overview</h1>
                    <p class="header-subtitle"><?= date('l, F j, Y') ?></p>
                </div>
                <div class="header-controls mt-3 mt-md-0">
                    <button class="refresh-btn" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-card-primary">
                <div class="stat-icon">
                    <i class="bi bi-door-open"></i>
                </div>
                <h2 class="stat-number"><?= number_format($premises_count) ?></h2>
                <h3 class="stat-title">Total Premises Entries</h3>
                <p class="stat-description">All people entering today</p>
            </div>

            <div class="stat-card stat-card-secondary">
                <div class="stat-icon">
                    <i class="bi bi-building"></i>
                </div>
                <h2 class="stat-number"><?= number_format($office_count) ?></h2>
                <h3 class="stat-title">Office Visitors</h3>
                <p class="stat-description">Registered at reception</p>
            </div>

            <div class="stat-card stat-card-tertiary">
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h2 class="stat-number"><?= number_format($hotel_other) ?></h2>
                <h3 class="stat-title">Hotel & Other</h3>
                <p class="stat-description">Hotel guests, staff, delivery</p>
            </div>
        </div>

        <!-- Recent Entries Table -->
        <div class="data-table-card">
            <div class="table-header">
                <h2 class="table-title">
                    <i class="bi bi-calendar-week"></i>
                    Recent Entries (Last 7 Days)
                </h2>
            </div>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Entries</th>
                            <th>Office Visitors</th>
                            <th>Hotel/Other</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_data as $day): ?>
                            <?php
                            $daily_hotel_other = max(0, $day['total_entries'] - $day['office_visitors']);
                            ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($day['date'])) ?></td>
                                <td><span class="badge-modern badge-primary"><?= number_format($day['total_entries']) ?></span></td>
                                <td><span class="badge-modern badge-secondary"><?= number_format($day['office_visitors']) ?></span></td>
                                <td><span class="badge-modern badge-tertiary"><?= number_format($daily_hotel_other) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="quick-actions-card">
                    <div class="table-header">
                        <h2 class="table-title">
                            <i class="bi bi-lightning-fill"></i>
                            Quick Actions
                        </h2>
                    </div>
                    <div class="p-4">
                        <button class="action-btn" onclick="window.location.href='vmc_dashboard.php'">
                            <i class="bi bi-building"></i>
                            <span>VMC Dashboard</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes with visual feedback
        let refreshTimer = setInterval(() => {
            // Add loading state
            document.body.classList.add('loading-pulse');

            setTimeout(() => {
                window.location.reload();
            }, 500);
        }, 300000);

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Add loading animation to refresh button
        document.querySelector('.refresh-btn').addEventListener('click', function() {
            this.innerHTML = '<i class="bi bi-arrow-clockwise"></i><span>Refreshing...</span>';
            this.disabled = true;
        });

        // Add intersection observer for smooth animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all stat cards for entrance animations
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Add number counter animation
        function animateNumbers() {
            const numbers = document.querySelectorAll('.stat-number');
            numbers.forEach(number => {
                const target = parseInt(number.textContent.replace(/,/g, ''));
                let current = 0;
                const increment = target / 60; // 60 frames for 1 second
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    number.textContent = Math.floor(current).toLocaleString();
                }, 16);
            });
        }

        // Trigger number animation when page loads
        setTimeout(animateNumbers, 500);
    </script>
</body>

</html>