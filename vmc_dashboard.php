<?php
session_start();

// DB Connection
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: vmc_login.php");
    exit();
}

// Get active tab from query parameter or default to 'checkedin'
$active_tab = isset($_GET['active_tab']) && in_array($_GET['active_tab'], ['checkedin', 'approved', 'checkedout', 'pending']) 
    ? $_GET['active_tab'] 
    : 'checkedin';

// Initialize variables BEFORE they are used
$pending_guests = [];
$total_pending = 0;

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $per_page = 50;
    $approved_page = isset($_GET['approved_page']) ? (int)$_GET['approved_page'] : 1;
    $checkedin_page = isset($_GET['checkedin_page']) ? (int)$_GET['checkedin_page'] : 1;
    $checkedout_page = isset($_GET['checkedout_page']) ? (int)$_GET['checkedout_page'] : 1;
    $pending_page = isset($_GET['pending_page']) ? (int)$_GET['pending_page'] : 1;

    $approved_start = ($approved_page - 1) * $per_page;
    $checkedin_start = ($checkedin_page - 1) * $per_page;
    $checkedout_start = ($checkedout_page - 1) * $per_page;
    $pending_start = ($pending_page - 1) * $per_page;

    $search_term = isset($_GET['search']) ? trim($_GET['search']) : "";
    $search_mode = !empty($search_term);

    if ($search_mode) {
        $like_term = '%' . $conn->real_escape_string($search_term) . '%';
        
        // Approved guests
        $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, status, organization, floor_of_visit 
                              FROM visitors 
                              WHERE status = 'approved' 
                              AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                              ORDER BY visit_date DESC, id DESC
                              LIMIT ?, ?");
        $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $approved_start, $per_page);
        $stmt->execute();
        $approved_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Checked-in guests
        $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, check_in_time, status, organization 
                              FROM visitors 
                              WHERE status = 'checked_in' 
                              AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                              ORDER BY check_in_time DESC, id DESC
                              LIMIT ?, ?");
        $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $checkedin_start, $per_page);
        $stmt->execute();
        $authenticated_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Checked-out guests
        $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, check_in_time, check_out_time, organization, floor_of_visit, reason 
                              FROM visitors 
                              WHERE status = 'checked_out' 
                              AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                              ORDER BY COALESCE(check_out_time, visit_date) DESC, id DESC
                              LIMIT ?, ?");
        $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $checkedout_start, $per_page);
        $stmt->execute();
        $checked_out_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Pending guests
        $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, created_at, status, organization, floor_of_visit 
                              FROM visitors 
                              WHERE status = 'pending' 
                              AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                              ORDER BY created_at DESC, id DESC
                              LIMIT ?, ?");
        $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $pending_start, $per_page);
        $stmt->execute();
        $pending_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Count queries
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'approved' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $total_approved = $stmt->get_result()->fetch_assoc()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_in' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $total_authenticated = $stmt->get_result()->fetch_assoc()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $total_checked_out = $stmt->get_result()->fetch_assoc()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'pending' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
        $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
        $stmt->execute();
        $total_pending = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

    } else {
        $approved_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, status, organization, floor_of_visit 
                                       FROM visitors 
                                       WHERE status = 'approved' 
                                       ORDER BY visit_date DESC, id DESC
                                       LIMIT $approved_start, $per_page")->fetch_all(MYSQLI_ASSOC);
        
        $authenticated_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, check_in_time, status, organization, floor_of_visit 
                                            FROM visitors 
                                            WHERE status = 'checked_in' 
                                            ORDER BY check_in_time DESC, id DESC
                                            LIMIT $checkedin_start, $per_page")->fetch_all(MYSQLI_ASSOC);
        
        $checked_out_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, check_in_time, check_out_time, organization, COALESCE(floor_of_visit, 'N/A') as floor_of_visit, COALESCE(reason, 'N/A') as reason 
                                          FROM visitors 
                                          WHERE status = 'checked_out' 
                                          ORDER BY COALESCE(check_out_time, visit_date) DESC, id DESC
                                          LIMIT $checkedout_start, $per_page")->fetch_all(MYSQLI_ASSOC);

        $pending_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, created_at, status, organization, floor_of_visit 
                                       FROM visitors 
                                       WHERE status = 'pending' 
                                       ORDER BY created_at DESC, id DESC
                                       LIMIT $pending_start, $per_page")->fetch_all(MYSQLI_ASSOC);

        $total_approved = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'approved'")->fetch_assoc()['count'];
        $total_authenticated = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_in'")->fetch_assoc()['count'];
        $total_checked_out = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out'")->fetch_assoc()['count'];
        $total_pending = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'pending'")->fetch_assoc()['count'];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'approved_guests' => $approved_guests,
        'authenticated_guests' => $authenticated_guests,
        'checked_out_guests' => $checked_out_guests,
        'pending_guests' => $pending_guests,
        'total_approved' => $total_approved,
        'total_authenticated' => $total_authenticated,
        'total_checked_out' => $total_checked_out,
        'total_pending' => $total_pending,
        'approved_pages' => ceil($total_approved / $per_page),
        'checkedin_pages' => ceil($total_authenticated / $per_page),
        'checkedout_pages' => ceil($total_checked_out / $per_page),
        'pending_pages' => ceil($total_pending / $per_page),
        'approved_page' => $approved_page,
        'checkedin_page' => $checkedin_page,
        'checkedout_page' => $checkedout_page,
        'pending_page' => $pending_page,
        'search_term' => $search_term
    ]);
    exit();
}

// Handle check-out
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_out_id'])) {
    $check_out_id = $_POST['check_out_id'];
    
    $stmt = $conn->prepare("UPDATE visitors SET status = 'checked_out', check_out_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $check_out_id);
    
    if ($stmt->execute()) {
        header("Location: vmc_dashboard.php?success=checked_out&active_tab=checkedout");
        exit();
    } else {
        $error_message = "Error checking out guest: " . $conn->error;
    }
    $stmt->close();
}

// Fetch logged-in receptionist name
$receptionist_name = "Receptionist";
if (isset($_SESSION['receptionist_id'])) {
    $rec_id = $_SESSION['receptionist_id'];
    $stmt = $conn->prepare("SELECT name FROM receptionists WHERE id = ?");
    $stmt->bind_param("i", $rec_id);
    $stmt->execute();
    $stmt->bind_result($receptionist_name);
    $stmt->fetch();
    $stmt->close();
}

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

// Get user role for conditional features
$user_role = $_SESSION['receptionist_role'] ?? 'receptionist';
$is_super_user = ($user_role === 'super_user');

// Pagination settings - MOVED HERE BEFORE BEING USED
$per_page = 10;
$approved_page = isset($_GET['approved_page']) ? (int)$_GET['approved_page'] : 1;
$checkedin_page = isset($_GET['checkedin_page']) ? (int)$_GET['checkedin_page'] : 1;
$checkedout_page = isset($_GET['checkedout_page']) ? (int)$_GET['checkedout_page'] : 1;
$pending_page = isset($_GET['pending_page']) ? (int)$_GET['pending_page'] : 1;

// Calculate start positions
$approved_start = ($approved_page - 1) * $per_page;
$checkedin_start = ($checkedin_page - 1) * $per_page;
$checkedout_start = ($checkedout_page - 1) * $per_page;
$pending_start = ($pending_page - 1) * $per_page;

// Search functionality
$search_term = isset($_GET['search']) ? trim($_GET['search']) : "";
$search_mode = !empty($search_term);

