<?php
session_start();

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

require 'db_connection.php';

// Get group statistics for dashboard
function getGroupStatistics($conn) {
    $today = date('Y-m-d');

    // Total groups today
    $total_groups_today = $conn->query("SELECT COUNT(DISTINCT group_id) as count 
                                      FROM visitors 
                                      WHERE group_id IS NOT NULL 
                                      AND DATE(visit_date) = '$today'")->fetch_assoc()['count'];

    // Active groups (checked in)
    $active_groups = $conn->query("SELECT COUNT(DISTINCT group_id) as count 
                                 FROM visitors 
                                 WHERE group_id IS NOT NULL 
                                 AND status = 'checked_in'")->fetch_assoc()['count'];

    // Average group size today
    $avg_group_size = $conn->query("SELECT AVG(group_size) as avg_size FROM (
                                   SELECT group_id, COUNT(*) as group_size 
                                   FROM visitors 
                                   WHERE group_id IS NOT NULL 
                                   AND DATE(visit_date) = '$today'
                                   GROUP BY group_id
                                   ) as group_sizes")->fetch_assoc()['avg_size'];

    // Largest group today
    $largest_group = $conn->query("SELECT group_id, COUNT(*) as size 
                                 FROM visitors 
                                 WHERE group_id IS NOT NULL 
                                 AND DATE(visit_date) = '$today'
                                 GROUP BY group_id 
                                 ORDER BY size DESC 
                                 LIMIT 1")->fetch_assoc();

    return [
        'total_groups_today' => $total_groups_today,
        'active_groups' => $active_groups,
        'avg_group_size' => round($avg_group_size, 1),
        'largest_group' => $largest_group
    ];
}

// Usage in dashboard:
$group_stats = getGroupStatistics($conn);

// Add this HTML to your dashboard for group statistics display:
?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= $group_stats['total_groups_today'] ?></h4>
                        <p class="mb-0">Groups Today</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people-fill fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= $group_stats['active_groups'] ?></h4>
                        <p class="mb-0">Active Groups</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-person-check-fill fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= $group_stats['avg_group_size'] ?></h4>
                        <p class="mb-0">Avg Group Size</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-bar-chart-fill fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= $group_stats['largest_group']['size'] ?? 0 ?></h4>
                        <p class="mb-0">Largest Group</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-trophy-fill fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $conn->close(); ?>