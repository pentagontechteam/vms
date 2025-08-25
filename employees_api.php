<?php
session_start();

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require 'db_connection.php';

// Set charset and timezone
$conn->set_charset("utf8");
date_default_timezone_set('Africa/Lagos');

// Admin authentication check
// if (!isset($_SESSION['admin_id'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit();
// }

// Handle different actions
$action = $_GET['action'] ?? '';
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

// Set appropriate headers based on action
if ($action === 'export_employees') {
    // Headers will be set in the export function
} else {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    switch ($action) {
        case 'get_employees':
            getEmployees();
            break;
        case 'get_stats':
            getStats();
            break;
        case 'create_employee':
            createEmployee();
            break;
        case 'update_employee':
            updateEmployee();
            break;
        case 'delete_employee':
            deleteEmployee();
            break;
        case 'bulk_delete':
            bulkDelete();
            break;
        case 'export_employees':
            exportEmployees();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    error_log("Employees API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
} finally {
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
}

function getEmployees() {
    global $conn;

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    error_log("Getting employees for page: $page");

    try {
        // Build WHERE clause for filters
        $whereConditions = [];
        $params = [];
        $types = '';

        // Search functionality
        if (!empty($_GET['search'])) {
            $searchTerm = '%' . $_GET['search'] . '%';
            $whereConditions[] = "(name LIKE ? OR email LIKE ? OR designation LIKE ? OR organization LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= 'ssss';
        }

        // Department filter
        if (!empty($_GET['department'])) {
            $whereConditions[] = "designation LIKE ?";
            $params[] = '%' . $_GET['department'] . '%';
            $types .= 's';
        }

        // Status filter (based on profile completion)
        if (!empty($_GET['status'])) {
            if ($_GET['status'] === 'active') {
                $whereConditions[] = "profile_completed = 1";
            } elseif ($_GET['status'] === 'inactive') {
                $whereConditions[] = "profile_completed = 0";
            }
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM employees $whereClause";
        if (!empty($params)) {
            $countStmt = $conn->prepare($countQuery);
            if (!$countStmt) {
                throw new Exception("Prepare failed for count query: " . $conn->error);
            }
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            $result = $conn->query($countQuery);
            if (!$result) {
                throw new Exception("Count query failed: " . $conn->error);
            }
            $totalRecords = $result->fetch_assoc()['total'];
        }

        // Get employees with guest count
        $query = "
            SELECT e.*, 
                   COALESCE(guest_counts.guest_count, 0) as guest_count
            FROM employees e
            LEFT JOIN (
                SELECT employee_id, COUNT(*) as guest_count 
                FROM visitors 
                WHERE status IN ('pending', 'approved', 'checked_in') 
                GROUP BY employee_id
            ) guest_counts ON e.id = guest_counts.employee_id
            $whereClause 
            ORDER BY e.created_at DESC 
            LIMIT ? OFFSET ?
        ";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for employees query: " . $conn->error);
        }

        $allParams = $params;
        $allParams[] = $limit;
        $allParams[] = $offset;
        $allTypes = $types . 'ii';

        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        $stmt->close();

        // Apply host filter after getting guest counts
        if (!empty($_GET['host_filter'])) {
            if ($_GET['host_filter'] === 'hosts_only') {
                $employees = array_filter($employees, function ($emp) {
                    return $emp['guest_count'] > 0;
                });
            } elseif ($_GET['host_filter'] === 'non_hosts') {
                $employees = array_filter($employees, function ($emp) {
                    return $emp['guest_count'] == 0;
                });
            }
            // Recalculate totals for filtered results
            $totalRecords = count($employees);
            $employees = array_slice($employees, 0, $limit);
        }

        // Calculate pagination
        $totalPages = ceil($totalRecords / $limit);
        $showingStart = $totalRecords > 0 ? $offset + 1 : 0;
        $showingEnd = min($offset + $limit, $totalRecords);

        error_log("Retrieved " . count($employees) . " employees");

        echo json_encode([
            'success' => true,
            'employees' => array_values($employees), // Reset array keys
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit
            ],
            'stats' => [
                'total' => (int)$totalRecords,
                'showing_start' => $showingStart,
                'showing_end' => $showingEnd
            ]
        ]);
    } catch (Exception $e) {
        error_log("Exception in getEmployees: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error getting employees: ' . $e->getMessage()]);
    }
}

function getStats() {
    global $conn;

    try {
        $today = date('Y-m-d');

        // Total employees
        $totalEmployees = 0;
        $result = $conn->query("SELECT COUNT(*) as count FROM employees");
        if ($result) {
            $totalEmployees = $result->fetch_assoc()['count'];
        }

        // Active hosts (employees with assigned guests)
        $activeHosts = 0;
        $result = $conn->query("
            SELECT COUNT(DISTINCT employee_id) as count 
            FROM visitors 
            WHERE employee_id IS NOT NULL 
            AND status IN ('pending', 'approved', 'checked_in')
        ");
        if ($result) {
            $activeHosts = $result->fetch_assoc()['count'];
        }

        // Today's hosts (employees hosting visitors today)
        $todaysHosts = 0;
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT employee_id) as count 
            FROM visitors 
            WHERE employee_id IS NOT NULL 
            AND DATE(visit_date) = ?
            AND status IN ('pending', 'approved', 'checked_in')
        ");
        if ($stmt) {
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $todaysHosts = $result->fetch_assoc()['count'];
            $stmt->close();
        }

        // Total assigned guests
        $totalGuests = 0;
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM visitors 
            WHERE employee_id IS NOT NULL 
            AND status IN ('pending', 'approved', 'checked_in')
        ");
        if ($result) {
            $totalGuests = $result->fetch_assoc()['count'];
        }

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_employees' => (int)$totalEmployees,
                'active_hosts' => (int)$activeHosts,
                'todays_hosts' => (int)$todaysHosts,
                'total_guests' => (int)$totalGuests
            ]
        ]);
    } catch (Exception $e) {
        error_log("Exception in getStats: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error getting stats: ' . $e->getMessage()]);
    }
}