// Reset checkedout_page if invalid
$checkedout_pages = ceil($conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out'")->fetch_assoc()['count'] / $per_page);
if ($checkedout_page < 1 || $checkedout_page > $checkedout_pages) {
    $checkedout_page = 1;
}

// Fetch visitors with pagination and search
if ($search_mode) {
    $like_term = '%' . $conn->real_escape_string($search_term) . '%';
    
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, status, organization, floor_of_visit 
                          FROM visitors 
                          WHERE status = 'approved' 
                          AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                          ORDER BY visit_date DESC, id DESC
                          LIMIT ?, ?");
    $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $approved_start, $per_page);
    $stmt->execute();
    $approved_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, check_in_time, status, organization 
                          FROM visitors 
                          WHERE status = 'checked_in' 
                          AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                          ORDER BY check_in_time DESC, id DESC
                          LIMIT ?, ?");
    $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $checkedin_start, $per_page);
    $stmt->execute();
    $authenticated_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, check_in_time, check_out_time, organization, COALESCE(floor_of_visit, 'N/A') as floor_of_visit, COALESCE(reason, 'N/A') as reason 
                          FROM visitors 
                          WHERE status = 'checked_out' 
                          AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                          ORDER BY COALESCE(check_out_time, visit_date) DESC, id DESC
                          LIMIT ?, ?");
    $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $checkedout_start, $per_page);
    $stmt->execute();
    $checked_out_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // ADD PENDING GUESTS QUERY
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, created_at, status, organization, floor_of_visit 
                          FROM visitors 
                          WHERE status = 'pending' 
                          AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                          ORDER BY created_at DESC, id DESC
                          LIMIT ?, ?");
    $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $pending_start, $per_page);
    $stmt->execute();
    $pending_guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'approved' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
    $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
    $stmt->execute();
    $total_approved = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_in' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
    $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
    $stmt->execute();
    $total_authenticated = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
    $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
    $stmt->execute();
    $total_checked_out = $stmt->get_result()->fetch_assoc()['count'];
    
    // ADD PENDING COUNT QUERY
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM visitors WHERE status = 'pending' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
    $stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
    $stmt->execute();
    $total_pending = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} else {
    $approved_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, status, organization, floor_of_visit 
                                   FROM visitors 
                                   WHERE status = 'approved' 
                                   ORDER BY visit_date DESC, id DESC
                                   LIMIT $approved_start, $per_page")->fetch_all(MYSQLI_ASSOC);
    
    $authenticated_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, check_in_time, status, organization, floor_of_visit 
                                        FROM visitors 
                                        WHERE status = 'checked_in' 
                                        ORDER BY check_in_time DESC, id DESC
                                        LIMIT $checkedin_start, $per_page")->fetch_all(MYSQLI_ASSOC);
    
    $checked_out_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, check_in_time, check_out_time, organization, COALESCE(floor_of_visit, 'N/A') as floor_of_visit, COALESCE(reason, 'N/A') as reason 
                                      FROM visitors 
                                      WHERE status = 'checked_out' 
                                      ORDER BY COALESCE(check_out_time, visit_date) DESC, id DESC
                                      LIMIT $checkedout_start, $per_page")->fetch_all(MYSQLI_ASSOC);
    
    // ADD PENDING GUESTS QUERY
    $pending_guests = $conn->query("SELECT id, name, phone, email, host_name, visit_date, created_at, status, organization, floor_of_visit 
                                   FROM visitors 
                                   WHERE status = 'pending' 
                                   ORDER BY created_at DESC, id DESC
                                   LIMIT $pending_start, $per_page")->fetch_all(MYSQLI_ASSOC);

    $total_approved = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'approved'")->fetch_assoc()['count'];
    $total_authenticated = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_in'")->fetch_assoc()['count'];
    $total_checked_out = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'checked_out'")->fetch_assoc()['count'];
    $total_pending = $conn->query("SELECT COUNT(*) as count FROM visitors WHERE status = 'pending'")->fetch_assoc()['count'];
}

// Calculate total pages for each tab
$approved_pages = ceil($total_approved / $per_page);
$checkedin_pages = ceil($total_authenticated / $per_page);
$checkedout_pages = ceil($total_checked_out / $per_page);
$pending_pages = ceil($total_pending / $per_page);

// Function to generate pagination links
function generate_pagination($total_pages, $current_page, $tab, $search_term, $active_tab) {
    $output = '<nav aria-label="Page navigation" class="pagination-enhanced"><ul class="pagination justify-content-center">';
    
    $prev_disabled = $current_page <= 1 ? 'disabled' : '';
    $output .= "<li class='page-item $prev_disabled'>";
    $output .= "<a class='page-link' href='?active_tab=$active_tab&search=".urlencode($search_term)."&{$tab}_page=".($current_page - 1)."'>« Previous</a>";
    $output .= "</li>";

    $range = 2;
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $output .= "<li class='page-item $active'>";
        $output .= "<a class='page-link' href='?active_tab=$active_tab&search=".urlencode($search_term)."&{$tab}_page=$i'>$i</a>";
        $output .= "</li>";
    }

    $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
    $output .= "<li class='page-item $next_disabled'>";
    $output .= "<a class='page-link' href='?active_tab=$active_tab&search=".urlencode($search_term)."&{$tab}_page=".($current_page + 1)."'>Next »</a>";
    $output .= "</li>";

    $output .= '</ul></nav>';
    return $output;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VMC Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #07AF8B;
            --accent: #FFCA00;
            --dark: #007570;
            --light: #F8F9FA;
            --white: #FFFFFF;
        }
        body {
            background: #F5F7FA;
            font-family: 'Inter', sans-serif;
        }
        .table-hover tbody tr {
            transition: all 0.3s ease;
        }
        .animate__animated {
            animation-duration: 0.5s;
        }
        .header-bar {
            background: var(--white);
            border-bottom: 1px solid #e0e0e0;
        }
        .header-bar span {
            color: #333333;
        }
        .header-bar a {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }
        .pagination .page-link {
            color: var(--primary);
            transition: background-color 0.2s, color 0.2s;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            cursor: not-allowed;
        }
        .pagination .page-link:hover {
            background-color: var(--light);
            color: var(--dark);
        }
        
        /*visitor pass styles */
.rotate-text {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    transform: rotate(180deg);
    font-family: 'Montserrat', sans-serif;
}

.ornamental-border {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border: 8px solid transparent;
    border-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%230d9488" stroke-width="2" stroke-dasharray="8,4" stroke-linecap="round"/></svg>') 8 round;
    pointer-events: none;
}

.corner-decoration {
    position: absolute;
    width: 30px;
    height: 30px;
    border-color: #0d9488;
    border-width: 2px;
}

.top-left {
    top: 0;
    left: 0;
    border-right: none;
    border-bottom: none;
}

.top-right {
    top: 0;
    right: 0;
    border-left: none;
    border-bottom: none;
}

.bottom-left {
    bottom: 0;
    left: 0;
    border-right: none;
    border-top: none;
}

.bottom-right {
    bottom: 0;
    right: 0;
    border-left: none;
    border-top: none;
}

.pass-body {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(245, 245, 245, 0.95) 100%);
}

.venue-font {
    font-family: 'Playfair Display', serif;
}

.text-font {
    font-family: 'Montserrat', sans-serif;
}

/* Visitor Pass Styles */
.rotate-text {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    transform: rotate(180deg);
    font-family: 'Montserrat', sans-serif;
}

.corner-decoration {
    position: absolute;
    width: 30px;
    height: 30px;
    border-color: #0d9488;
    border-width: 2px;
}

.top-left {
    top: 0;
    left: 0;
    border-right: none;
    border-bottom: none;
}

.top-right {
    top: 0;
    right: 0;
    border-left: none;
    border-bottom: none;
}

.bottom-left {
    bottom: 0;
    left: 0;
    border-right: none;
    border-top: none;
}

.bottom-right {
    bottom: 0;
    right: 0;
    border-left: none;
    border-top: none;
}

.venue-font {
    font-family: 'Playfair Display', serif;
}

.text-font {
    font-family: 'Montserrat', sans-serif;
}


