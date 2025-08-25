<?php
session_start();

// Database connection
require 'db_connection.php';

// Set timezone
date_default_timezone_set('Africa/Lagos');

// Admin authentication check
// if (!isset($_SESSION['admin_id'])) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit();
// }

header('Content-Type: application/json');

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_visitors':
            getVisitors();
            break;
        case 'get_hosts':
            getHosts();
            break;
        case 'delete_visitor':
            deleteVisitor();
            break;
        case 'bulk_delete':
            bulkDelete();
            break;
        case 'export_visitors':
            exportVisitors();
            break;
        case 'print_badges':
            printBadges();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function getVisitors() {
    global $conn;

    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause based on filters
    $whereConditions = [];
    $params = [];
    $types = '';

    // Search filter
    if (!empty($_GET['search'])) {
        $whereConditions[] = "(name LIKE ? OR email LIKE ? OR organization LIKE ? OR host_name LIKE ?)";
        $searchTerm = '%' . $_GET['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= 'ssss';
    }

    // Status filter
    if (!empty($_GET['status'])) {
        $whereConditions[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }

    // Host filter
    if (!empty($_GET['host'])) {
        $whereConditions[] = "host_name = ?";
        $params[] = $_GET['host'];
        $types .= 's';
    }

    // Date filter
    if (!empty($_GET['date'])) {
        $whereConditions[] = "DATE(check_in_time) = ?";
        $params[] = $_GET['date'];
        $types .= 's';
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM visitors $whereClause";
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Get visitors data
    $query = "SELECT id, name, email, phone, organization, host_name, status, visit_date, 
                     check_in_time, check_out_time, floor_of_visit, reason, created_at
              FROM visitors 
              $whereClause 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $visitors = [];
    while ($row = $result->fetch_assoc()) {
        $visitors[] = $row;
    }
    $stmt->close();

    // Calculate pagination
    $totalPages = ceil($totalRecords / $limit);
    $showingStart = $totalRecords > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $limit, $totalRecords);

    echo json_encode([
        'visitors' => $visitors,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $limit
        ],
        'stats' => [
            'total' => $totalRecords,
            'showing_start' => $showingStart,
            'showing_end' => $showingEnd
        ]
    ]);
}

function getHosts() {
    global $conn;

    $query = "SELECT DISTINCT name FROM employees WHERE name IS NOT NULL AND name != '' ORDER BY name";
    $result = $conn->query($query);

    $hosts = [];
    while ($row = $result->fetch_assoc()) {
        $hosts[] = $row;
    }

    echo json_encode($hosts);
}

function deleteVisitor() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid visitor ID']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM visitors WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Visitor deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete visitor']);
    }
    $stmt->close();
}

function bulkDelete() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No visitors selected']);
        return;
    }

    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid visitor IDs']);
        return;
    }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $query = "DELETE FROM visitors WHERE id IN ($placeholders)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

    if ($stmt->execute()) {
        $deletedCount = $stmt->affected_rows;
        echo json_encode(['success' => true, 'message' => "$deletedCount visitors deleted successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete visitors']);
    }
    $stmt->close();
}

function exportVisitors() {
    global $conn;

    $ids = $_GET['ids'] ?? '';
    if (empty($ids)) {
        echo json_encode(['error' => 'No visitors selected']);
        return;
    }

    $ids = explode(',', $ids);
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        echo json_encode(['error' => 'Invalid visitor IDs']);
        return;
    }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $query = "SELECT name, email, phone, organization, host_name, status, visit_date, 
                     check_in_time, check_out_time, floor_of_visit, reason, created_at
              FROM visitors 
              WHERE id IN ($placeholders)
              ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="visitors_export_' . date('Y-m-d_H-i-s') . '.csv"');

    // Output CSV
    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, [
        'Name',
        'Email',
        'Phone',
        'Organization',
        'Host',
        'Status',
        'Visit Date',
        'Check-In Time',
        'Check-Out Time',
        'Floor',
        'Reason',
        'Created At'
    ]);

    // CSV data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['organization'],
            $row['host_name'],
            $row['status'],
            $row['visit_date'],
            $row['check_in_time'],
            $row['check_out_time'],
            $row['floor_of_visit'],
            $row['reason'],
            $row['created_at']
        ]);
    }

    fclose($output);
    $stmt->close();
}

function printBadges() {
    global $conn;

    $ids = $_GET['ids'] ?? '';
    if (empty($ids)) {
        echo json_encode(['error' => 'No visitors selected']);
        return;
    }

    $ids = explode(',', $ids);
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        echo json_encode(['error' => 'Invalid visitor IDs']);
        return;
    }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $query = "SELECT id, name, organization, host_name, visit_date, unique_code, qr_code
              FROM visitors 
              WHERE id IN ($placeholders)
              ORDER BY name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    // Set headers for HTML output
    header('Content-Type: text/html');

    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Visitor Badges</title>
        <style>
            @media print {
                .badge { page-break-after: always; }
                .badge:last-child { page-break-after: avoid; }
            }
            .badge {
                width: 300px;
                height: 400px;
                border: 2px solid #007570;
                margin: 20px;
                padding: 20px;
                text-align: center;
                font-family: Arial, sans-serif;
                display: inline-block;
                vertical-align: top;
            }
            .badge h2 { color: #007570; margin: 10px 0; }
            .badge .name { font-size: 24px; font-weight: bold; margin: 20px 0; }
            .badge .organization { font-size: 16px; color: #666; margin: 10px 0; }
            .badge .host { font-size: 14px; margin: 10px 0; }
            .badge .date { font-size: 12px; color: #888; margin: 10px 0; }
            .badge .code { font-size: 10px; font-family: monospace; margin: 10px 0; }
        </style>
    </head>
    <body>';

    while ($row = $result->fetch_assoc()) {
        echo '<div class="badge">
            <h2>AATC VISITOR</h2>
            <div class="name">' . htmlspecialchars($row['name']) . '</div>
            <div class="organization">' . htmlspecialchars($row['organization'] ?: 'N/A') . '</div>
            <div class="host">Host: ' . htmlspecialchars($row['host_name'] ?: 'Walk-In') . '</div>
            <div class="date">Visit Date: ' . htmlspecialchars($row['visit_date'] ?: date('Y-m-d')) . '</div>
            <div class="code">Code: ' . htmlspecialchars($row['unique_code']) . '</div>
        </div>';
    }

    echo '<script>window.print();</script>
    </body>
    </html>';

    $stmt->close();
}

$conn->close();