function createEmployee() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $data = $input['data'] ?? [];

    error_log("Creating employee with data: " . json_encode($data));

    if (empty($data['name']) || empty($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        return;
    }

    try {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM employees WHERE email = ?");
        $checkStmt->bind_param("s", $data['email']);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            $checkStmt->close();
            return;
        }
        $checkStmt->close();

        // Insert new employee
        $stmt = $conn->prepare("
            INSERT INTO employees (name, email, phone, country_code, designation, organization, profile_completed, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->bind_param(
            "ssssss",
            $data['name'],
            $data['email'],
            $data['phone'] ?? '',
            $data['country_code'] ?? '+234',
            $data['designation'] ?? '',
            $data['organization'] ?? ''
        );

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            error_log("Employee created successfully with ID: $insertId");
            echo json_encode(['success' => true, 'message' => 'Employee created successfully', 'id' => $insertId]);
        } else {
            throw new Exception('Failed to create employee: ' . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Exception in createEmployee: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error creating employee: ' . $e->getMessage()]);
    }
}

function updateEmployee() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $data = $input['data'] ?? [];

    error_log("Updating employee with data: " . json_encode($data));

    if (empty($data['id']) || empty($data['name']) || empty($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'ID, name and email are required']);
        return;
    }

    try {
        // Check if email already exists for different employee
        $checkStmt = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $checkStmt->bind_param("si", $data['email'], $data['id']);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            $checkStmt->close();
            return;
        }
        $checkStmt->close();

        // Update employee
        $stmt = $conn->prepare("
            UPDATE employees 
            SET name = ?, email = ?, phone = ?, country_code = ?, designation = ?, organization = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssssssi",
            $data['name'],
            $data['email'],
            $data['phone'] ?? '',
            $data['country_code'] ?? '+234',
            $data['designation'] ?? '',
            $data['organization'] ?? '',
            $data['id']
        );

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                error_log("Employee updated successfully");
                echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made or employee not found']);
            }
        } else {
            throw new Exception('Failed to update employee: ' . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Exception in updateEmployee: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating employee: ' . $e->getMessage()]);
    }
}

function deleteEmployee() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    error_log("Deleting employee with ID: $id");

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        return;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Update visitors to remove employee reference
        $updateVisitorsStmt = $conn->prepare("UPDATE visitors SET employee_id = NULL WHERE employee_id = ?");
        $updateVisitorsStmt->bind_param("i", $id);
        $updateVisitorsStmt->execute();
        $updateVisitorsStmt->close();

        // Delete employee
        $deleteStmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $deleteStmt->bind_param("i", $id);

        if ($deleteStmt->execute()) {
            if ($deleteStmt->affected_rows > 0) {
                $conn->commit();
                error_log("Employee deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
            }
        } else {
            $conn->rollback();
            throw new Exception('Failed to delete employee: ' . $deleteStmt->error);
        }

        $deleteStmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Exception in deleteEmployee: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting employee: ' . $e->getMessage()]);
    }
}