.rotate-text {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    transform: rotate(180deg);
    font-family: 'Montserrat', sans-serif;
}

.corner-decoration {
    position: absolute;
    width: 30px;
    height: 30px;
    border-color: #0d9488;
    border-width: 2px;
}

.top-left {
    top: 0;
    left: 0;
    border-right: none;
    border-bottom: none;
}

.top-right {
    top: 0;
    right: 0;
    border-left: none;
    border-bottom: none;
}

.bottom-left {
    bottom: 0;
    left: 0;
    border-right: none;
    border-top: none;
}

.bottom-right {
    bottom: 0;
    right: 0;
    border-left: none;
    border-top: none;
}

.venue-font {
    font-family: 'Playfair Display', serif;
}

.text-font {
    font-family: 'Montserrat', sans-serif;
}

.pass-body {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(245, 245, 245, 0.95) 100%);
}

/* Enhanced Typography Hierarchy */
.text-primary-enhanced { 
    color: #1F2937; 
    font-weight: 600; 
    font-size: 18px; /* Visitor names more prominent */
}

.text-secondary-enhanced { 
    color: #6B7280; 
    font-size: 14px; 
    font-weight: 500;
}

.text-muted-enhanced { 
    color: #9CA3AF; 
    font-size: 13px; 
}

/* Improved base typography */
body {
    font-size: 16px; /* Increased from default 14px */
    line-height: 1.6;
}

/* Enhanced Button System */
.btn-enhanced {
    min-height: 44px; /* Touch-friendly size */
    padding: 12px 24px;
    font-weight: 600;
    font-size: 16px;
    border-radius: 8px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    cursor: pointer;
}

.btn-primary-enhanced {
    background: var(--primary);
    color: var(--white);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

.btn-primary-enhanced:hover {
    background: var(--dark);
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    color: var(--white);
}

.btn-secondary-enhanced {
    background: var(--white);
    color: #1F2937;
    border-color: #E5E7EB;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.btn-secondary-enhanced:hover {
    background: var(--light);
    border-color: var(--primary);
    transform: translateY(-1px);
    color: #1F2937;
}

.btn-danger-enhanced {
    background: #EF4444;
    color: var(--white);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

.btn-danger-enhanced:hover {
    background: #DC2626;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    color: var(--white);
}

.btn-warning-enhanced {
    background: var(--accent);
    color: #1F2937;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    font-weight: 600;
}

.btn-warning-enhanced:hover {
    background: #F59E0B;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    color: #1F2937;
}

/* Small button variant */
.btn-sm-enhanced {
    min-height: 36px;
    padding: 8px 16px;
    font-size: 14px;
}

/* Time-Sensitive Color Coding */
.time-indicator {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

.time-recent {
    background: rgba(16, 185, 129, 0.1);
    color: #10B981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.time-moderate {
    background: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.time-long {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Enhanced Table Design - Cleaner Version */
.table-enhanced {
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #F3F4F6;
}

.table-enhanced thead th {
    background: #FAFAFA;
    color: #374151;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    padding: 18px 20px;
    border: none;
    border-bottom: 2px solid var(--primary);
}

.table-enhanced tbody tr {
    border-bottom: 1px solid #F9FAFB;
    transition: all 0.15s ease;
}

.table-enhanced tbody tr:hover {
    background: #FEFEFE;
    box-shadow: 0 2px 4px rgba(7, 175, 139, 0.08);
}

.table-enhanced tbody tr:last-child {
    border-bottom: none;
}

.table-enhanced tbody td {
    padding: 20px;
    vertical-align: middle;
    border: none;
    font-size: 15px;
}

/* Alternating row colors for better readability */
.table-enhanced tbody tr:nth-child(even) {
    background: #FDFDFD;
}

.table-enhanced tbody tr:nth-child(even):hover {
    background: #FEFEFE;
}

/* Center visitor name field content */
/* Left-align visitor avatars in straight line */
.visitor-cell {
    text-align: left;
}

.visitor-cell .d-flex {
    justify-content: flex-start;
    align-items: center;
}

/* Enhanced Avatar/Symbol Design */
.symbol-enhanced {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--dark) 100%);
    color: var(--white);
    box-shadow: 0 4px 8px rgba(7, 175, 139, 0.25);
    margin-right: 16px;
    flex-shrink: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.symbol-enhanced:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}

/* Enhanced Card Design */
.card-enhanced {
    background: var(--white);
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06);
    overflow: hidden;
    transition: all 0.3s ease;
    margin-bottom: 24px;
}

.card-enhanced:hover {
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05);
    transform: translateY(-2px);
}

.card-enhanced .card-body {
    padding: 24px;
}

/* Enhanced Action Buttons */
.action-buttons {
    background: var(--white);
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06);
    margin-bottom: 24px;
    text-align: center;
}

.action-buttons .btn-enhanced {
    margin: 8px;
    min-width: 200px;
}

/* Enhanced Tab Design */
.nav-tabs-enhanced {
    border: none;
    background: var(--white);
    border-radius: 12px;
    padding: 8px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06);
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
}

.nav-tabs-enhanced .nav-link {
    border: none;
    border-radius: 8px;
    padding: 16px 24px;
    font-weight: 600;
    font-size: 16px;
    color: #6B7280;
    transition: all 0.2s ease;
    margin-right: 4px;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.nav-tabs-enhanced .nav-link.active {
    background: var(--primary);
    color: var(--white);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

.nav-tabs-enhanced .nav-link:hover:not(.active) {
    background: #F8F9FA;
    color: #1F2937;
}

/* Status Indicators */
.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
    animation: pulse 2s infinite;
}

.status-checked-in { background: #10B981; }
.status-approved { background: #3B82F6; }
.status-checked-out { background: #6B7280; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Enhanced Badge Design */
.badge-enhanced {
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 20px;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.badge-primary-enhanced {
    background: var(--primary);
    color: var(--white);
}

.badge-success-enhanced {
    background: #10B981;
    color: var(--white);
}

.badge-secondary-enhanced {
    background: #6B7280;
    color: var(--white);
}

/* Enhanced Pagination */
.pagination-enhanced {
    margin-top: 24px;
}

.pagination-enhanced .page-link {
    color: #1F2937;
    border: 2px solid #E5E7EB;
    padding: 12px 16px;
    font-weight: 500;
    margin: 0 4px;
    border-radius: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.pagination-enhanced .page-item.active .page-link {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--white);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

.pagination-enhanced .page-link:hover {
    background: #F8F9FA;
    border-color: var(--primary);
    transform: translateY(-1px);
    text-decoration: none;
}

.pagination-enhanced .page-item.disabled .page-link {
    color: #9CA3AF;
    background: #F8F9FA;
    border-color: #E5E7EB;
    cursor: not-allowed;
}

/* Enhanced Empty States */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #9CA3AF;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
    color: #D1D5DB;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #6B7280;
}

.empty-state p {
    font-size: 16px;
    margin: 0;
    color: #9CA3AF;
}

/* Loading States */
.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Success/Error Feedback */
.feedback-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    z-index: 1000;
    animation: slideIn 0.3s ease;
    min-width: 300px;
}

.feedback-success {
    background: #10B981;
    color: var(--white);
}

.feedback-error {
    background: #EF4444;
    color: var(--white);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Loading Overlay */
#loadingOverlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.9);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

/* Enhanced Header */
.header-bar {
    background: var(--white);
    border-bottom: 2px solid #E5E7EB;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    padding: 16px 24px;
}

.header-bar .logo {
    height: 48px;
}

.header-bar .title {
    color: #1F2937;
    font-size: 20px;
    font-weight: 600;
    margin-left: 16px;
}

.header-bar .user-info {
    font-size: 16px;
    color: #6B7280;
    font-weight: 500;
}

/* Enhanced Modal Design */
.modal-content-enhanced {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04);
    overflow: hidden;
}

.modal-header-enhanced {
    background: linear-gradient(135deg, var(--primary) 0%, var(--dark) 100%);
    color: white;
    border-radius: 16px 16px 0 0;
    border-bottom: none;
    padding: 20px 24px;
}

.modal-header-enhanced .modal-title {
    font-weight: 700;
    font-size: 18px;
}

.modal-body-enhanced {
    padding: 24px;
}

.modal-footer-enhanced {
    border-top: none;
    padding: 20px 24px;
    background: #FAFAFA;
}

/* Enhanced form controls in modals */
.modal .form-control {
    height: 48px;
    font-size: 16px;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    padding: 12px 16px;
    transition: all 0.2s ease;
}

.modal .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(7, 175, 139, 0.1);
}

.modal .form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

/* Yellow export button */
.btn-export-enhanced {
    background: #FFCA00;
    color: #1F2937;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    font-weight: 600;
}

.btn-export-enhanced:hover {
    background: #F59E0B;
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    color: #1F2937;
}

/* Enhanced Form Styling */
.form-control-enhanced {
    height: 48px;
    font-size: 16px;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    padding: 12px 16px;
    transition: all 0.2s ease;
    background: var(--white);
}

.form-control-enhanced:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(7, 175, 139, 0.1);
    outline: none;
}

.form-label-enhanced {
    font-weight: 600;
    color: #374151;
    margin
}

/* Enhanced Confirmation Modals */
.modal-danger-header {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
    color: white;
    border-radius: 16px 16px 0 0;
}

.alert-warning-enhanced {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.2);
    color: #92400E;
    border-radius: 8px;
    padding: 12px 16px;
}

/* Enhanced Responsive Design */
@media (max-width: 768px) {
    .header-bar {
        padding: 12px 16px;
        flex-direction: column;
        gap: 12px;
    }
    
    .header-bar .title {
        font-size: 18px;
        margin-left: 0;
        text-align: center;
    }
    
    .btn-enhanced {
        min-height: 48px; /* Larger touch targets on mobile */
        font-size: 16px;
        width: 100%;
        margin-bottom: 8px;
    }
    
    .action-buttons .btn-enhanced {
        display: block;
        margin: 8px 0;
    }
    
    .table-enhanced {
        font-size: 14px;
    }
    
    .symbol-enhanced {
        width: 40px;
        height: 40px;
        font-size: 16px;
        margin-right: 12px;
    }

    .nav-tabs-enhanced {
        flex-direction: column;
    }

    .nav-tabs-enhanced .nav-link {
        margin-right: 0;
        margin-bottom: 4px;
        justify-content: center;
        text-align: center;
    }

    .text-primary-enhanced {
        font-size: 16px; /* Slightly smaller names on mobile */
    }

    .pagination-enhanced .page-link {
        padding: 8px 12px;
        font-size: 14px;
    }

    /* Hide some table columns on mobile for better fit */
    .table-enhanced th:nth-child(n+4),
    .table-enhanced td:nth-child(n+4) {
        display: none;
    }

    /* Show only essential columns */
    .table-enhanced th:nth-child(1),
    .table-enhanced th:nth-child(2),
    .table-enhanced th:last-child,
    .table-enhanced td:nth-child(1),
    .table-enhanced td:nth-child(2),
    .table-enhanced td:last-child {
        display: table-cell;
    }
}

/* Mobile tab text abbreviation */
@media (max-width: 768px) {
    .nav-tabs-enhanced {
        flex-direction: row;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 8px 8px 16px 8px;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE/Edge */
    }
    
    .nav-tabs-enhanced::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }
    
    .nav-tabs-enhanced .nav-link {
        flex-shrink: 0;
        min-width: 120px;
        margin-right: 8px;
        margin-bottom: 0;
        padding: 12px 16px;
        text-align: center;
        white-space: nowrap;
        justify-content: center;
    }
    
    .nav-tabs-enhanced .nav-link .tab-text-full {
        display: none;
    }
    
    .nav-tabs-enhanced .nav-link .tab-text-short {
        display: inline;
        font-size: 14px;
        font-weight: 600;
    }
}

/* Desktop tab text (add this new section) */
@media (min-width: 769px) {
    .nav-tabs-enhanced .nav-link .tab-text-short {
        display: none;
    }
    
    .nav-tabs-enhanced .nav-link .tab-text-full {
        display: inline;
    }
}