function bulkDelete() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    error_log("Bulk deleting employees with IDs: " . json_encode($ids));

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No employees selected']);
        return;
    }

    // Sanitize IDs
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee IDs']);
        return;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';

        // Update visitors to remove employee references
        $updateQuery = "UPDATE visitors SET employee_id = NULL WHERE employee_id IN ($placeholders)";
        $updateStmt = $conn->prepare($updateQuery);
        if (!$updateStmt) {
            throw new Exception('Failed to prepare update statement: ' . $conn->error);
        }

        $types = str_repeat('i', count($ids));
        $updateStmt->bind_param($types, ...$ids);
        $updateStmt->execute();
        $updateStmt->close();

        // Delete employees
        $deleteQuery = "DELETE FROM employees WHERE id IN ($placeholders)";
        $deleteStmt = $conn->prepare($deleteQuery);
        if (!$deleteStmt) {
            throw new Exception('Failed to prepare delete statement: ' . $conn->error);
        }

        $deleteStmt->bind_param($types, ...$ids);

        if ($deleteStmt->execute()) {
            $deletedCount = $deleteStmt->affected_rows;
            $conn->commit();
            error_log("Successfully bulk deleted $deletedCount employees");
            echo json_encode(['success' => true, 'message' => "$deletedCount employee(s) deleted successfully"]);
        } else {
            $conn->rollback();
            throw new Exception('Failed to bulk delete employees: ' . $deleteStmt->error);
        }

        $deleteStmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Exception in bulkDelete: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error bulk deleting employees: ' . $e->getMessage()]);
    }
}

function exportEmployees() {
    global $conn;

    error_log("Exporting employees");

    try {
        // Build WHERE clause for filters (same as getEmployees)
        $whereConditions = [];
        $params = [];
        $types = '';

        // Handle specific IDs for bulk export
        if (!empty($_GET['ids'])) {
            $ids = explode(',', $_GET['ids']);
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function ($id) {
                return $id > 0;
            });

            if (!empty($ids)) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $whereConditions[] = "e.id IN ($placeholders)";
                $params = array_merge($params, $ids);
                $types .= str_repeat('i', count($ids));
            }
        } else {
            // Apply filters for full export
            if (!empty($_GET['search'])) {
                $searchTerm = '%' . $_GET['search'] . '%';
                $whereConditions[] = "(e.name LIKE ? OR e.email LIKE ? OR e.designation LIKE ? OR e.organization LIKE ?)";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                $types .= 'ssss';
            }

            if (!empty($_GET['department'])) {
                $whereConditions[] = "e.designation LIKE ?";
                $params[] = '%' . $_GET['department'] . '%';
                $types .= 's';
            }

            if (!empty($_GET['status'])) {
                if ($_GET['status'] === 'active') {
                    $whereConditions[] = "e.profile_completed = 1";
                } elseif ($_GET['status'] === 'inactive') {
                    $whereConditions[] = "e.profile_completed = 0";
                }
            }
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get employees with guest count
        $query = "
            SELECT e.name, e.email, e.phone, e.country_code, e.designation, e.organization, 
                   e.profile_completed, e.created_at,
                   COALESCE(guest_counts.guest_count, 0) as guest_count
            FROM employees e
            LEFT JOIN (
                SELECT employee_id, COUNT(*) as guest_count 
                FROM visitors 
                WHERE status IN ('pending', 'approved', 'checked_in') 
                GROUP BY employee_id
            ) guest_counts ON e.id = guest_counts.employee_id
            $whereClause 
            ORDER BY e.created_at DESC
        ";

        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Failed to prepare export query: ' . $conn->error);
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
            if (!$result) {
                throw new Exception('Export query failed: ' . $conn->error);
            }
        }

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        // Output CSV with BOM for Excel compatibility
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        // CSV headers
        fputcsv($output, [
            'Name',
            'Email',
            'Phone',
            'Country Code',
            'Designation',
            'Organization',
            'Profile Completed',
            'Assigned Guests',
            'Created At'
        ]);

        // Output data rows
        while ($row = $result->fetch_assoc()) {
            $csvRow = [
                $row['name'],
                $row['email'],
                $row['phone'] ?: '',
                $row['country_code'] ?: '+234',
                $row['designation'] ?: '',
                $row['organization'] ?: '',
                $row['profile_completed'] ? 'Yes' : 'No',
                $row['guest_count'],
                $row['created_at']
            ];
            fputcsv($output, $csvRow);
        }

        fclose($output);

        if (isset($stmt)) {
            $stmt->close();
        }

        error_log("Successfully exported employees");
    } catch (Exception $e) {
        error_log("Exception in exportEmployees: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Export failed: ' . $e->getMessage()]);
    }
}