/* Warning badge for pending requests */
.badge-warning-enhanced {
    background: #F59E0B;
    color: #1F2937;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 20px;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

/* Analytics Button Styling */
.btn-analytics-enhanced {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    color: var(--white);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.btn-analytics-enhanced:hover {
    background: linear-gradient(135deg, #4338CA 0%, #6D28D9 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    color: var(--white);
}

.btn-analytics-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-analytics-enhanced:hover::before {
    left: 100%;
}
    </style>
</head>
<body>
    <div class="header-bar sticky-top d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <img src="assets/logo-green-yellow.png" alt="Logo" class="logo">
            <span class="title">
    <?php if ($is_super_user): ?>
        Visitor Management Center Super User
    <?php else: ?>
        Visitor Management Center
    <?php endif; ?>
</span>
        </div>
        <div class="d-flex align-items-center">
            <span class="user-info me-3">Welcome, <?= htmlspecialchars($receptionist_name) ?></span>
            <?php if ($is_super_user): ?>
                <a href="vmcanalytics.php" class="btn-enhanced btn-secondary-enhanced me-3">
                    <i class="bi bi-graph-up me-2"></i>Analytics
                </a>
            <?php endif; ?>
            <a href="vmc_logout.php" class="btn-enhanced btn-secondary-enhanced">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </div>
    </div>


    <!--<div class="d-flex align-items-center ms-3">
        <button id="refreshBtn" class="btn btn-sm btn-icon" style="background: var(--light); color: var(--dark); border: 1px solid rgba(0,0,0,0.1);" title="Refresh Data" aria-label="Refresh dashboard data">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
            </svg>
        </button>
    </div> -->

    <div class="container-fluid py-4 px-4">
        

        <form method="GET" class="row justify-content-center mb-4">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search by name, phone, email, or host" value="<?= htmlspecialchars($search_term) ?>">
                <input type="hidden" name="active_tab" value="<?= htmlspecialchars($active_tab) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-custom" style="background: var(--primary); color: white;">Search</button>
                <?php if (!empty($search_term)): ?>
                    <a href="vmc_dashboard.php?active_tab=<?= htmlspecialchars($active_tab) ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($search_mode): ?>
            <div class="alert alert-info alert-dismissible fade show text-center m-0 rounded-0" role="alert" style="background-color: #FFCA00; color: #212529;">
                <strong>Search Result:</strong> Showing results for "<strong><?= htmlspecialchars($search_term) ?></strong>"
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="action-buttons">
    <a href="register_walkin.php" class="btn-enhanced btn-primary-enhanced">
        <i class="bi bi-person-plus-fill me-2"></i>Walk-in Visitor
    </a>
    <a href="register_for_staff.php" class="btn-enhanced btn-secondary-enhanced">
        <i class="bi bi-person-plus-fill me-2"></i>Request Visit for Staff
    </a>
    <button type="button" class="btn-enhanced btn-secondary-enhanced" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="bi bi-download me-2"></i>Export Visitors
    </button>
</div>
           <!-- <a href="export_visitors.php" class="btn btn-lg shadow-sm px-4 py-2 animate__animated animate__pulse" style="background: #6c757d; color: white;">
    <i class="bi bi-download me-2"></i>Export Today's Visitors
</a> 
        </div>
        
    <button type="button" class="btn btn-lg shadow-sm px-4 py-2 animate__animated animate__pulse" style="background: #6c757d; color: white;" data-bs-toggle="modal" data-bs-target="#exportModal">
    <i class="bi bi-download me-2"></i>Export Visitors
</button> -->

<ul class="nav nav-tabs nav-tabs-enhanced" id="dashboardTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $active_tab === 'checkedin' ? 'active' : '' ?>" id="checkedin-tab" data-bs-toggle="tab" data-bs-target="#checkedin" type="button" role="tab">
            <i class="bi bi-people-fill me-2"></i>
            <span class="tab-text-full">Checked-In Visitors</span>
            <span class="tab-text-short">Checked-In</span>
            <span class="badge badge-primary-enhanced ms-2"><?= $total_authenticated ?? 0 ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $active_tab === 'approved' ? 'active' : '' ?>" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
            <i class="bi bi-check-circle-fill me-2"></i>
            <span class="tab-text-full">Approved Visitors</span>
            <span class="tab-text-short">Approved</span>
            <span class="badge badge-success-enhanced ms-2"><?= $total_approved ?? 0 ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $active_tab === 'pending' ? 'active' : '' ?>" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
            <i class="bi bi-clock me-2"></i>
            <span class="tab-text-full">Pending Requests</span>
            <span class="tab-text-short">Pending</span>
            <span class="badge badge-warning-enhanced ms-2"><?= $total_pending ?? 0 ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $active_tab === 'checkedout' ? 'active' : '' ?>" id="checkedout-tab" data-bs-toggle="tab" data-bs-target="#checkedout" type="button" role="tab">
            <i class="bi bi-box-arrow-left me-2"></i>
            <span class="tab-text-full">Checked-Out Visitors</span>
            <span class="tab-text-short">Checked-Out</span>
            <span class="badge badge-secondary-enhanced ms-2"><?= $total_checked_out ?? 0 ?></span>
        </button>
    </li>
</ul>

        <div class="tab-content" id="dashboardTabsContent">
            <div class="tab-pane fade <?= $active_tab === 'checkedin' ? 'show active' : '' ?>" id="checkedin" role="tabpanel">
                <div class="card-enhanced animate__animated animate__fadeIn">
                    <div class="card-body p-0">
                        <?php if (empty($authenticated_guests)): ?>
                            <div class="empty-state">
        <i class="bi bi-people"></i>
        <h3>No Visitors Currently Checked-In</h3>
        <p>All visitors have completed their visits</p>
    </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-enhanced align-middle mb-0">
                                    <thead style="background: var(--primary); color: white;">
                                        <tr>
                                            <th width="18%">Visitor</th>
                                            <th width="15%">Contact</th>
                                            <th width="18%">Host</th>
                                            <th width="12%">Visit Date</th>
                                            <th width="15%">Check-In Time</th>
                                            <th width="22%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($authenticated_guests as $guest): ?>
                                            <tr class="animate__animated animate__fadeIn">
                                                <td class="visitor-cell">
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol-enhanced">
    <?= strtoupper(substr($guest['name'], 0, 1)) ?>
</div>
                                                        <div>
                                                            <div class="text-primary-enhanced"><?= htmlspecialchars($guest['name']) ?></div>
                                                            <div class="text-muted-enhanced"><?= htmlspecialchars($guest['email']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($guest['phone']) ?></td>
                                                <td><?= htmlspecialchars($guest['host_name']) ?></td>
                                                <td>
                                                    <?php 
                                                        if (!empty($guest['visit_date']) && $guest['visit_date'] != '0000-00-00') {
                                                            echo htmlspecialchars(date('Y-m-d', strtotime($guest['visit_date'])));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
    <?php
    // Calculate time difference in hours
    $checkin_time = strtotime($guest['check_in_time']);
    $current_time = time();
    $hours_diff = ($current_time - $checkin_time) / 3600;
    
    // Determine time class based on duration
    if ($hours_diff < 2) {
        $time_class = 'time-recent';
    } elseif ($hours_diff < 4) {
        $time_class = 'time-moderate';
    } else {
        $time_class = 'time-long';
    }
    ?>
    <span class="time-indicator <?= $time_class ?>"><?= date('g:i A', strtotime($guest['check_in_time'])) ?></span>
</td>
                                                <td>
                                                    <button class="btn btn-sm me-2" style="background: var(--accent);" 
        onclick="openCameraModal(<?= $guest['id'] ?>, '<?= htmlspecialchars($guest['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($guest['host_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($guest['organization'] ?? 'N/A', ENT_QUOTES) ?>', '<?= htmlspecialchars(date('jS F, Y', strtotime($guest['visit_date'])), ENT_QUOTES) ?>', '<?= htmlspecialchars($guest['floor_of_visit'] ?? 'N/A', ENT_QUOTES) ?>')">
    Card
</button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#checkoutModal<?= $guest['id'] ?>">
                                                        Check Out
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?= generate_pagination($checkedin_pages, $checkedin_page, 'checkedin', $search_term, $active_tab) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?= $active_tab === 'approved' ? 'show active' : '' ?>" id="approved" role="tabpanel">
                <div class="card-enhanced animate__animated animate__fadeIn">
                    <div class="card-body p-0">
                        <?php if (empty($approved_guests)): ?>
                            <div class="empty-state">
        <i class="bi bi-check-circle"></i>
        <h3>No Approved Visitors</h3>
        <p>All approved visitors have been checked in</p>
    </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-enhanced align-middle mb-0">
                                    <thead style="background: var(--primary); color: white;">
                                        <tr>
                                            <th width="20%">Visitor</th>
                                            <th width="15%">Contact</th>
                                            <th width="20%">Host</th>
                                            <th width="15%">Visit Date</th>
                                            <th width="30%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($approved_guests as $guest): ?>
                                            <tr class="animate__animated animate__fadeIn">
                                                <td class="visitor-cell">
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol-enhanced">
    <?= strtoupper(substr($guest['name'], 0, 1)) ?>
</div>
                                                        <div>
                                                            <div class="text-primary-enhanced"><?= htmlspecialchars($guest['name']) ?></div>
                                                            <div class="text-muted-enhanced"><?= htmlspecialchars($guest['email']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($guest['phone']) ?></td>
                                                <td><?= htmlspecialchars($guest['host_name']) ?></td>
                                                <td>
                                                    <?php 
                                                        if (!empty($guest['visit_date']) && $guest['visit_date'] != '0000-00-00') {
                                                            echo htmlspecialchars(date('Y-m-d', strtotime($guest['visit_date'])));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm me-2" style="background: var(--accent);" 
        onclick="openCameraModal(<?= $guest['id'] ?>, '<?= htmlspecialchars($guest['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($guest['host_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($guest['organization'] ?? 'N/A', ENT_QUOTES) ?>', '<?= htmlspecialchars(date('jS F, Y', strtotime($guest['visit_date'])), ENT_QUOTES) ?>', '<?= htmlspecialchars($guest['floor_of_visit'] ?? 'N/A', ENT_QUOTES) ?>')">
    Card
</button>
                                                    
                                                    <button class="btn btn-sm btn-success me-2" 
        onclick="checkInGuest(<?= $guest['id'] ?>, '<?= htmlspecialchars($guest['name'], ENT_QUOTES) ?>')"
        title="Check In Guest">
    <i class="bi bi-box-arrow-in-right"></i> Check In
</button>
                                                    
                                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#checkoutModal<?= $guest['id'] ?>">
                                                        Cancel
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?= generate_pagination($approved_pages, $approved_page, 'approved', $search_term, $active_tab) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

<!-- Add this after the approved visitors tab content -->
<div class="tab-pane fade <?= $active_tab === 'pending' ? 'show active' : '' ?>" id="pending" role="tabpanel">
    <div class="card-enhanced animate__animated animate__fadeIn">
        <div class="card-body p-0">
            <?php if (empty($pending_guests)): ?>
                <div class="empty-state">
                    <i class="bi bi-clock"></i>
                    <h3>No Pending Requests</h3>
                    <p>All visitor requests have been processed</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-enhanced align-middle mb-0">
                        <thead style="background: var(--primary); color: white;">
                            <tr>
                                <th width="20%">Visitor</th>
                                <th width="15%">Contact</th>
                                <th width="20%">Host</th>
                                <th width="15%">Requested Date</th>
                                <th width="15%">Request Time</th>
                                <th width="15%">Organization</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_guests as $guest): ?>
                                <tr class="animate__animated animate__fadeIn">
                                    <td class="visitor-cell">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol-enhanced">
                                                <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="text-primary-enhanced"><?= htmlspecialchars($guest['name']) ?></div>
                                                <div class="text-muted-enhanced"><?= htmlspecialchars($guest['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($guest['phone']) ?></td>
                                    <td><?= htmlspecialchars($guest['host_name']) ?></td>
                                    <td>
                                        <?php 
                                            if (!empty($guest['visit_date']) && $guest['visit_date'] != '0000-00-00') {
                                                echo htmlspecialchars(date('Y-m-d', strtotime($guest['visit_date'])));
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="time-indicator time-moderate"><?= date('g:i A', strtotime($guest['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($guest['organization'] ?? 'N/A') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= generate_pagination($pending_pages, $pending_page, 'pending', $search_term, $active_tab) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
            
            <div class="tab-pane fade <?= $active_tab === 'checkedout' ? 'show active' : '' ?>" id="checkedout" role="tabpanel">
                <div class="card-enhanced animate__animated animate__fadeIn">
                    <div class="card-body p-0">
                        <?php if (empty($checked_out_guests)): ?>
                            <div class="empty-state">
        <i class="bi bi-box-arrow-left"></i>
        <h3>No Checked-Out Visitors</h3>
        <p>No visitors have checked out today</p>
    </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-enhanced align-middle mb-0">
                                    <thead style="background: var(--primary); color: white;">
                                        <tr>
                                            <th width="18%">Visitor</th>
                                            <th width="12%">Contact</th>
                                            <th width="15%">Host</th>
                                            <th width="10%">Visit Date</th>
                                            <th width="12%">Check-Out Time</th>
                                            <th width="15%">Floor/Venue</th>
                                            <th width="18%">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($checked_out_guests as $guest): ?>
                                            <tr class="animate__animated animate__fadeIn">
                                                <td class="visitor-cell">
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol-enhanced">
    <?= strtoupper(substr($guest['name'], 0, 1)) ?>
</div>
                                                        <div>
                                                            <div class="text-primary-enhanced"><?= htmlspecialchars($guest['name']) ?></div>
                                                            <div class="text-muted-enhanced"><?= htmlspecialchars($guest['email']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($guest['phone']) ?></td>
                                                <td><?= htmlspecialchars($guest['host_name']) ?></td>
                                                <td>
                                                    <?php 
                                                        if (!empty($guest['visit_date']) && $guest['visit_date'] != '0000-00-00') {
                                                            echo htmlspecialchars(date('Y-m-d', strtotime($guest['visit_date'])));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?= !empty($guest['check_out_time']) ? date('g:i A', strtotime($guest['check_out_time'])) : 'N/A' ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($guest['floor_of_visit']) ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($guest['reason']) ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?= generate_pagination($checkedout_pages, $checkedout_page, 'checkedout', $search_term, $active_tab) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php foreach ($authenticated_guests as $guest): ?>
<div class="modal fade" id="checkoutModal<?= $guest['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header modal-header-enhanced">
                <h5 class="modal-title fw-bold">
                    Confirm Check-Out
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-enhanced">
                <p class="mb-3">Are you sure you want to <strong>check out</strong> this visitor?</p>
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol-enhanced me-3">
                        <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h6 class="mb-1 text-primary-enhanced"><?= htmlspecialchars($guest['name']) ?></h6>
                        <small class="text-muted-enhanced">Host: <?= htmlspecialchars($guest['host_name']) ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-footer-enhanced">
                <button type="button" class="btn-enhanced btn-secondary-enhanced" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form method="POST" action="vmc_dashboard.php?active_tab=checkedout" class="d-inline">
                    <input type="hidden" name="check_out_id" value="<?= $guest['id'] ?>">
                    <button type="submit" class="btn-enhanced btn-danger-enhanced">
                        Check Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php foreach ($approved_guests as $guest): ?>
<div class="modal fade" id="checkoutModal<?= $guest['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header modal-danger-header">
                <h5 class="modal-title fw-bold">
                    Cancel Visit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-enhanced">
                <p>Are you sure you want to <strong>cancel</strong> this visit?</p>
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol-enhanced me-3">
                        <?= strtoupper(substr($guest['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h6 class="mb-1 text-primary-enhanced"><?= htmlspecialchars($guest['name']) ?></h6>
                        <small class="text-muted-enhanced">Host: <?= htmlspecialchars($guest['host_name']) ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-footer-enhanced">
                <button type="button" class="btn-enhanced btn-secondary-enhanced" data-bs-dismiss="modal">Close</button>
                <form method="POST" action="vmc_dashboard.php?active_tab=checkedout" class="d-inline">
                    <input type="hidden" name="check_out_id" value="<?= $guest['id'] ?>">
                    <button type="submit" class="btn-enhanced btn-danger-enhanced">
                        Cancel Visit
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

        <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: var(--primary); color: white;">
                <h5 class="modal-title">Visitor Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-center p-4">
                    <div id="cardPreview" style="width: 100%; max-width: 400px;">
                        <div class="bg-white w-80 h-[500px] shadow-xl flex overflow-hidden relative pass-body">
                            <!-- Corner Decorations -->
                            <div class="corner-decoration top-left"></div>
                            <div class="corner-decoration top-right"></div>
                            <div class="corner-decoration bottom-left"></div>
                            <div class="corner-decoration bottom-right"></div>

                            <!-- Left Sidebar -->
                           <div class="bg-[#007570] w-16 flex items-center justify-center relative">
    <div class="rotate-text text-white font-bold text-3xl tracking-wider">
        VISITOR PASS
    </div>
</div>
<!-- Main Content -->
<div class="flex-1 p-6 relative flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-center mb-8">
        <img src="assets/logo-green-yellow.png" alt="Logo" class="max-w-[180px] h-auto">
    </div>
    
    <!-- Venue Information -->
    <div class="mb-8 text-center">
        <div class="text-2xl font-bold text-gray-800 mb-3 venue-font">
            <strong>Venue:</strong><br><span id="visitorFloor">
           
        </span>
        </div>
        
        <div class="text-2xl font-bold text-gray-800 venue-font">
            Pass ID: 001
        </div>
    </div>

    <!-- Divider -->
    <div class="w-3/4 h-[2px] bg-gradient-to-r from-transparent via-[#0d9488] to-transparent mx-auto my-6 rounded-full"></div>

    <!-- Details 
    <div class="text-font" style="margin-bottom: 1.5rem;">
        <div cla ss="mb-2"><strong>Name:</strong> <span id="visitorName"></span></div>
        <div class="mb-2"><strong>Organization:</strong> <span id="visitorOrg"></span></div>
        <div class="mb-2"><strong>Date:</strong> <span id="visitorDate"></span></div>
        <div><strong>Host:</strong> <span id="visitorHost"></span></div>
    </div> -->

    <!-- Notice -->
    <div class="mb-8 text-center z-20">
                                            <div class="text-lg font-semibold text-gray-800 leading-relaxed text-font">
                                                Must be visibly worn at all times while on premises
                                            </div>
                                        </div>

    <!-- Fixed Bottom-Right Logo -->
                                        <div class="absolute bottom-0 right-0 z-10">
                                            <img src="assets/Picture3.png" alt="Bottom Logo" class="h-[8rem] opacity-30" />
                                        </div>
                                        
                                        <!-- Subtle pattern overlay -->
                                        <div class="absolute inset-0 opacity-5 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgdmlld0JveD0iMCAwIDYwIDYwIj48cmVjdCB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIGZpbGw9IiNmZmYiLz48cGF0aCBkPSJNMzAgMTVMMTUgMzAgMzAgNDUgNDUgMzB6IiBzdHJva2U9IiMwZDk0ODgiIHN0cm9rZS13aWR0aD0iMS41IiBmaWxsPSJub25lIi8+PC9zdmc+')]"></div>
</div>
                        </div>
                    </div>
                </div>
                <!-- Print Section -->
                <div class="text-center mt-3">
                    <button class="btn btn-outline-primary" onclick="printVisitorCard()">
                        <i class="bi bi-printer me-1"></i> Print Card
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
function openCameraModal(id, name, host, org, date, floor) {
    console.log('Opening modal with data:', { id, name, host, org, date, floor }); // Debug log
    
    // Show the modal
    const modal = new bootstrap.Modal('#cameraModal');
    modal.show();
    
    // Set all visitor details
    const visitorName = document.getElementById('visitorName');
    const visitorHost = document.getElementById('visitorHost');
    const visitorOrg = document.getElementById('visitorOrg');
    const visitorDate = document.getElementById('visitorDate');
    const visitorFloor = document.getElementById('visitorFloor');
    
    if (visitorName) visitorName.textContent = name || 'N/A';
    if (visitorHost) visitorHost.textContent = host || 'N/A';
    if (visitorOrg) visitorOrg.textContent = org || 'N/A';
    if (visitorDate) visitorDate.textContent = date || 'N/A';
    
    // Handle floor splitting for display
    if (visitorFloor) {
        if (floor && floor.includes(' - ')) {
            console.log('Splitting floor:', floor); // Debug log
            // Split on hyphen and join with line break
            const parts = floor.split(' - ').map(part => part.trim());
            visitorFloor.innerHTML = parts.join('<br>');
            console.log('Floor after split:', visitorFloor.innerHTML); // Debug log
        } else {
            console.log('Floor without split:', floor); // Debug log
            // No hyphen, display as is
            visitorFloor.textContent = floor || 'N/A';
        }
    } else {
        console.error('visitorFloor element not found!');
    }
}

function printVisitorCard() {
    const printContent = document.getElementById('cardPreview').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <style>
            @page { size: auto; margin: 0mm; }
            body { padding: 10px; }
            .corner-decoration { display: none; }
        </style>
        ${printContent}
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

function generatePagination(totalPages, currentPage, tab, searchTerm, activeTab) {
    let output = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-3">';
    
    const prevDisabled = currentPage <= 1 ? 'disabled' : '';
    output += `<li class='page-item ${prevDisabled}'><a class='page-link' href='?active_tab=${activeTab}&search=${encodeURIComponent(searchTerm)}&${tab}_page=${currentPage - 1}'>« Previous</a></li>`;

    const range = 2;
    const start = Math.max(1, currentPage - range);
    const end = Math.min(totalPages, currentPage + range);

    for (let i = start; i <= end; i++) {
        const active = i === currentPage ? 'active' : '';
        output += `<li class='page-item ${active}'><a class='page-link' href='?active_tab=${activeTab}&search=${encodeURIComponent(searchTerm)}&${tab}_page=${i}'>${i}</a></li>`;
    }

    const nextDisabled = currentPage >= totalPages ? 'disabled' : '';
    output += `<li class='page-item ${nextDisabled}'><a class='page-link' href='?active_tab=${activeTab}&search=${encodeURIComponent(searchTerm)}&${tab}_page=${currentPage + 1}'>Next »</a></li>`;

    output += '</ul></nav>';
    return output;
}

function updateTable(selector, guests, tab, totalPages, currentPage, searchTerm, activeTab) {
    const tbody = document.querySelector(selector);
    if (!tbody) return;
    
    tbody.innerHTML = guests.map(guest => `
        <tr class="animate__animated animate__fadeIn">
            <td class="visitor-cell">
                <div class="d-flex align-items-center">
                    <div class="symbol-enhanced">
                        ${guest.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="fw-medium">${escapeHtml(guest.name)}</div>
                        <div class="text-muted small">${escapeHtml(guest.email)}</div>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(guest.phone)}</td>
            <td>${escapeHtml(guest.host_name)}</td>
            <td>${guest.visit_date && guest.visit_date != '0000-00-00' ? escapeHtml(guest.visit_date) : 'N/A'}</td>
            ${tab === 'checkedin' ? `<td>${formatTime(guest.check_in_time)}</td>` : ''}
            ${tab === 'checkedout' ? `
                <td>${guest.check_out_time ? formatTime(guest.check_out_time) : 'N/A'}</td>
                <td>${escapeHtml(guest.floor_of_visit)}</td>
                <td><small class="text-muted">${escapeHtml(guest.reason)}</small></td>
            ` : ''}
            ${tab !== 'checkedout' ? `
                <td>
                    <button class="btn btn-sm me-2" style="background: var(--accent);" 
                        onclick="openCameraModal(${guest.id}, '${escapeHtml(guest.name)}', '${escapeHtml(guest.host_name)}', '${escapeHtml(guest.organization || 'N/A')}', '${escapeHtml(guest.visit_date)}', '${escapeHtml(guest.floor_of_visit || 'N/A')}')">
                        Card
                    </button>
                    ${tab === 'checkedin' ? 
                        `<form method="POST" action="vmc_dashboard.php?active_tab=${activeTab}" class="d-inline">
                            <input type="hidden" name="check_out_id" value="${guest.id}">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Check Out</button>
                        </form>` : 
                        tab === 'approved' ?
                        `<button class="btn btn-sm btn-success me-2" 
                            onclick="checkInGuest(${guest.id}, '${escapeHtml(guest.name)}')"
                            title="Check In Guest">
                            <i class="bi bi-box-arrow-in-right"></i> Check In
                        </button>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" 
                            data-bs-target="#checkoutModal${guest.id}">
                            Cancel
                        </button>` :
                        `<button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" 
                            data-bs-target="#checkoutModal${guest.id}">
                            Cancel
                        </button>`}
                </td>
            ` : ''}
        </tr>
    `).join('');

    const paginationContainer = document.querySelector(`#${tab} .card-body .pagination`) || document.createElement('div');
    paginationContainer.className = 'pagination';
    paginationContainer.innerHTML = generatePagination(totalPages, currentPage, tab, searchTerm, activeTab);
    if (!document.querySelector(`#${tab} .card-body .pagination`)) {
        document.querySelector(`#${tab} .card-body`).appendChild(paginationContainer);
    }
}

function escapeHtml(unsafe) {
    return unsafe ? unsafe.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;") : '';
}

function formatTime(timeString) {
    if (!timeString) return 'N/A';
    const time = new Date(timeString);
    return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function checkInGuest(guestId, guestName) {
    console.log('checkInGuest called with:', guestId, guestName);
    
    // Get guest host from the table row
    const guestRow = document.querySelector(`button[onclick*="checkInGuest(${guestId}"]`).closest('tr');
    const hostCell = guestRow ? guestRow.querySelector('td:nth-child(3)') : null; // Host is the 3rd column
    const guestHost = hostCell ? hostCell.textContent.trim() : 'Unknown Host';
    
    console.log('Found host:', guestHost);
    
    // Populate the modal with guest information
    const checkinGuestInitial = document.getElementById('checkinGuestInitial');
    const checkinGuestName = document.getElementById('checkinGuestName');
    const checkinGuestHost = document.getElementById('checkinGuestHost');
    
    if (checkinGuestInitial) checkinGuestInitial.textContent = guestName.charAt(0).toUpperCase();
    if (checkinGuestName) checkinGuestName.textContent = guestName;
    if (checkinGuestHost) checkinGuestHost.textContent = 'Host: ' + guestHost;
    
    // Store guest info for the confirm button
    const confirmBtn = document.getElementById('confirmCheckinBtn');
    if (confirmBtn) {
        confirmBtn.setAttribute('data-guest-id', guestId);
        confirmBtn.setAttribute('data-guest-name', guestName);
    }
    
    // Show the confirmation modal
    const modal = new bootstrap.Modal('#checkinModal');
    modal.show();
}

function showErrorModal(title, message) {
    const messageHeader = document.getElementById('messageModalHeader');
    const messageTitle = document.getElementById('messageModalTitle');
    const messageIcon = document.getElementById('messageModalIcon');
    const messageText = document.getElementById('messageModalText');
    
    if (messageHeader) messageHeader.className = 'modal-header modal-danger-header';
    if (messageTitle) messageTitle.textContent = title;
    if (messageIcon) messageIcon.className = 'bi bi-exclamation-triangle-fill text-danger mb-3';
    if (messageText) messageText.textContent = message;
    
    const modal = new bootstrap.Modal('#messageModal');
    modal.show();
}

// SINGLE DOMContentLoaded EVENT LISTENER - CONSOLIDATES ALL FUNCTIONALITY
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing all functionality');
    
    // Show welcome popup on every page load
    setTimeout(function() {
        const welcomeModal = new bootstrap.Modal('#welcomeModal', {
            backdrop: 'static',
            keyboard: false
        });
        welcomeModal.show();
    }, 800);
    
    // Handle check-in confirmation button
    const confirmCheckinBtn = document.getElementById('confirmCheckinBtn');
    if (confirmCheckinBtn) {
        console.log('Found confirmCheckinBtn, adding event listener');
        confirmCheckinBtn.addEventListener('click', function() {
            console.log('Confirm check-in button clicked');
            
            const guestId = this.getAttribute('data-guest-id');
            const guestName = this.getAttribute('data-guest-name');
            
            console.log('Guest ID:', guestId, 'Guest Name:', guestName);
            
            if (!guestId || !guestName) {
                console.error('Missing guest data');
                showErrorModal('Error', 'Guest information is missing. Please try again.');
                return;
            }
            
            // Hide the confirmation modal
            const checkinModal = bootstrap.Modal.getInstance('#checkinModal');
            if (checkinModal) checkinModal.hide();
            
            // Perform the check-in
            fetch('check_in_guest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `guest_id=${guestId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    
                    // Show success/error message modal
                    const messageModal = document.getElementById('messageModal');
                    const messageHeader = document.getElementById('messageModalHeader');
                    const messageTitle = document.getElementById('messageModalTitle');
                    const messageIcon = document.getElementById('messageModalIcon');
                    const messageText = document.getElementById('messageModalText');
                    
                    if (data.success) {
                        if (messageHeader) messageHeader.className = 'modal-header modal-header-enhanced';
                        if (messageTitle) messageTitle.textContent = 'Check-In Successful';
                        if (messageIcon) messageIcon.className = 'bi bi-check-circle-fill text-success mb-3';
                        if (messageText) messageText.textContent = `${guestName} has been successfully checked in!`;
                    } else {
                        if (messageHeader) messageHeader.className = 'modal-header modal-danger-header';
                        if (messageTitle) messageTitle.textContent = 'Check-In Failed';
                        if (messageIcon) messageIcon.className = 'bi bi-exclamation-triangle-fill text-danger mb-3';
                        if (messageText) messageText.textContent = 'Error: ' + (data.message || 'Unknown error occurred');
                    }
                    
                    const modal = new bootstrap.Modal('#messageModal');
                    modal.show();
                    
                } catch (e) {
                    console.error('JSON parse error:', e);
                    showErrorModal('Server Error', 'Invalid response from server. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showErrorModal('Network Error', 'Unable to connect to server. Please try again.');
            });
        });
    } else {
        console.error('confirmCheckinBtn not found!');
    }
    
    // Force page refresh when any tab is clicked
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('click', function(e) {
            // Prevent default Bootstrap tab behavior
            e.preventDefault();
            
            // Get the tab ID from data-bs-target
            const tabId = this.getAttribute('data-bs-target').replace('#', '');
            
            // Get current URL and update the active_tab parameter
            const url = new URL(window.location.href);
            url.searchParams.set('active_tab', tabId);
            
            // Force page reload with new URL
            window.location.href = url.toString();
        });
    });
    
    // Initialize the correct tab on page load
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('active_tab') || 'checkedin';
    const tabTrigger = document.getElementById(`${activeTab}-tab`);
    
    if (tabTrigger) {
        // Small delay to ensure Bootstrap is fully loaded
        setTimeout(() => {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }, 50);
    }
    
    // Handle print card button
    const printCardBtn = document.getElementById('printCardBtn');
    if (printCardBtn) {
        printCardBtn.addEventListener('click', printVisitorCard);
    }
    
    // Set default dates in export modal
    const exportModal = document.getElementById('exportModal');
    if (exportModal) {
        exportModal.addEventListener('show.bs.modal', function() {
            const today = new Date();
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            
            const startDate = document.getElementById('startDate');
            const endDate = document.getElementById('endDate');
            
            if (startDate) startDate.valueAsDate = yesterday;
            if (endDate) endDate.valueAsDate = today;
        });
    }
    
    console.log('All event listeners initialized successfully');
});
</script>
        <!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header modal-header-enhanced">
                <h5 class="modal-title">Export Visitors</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modal-body-enhanced">
                <form id="exportForm" action="export_visitors.php" method="GET">
                    <div class="mb-3">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" name="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" name="end_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="csvFormat" value="csv" checked>
                            <label class="form-check-label" for="csvFormat">CSV</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="excelFormat" value="excel">
                            <label class="form-check-label" for="excelFormat">Excel</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-enhanced btn-secondary-enhanced" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="submit" form="exportForm" class="btn-enhanced btn-export-enhanced">
                    <i class="bi bi-download me-2"></i>Export Data
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Loading Overlay -->
<div id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem; color: var(--primary) !important;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="text-secondary-enhanced">Loading visitor data...</div>
    </div>
</div>

<!-- Feedback Container -->
<div id="feedbackContainer" style="position: fixed; top: 80px; right: 20px; z-index: 1000; max-width: 350px;"></div>

<!-- Check-In Confirmation Modal -->
<div class="modal fade" id="checkinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header modal-header-enhanced">
                <h5 class="modal-title fw-bold">
                    Confirm Check-In
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-enhanced">
                <p class="mb-3">Are you sure you want to <strong>check in</strong> this visitor?</p>
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol-enhanced me-3" id="checkinGuestInitial">
                        <!-- Guest initial will be inserted here -->
                    </div>
                    <div>
                        <h6 class="mb-1 text-primary-enhanced" id="checkinGuestName"><!-- Guest name will be inserted here --></h6>
                        <small class="text-muted-enhanced" id="checkinGuestHost"><!-- Host will be inserted here --></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer modal-footer-enhanced">
                <button type="button" class="btn-enhanced btn-secondary-enhanced" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn-enhanced btn-primary-enhanced" id="confirmCheckinBtn">
                    Check In
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-enhanced">
            <div class="modal-header" id="messageModalHeader">
                <h5 class="modal-title fw-bold" id="messageModalTitle">
                    <!-- Title will be inserted here -->
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-enhanced text-center">
                <i id="messageModalIcon" class="mb-3" style="font-size: 48px;"></i>
                <p id="messageModalText" class="mb-0"></p>
            </div>
            <div class="modal-footer modal-footer-enhanced justify-content-center">
                <button type="button" class="btn-enhanced btn-primary-enhanced" data-bs-dismiss="modal" onclick="location.reload()">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>