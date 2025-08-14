<?php
/**
 * Floors & Offices Management API
 * File: floors_offices_api.php
 * Handles all CRUD operations for floors and offices management
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session for admin authentication
session_start();

// Database connection
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Set charset and timezone
$conn->set_charset("utf8");
date_default_timezone_set('Africa/Lagos');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the incoming request
error_log("Floors & Offices API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
error_log("Action: " . ($_GET['action'] ?? 'none'));

// Get the action from query parameters
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Create tables if they don't exist
createTablesIfNotExist();

try {
    switch ($action) {
        case 'test':
            handleTest();
            break;
        case 'get_all':
            handleGetAll();
            break;
        case 'create_floor':
            handleCreateFloor();
            break;
        case 'update_floor':
            handleUpdateFloor();
            break;
        case 'delete_floor':
            handleDeleteFloor();
            break;
        case 'toggle_floor_status':
            handleToggleFloorStatus();
            break;
        case 'create_office':
            handleCreateOffice();
            break;
        case 'update_office':
            handleUpdateOffice();
            break;
        case 'delete_office':
            handleDeleteOffice();
            break;
        case 'toggle_office_status':
            handleToggleOfficeStatus();
            break;
        case 'upload_maps':
            handleUploadMaps();
            break;
        case 'get_recent_maps':
            handleGetRecentMaps();
            break;
        case 'get_all_maps':
            handleGetAllMaps();
            break;
        case 'view_map':
            handleViewMap();
            break;
        case 'delete_map':
            handleDeleteMap();
            break;
        case 'export':
            handleExport();
            break;
        case 'get_statistics':
            handleGetStatistics();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action specified',
                'available_actions' => [
                    'test', 'get_all', 'create_floor', 'update_floor', 'delete_floor', 'toggle_floor_status',
                    'create_office', 'update_office', 'delete_office', 'toggle_office_status',
                    'upload_maps', 'get_recent_maps', 'get_all_maps', 'view_map', 'delete_map',
                    'export', 'get_statistics'
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Floors & Offices API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
} finally {
    $conn->close();
}

/**
 * Create necessary tables if they don't exist
 */
function createTablesIfNotExist() {
    global $conn;
    
    // Create floors table
    $floorsTable = "
        CREATE TABLE IF NOT EXISTS floors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            floor_number VARCHAR(50) NOT NULL,
            floor_name VARCHAR(100),
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_floor_number (floor_number),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // Create offices table
    $officesTable = "
        CREATE TABLE IF NOT EXISTS offices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            floor_id INT,
            office_name VARCHAR(100) NOT NULL,
            department VARCHAR(100),
            capacity INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (floor_id) REFERENCES floors(id) ON DELETE CASCADE,
            INDEX idx_floor_id (floor_id),
            INDEX idx_department (department),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // Create floor_maps table
    $mapsTable = "
        CREATE TABLE IF NOT EXISTS floor_maps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            floor_id INT,
            original_name VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            file_size INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (floor_id) REFERENCES floors(id) ON DELETE CASCADE,
            INDEX idx_floor_id (floor_id),
            INDEX idx_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->query($floorsTable);
    $conn->query($officesTable);
    $conn->query($mapsTable);
    
    if ($conn->error) {
        error_log("Error creating tables: " . $conn->error);
    }
}

/**
 * Test endpoint to verify API is working
 */
function handleTest() {
    global $conn;
    
    $response = [
        'success' => true,
        'message' => 'Floors & Offices API is working correctly',
        'timestamp' => date('Y-m-d H:i:s'),
        'database_status' => 'connected'
    ];
    
    // Test database connection and tables
    $result = $conn->query("SELECT COUNT(*) as total FROM floors");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['floors_count'] = (int)$row['total'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as total FROM offices");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['offices_count'] = (int)$row['total'];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}

/**
 * Get all floors and offices with statistics
 */
function handleGetAll() {
    global $conn;
    
    // Get floors with visitor counts
    $floorsQuery = "
        SELECT 
            f.id,
            f.floor_number,
            f.floor_name,
            f.description,
            f.is_active,
            f.created_at,
            f.updated_at,
            COALESCE(v.total_visitors, 0) as total_visitors,
            COALESCE(v.today_visitors, 0) as today_visitors,
            CASE WHEN m.floor_id IS NOT NULL THEN 1 ELSE 0 END as has_map
        FROM floors f
        LEFT JOIN (
            SELECT 
                SUBSTRING_INDEX(floor_of_visit, ' - ', 1) as floor_number,
                COUNT(*) as total_visitors,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_visitors
            FROM visitors 
            WHERE floor_of_visit IS NOT NULL 
            AND floor_of_visit != ''
            AND status IN ('checked_in', 'checked_out')
            GROUP BY SUBSTRING_INDEX(floor_of_visit, ' - ', 1)
        ) v ON f.floor_number = v.floor_number
        LEFT JOIN (
            SELECT DISTINCT floor_id 
            FROM floor_maps 
            WHERE floor_id IS NOT NULL
        ) m ON f.id = m.floor_id
        ORDER BY f.floor_number
    ";
    
    $floorsResult = $conn->query($floorsQuery);
    if (!$floorsResult) {
        throw new Exception("Floors query failed: " . $conn->error);
    }
    
    $floors = [];
    while ($row = $floorsResult->fetch_assoc()) {
        $floors[] = [
            'id' => (int)$row['id'],
            'floor_number' => $row['floor_number'],
            'floor_name' => $row['floor_name'],
            'description' => $row['description'],
            'is_active' => (bool)$row['is_active'],
            'total_visitors' => (int)$row['total_visitors'],
            'today_visitors' => (int)$row['today_visitors'],
            'has_map' => (bool)$row['has_map'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    // Get offices with visitor counts
    $officesQuery = "
        SELECT 
            o.id,
            o.floor_id,
            o.office_name,
            o.department,
            o.capacity,
            o.is_active,
            o.created_at,
            o.updated_at,
            f.floor_number,
            COALESCE(v.visitors_count, 0) as visitors_count,
            COALESCE(v.today_visitors, 0) as today_visitors
        FROM offices o
        LEFT JOIN floors f ON o.floor_id = f.id
        LEFT JOIN (
            SELECT 
                CONCAT(SUBSTRING_INDEX(floor_of_visit, ' - ', 1), ' - ', SUBSTRING_INDEX(floor_of_visit, ' - ', -1)) as office_location,
                COUNT(*) as visitors_count,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_visitors
            FROM visitors 
            WHERE floor_of_visit IS NOT NULL 
            AND floor_of_visit != ''
            AND status IN ('checked_in', 'checked_out')
            GROUP BY office_location
        ) v ON CONCAT(f.floor_number, ' - ', o.office_name) = v.office_location
        ORDER BY f.floor_number, o.office_name
    ";
    
    $officesResult = $conn->query($officesQuery);
    if (!$officesResult) {
        throw new Exception("Offices query failed: " . $conn->error);
    }
    
    $offices = [];
    while ($row = $officesResult->fetch_assoc()) {
        $offices[] = [
            'id' => (int)$row['id'],
            'floor_id' => (int)$row['floor_id'],
            'office_name' => $row['office_name'],
            'department' => $row['department'],
            'capacity' => $row['capacity'] ? (int)$row['capacity'] : null,
            'is_active' => (bool)$row['is_active'],
            'floor_number' => $row['floor_number'],
            'visitors_count' => (int)$row['visitors_count'],
            'today_visitors' => (int)$row['today_visitors'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    // Get overall statistics
    $statistics = getOverallStatistics();
    
    $response = [
        'success' => true,
        'floors' => $floors,
        'offices' => $offices,
        'statistics' => $statistics,
        'counts' => [
            'floors' => count($floors),
            'offices' => count($offices),
            'total_locations' => count($floors) + count($offices)
        ]
    ];
    
    echo json_encode($response, JSON_NUMERIC_CHECK);
}

/**
 * Create a new floor
 */
function handleCreateFloor() {
    global $conn;
    
    $floor_number = trim($_POST['floor_number'] ?? '');
    $floor_name = trim($_POST['floor_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    
    if (empty($floor_number)) {
        echo json_encode(['success' => false, 'message' => 'Floor number is required']);
        return;
    }
    
    // Check if floor number already exists
    $stmt = $conn->prepare("SELECT id FROM floors WHERE floor_number = ?");
    $stmt->bind_param("s", $floor_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Floor number already exists']);
        return;
    }
    $stmt->close();
    
    // Insert new floor
    $stmt = $conn->prepare("INSERT INTO floors (floor_number, floor_name, description, is_active) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $floor_number, $floor_name, $description, $is_active);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Floor created successfully',
            'id' => $newId
        ]);
    } else {
        throw new Exception("Failed to create floor: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Update an existing floor
 */
function handleUpdateFloor() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid floor ID']);
        return;
    }
    
    $floor_number = trim($_POST['floor_number'] ?? '');
    $floor_name = trim($_POST['floor_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    
    if (empty($floor_number)) {
        echo json_encode(['success' => false, 'message' => 'Floor number is required']);
        return;
    }
    
    // Check if floor number already exists for other floors
    $stmt = $conn->prepare("SELECT id FROM floors WHERE floor_number = ? AND id != ?");
    $stmt->bind_param("si", $floor_number, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Floor number already exists']);
        return;
    }
    $stmt->close();
    
    // Update floor
    $stmt = $conn->prepare("UPDATE floors SET floor_number = ?, floor_name = ?, description = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("sssii", $floor_number, $floor_name, $description, $is_active, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Floor updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Floor not found or no changes made']);
        }
    } else {
        throw new Exception("Failed to update floor: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Delete a floor
 */
function handleDeleteFloor() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid floor ID']);
        return;
    }
    
    $conn->autocommit(FALSE);
    
    try {
        // Delete related maps first
        $stmt = $conn->prepare("DELETE FROM floor_maps WHERE floor_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Delete related offices
        $stmt = $conn->prepare("DELETE FROM offices WHERE floor_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $deletedOffices = $stmt->affected_rows;
        $stmt->close();
        
        // Delete the floor
        $stmt = $conn->prepare("DELETE FROM floors WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $deletedFloor = $stmt->affected_rows;
        $stmt->close();
        
        $conn->commit();
        
        if ($deletedFloor > 0) {
            $message = 'Floor deleted successfully';
            if ($deletedOffices > 0) {
                $message .= " (and $deletedOffices related offices)";
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Floor not found']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    } finally {
        $conn->autocommit(TRUE);
    }
}

/**
 * Toggle floor status
 */
function handleToggleFloorStatus() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid floor ID']);
        return;
    }
    
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;
    
    $stmt = $conn->prepare("UPDATE floors SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_active, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Floor status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Floor not found']);
        }
    } else {
        throw new Exception("Failed to update floor status: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Create a new office
 */
function handleCreateOffice() {
    global $conn;
    
    $floor_id = intval($_POST['floor_id'] ?? 0);
    $office_name = trim($_POST['office_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    
    if ($floor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid floor']);
        return;
    }
    
    if (empty($office_name)) {
        echo json_encode(['success' => false, 'message' => 'Office name is required']);
        return;
    }
    
    // Check if office name already exists on the same floor
    $stmt = $conn->prepare("SELECT id FROM offices WHERE floor_id = ? AND office_name = ?");
    $stmt->bind_param("is", $floor_id, $office_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Office name already exists on this floor']);
        return;
    }
    $stmt->close();
    
    // Insert new office
    $stmt = $conn->prepare("INSERT INTO offices (floor_id, office_name, department, capacity, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $floor_id, $office_name, $department, $capacity, $is_active);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Office created successfully',
            'id' => $newId
        ]);
    } else {
        throw new Exception("Failed to create office: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Update an existing office
 */
function handleUpdateOffice() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid office ID']);
        return;
    }
    
    $floor_id = intval($_POST['floor_id'] ?? 0);
    $office_name = trim($_POST['office_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $capacity = !empty($_POST['capacity']) ? intval($_POST['capacity']) : null;
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    
    if ($floor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid floor']);
        return;
    }
    
    if (empty($office_name)) {
        echo json_encode(['success' => false, 'message' => 'Office name is required']);
        return;
    }
    
    // Check if office name already exists on the same floor for other offices
    $stmt = $conn->prepare("SELECT id FROM offices WHERE floor_id = ? AND office_name = ? AND id != ?");
    $stmt->bind_param("isi", $floor_id, $office_name, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Office name already exists on this floor']);
        return;
    }
    $stmt->close();
    
    // Update office
    $stmt = $conn->prepare("UPDATE offices SET floor_id = ?, office_name = ?, department = ?, capacity = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("issiii", $floor_id, $office_name, $department, $capacity, $is_active, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Office updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Office not found or no changes made']);
        }
    } else {
        throw new Exception("Failed to update office: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Delete an office
 */
function handleDeleteOffice() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid office ID']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM offices WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Office deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Office not found']);
        }
    } else {
        throw new Exception("Failed to delete office: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Toggle office status
 */
function handleToggleOfficeStatus() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid office ID']);
        return;
    }
    
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;
    
    $stmt = $conn->prepare("UPDATE offices SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_active, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Office status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Office not found']);
        }
    } else {
        throw new Exception("Failed to update office status: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Handle file uploads for floor maps
 */
function handleUploadMaps() {
    global $conn;
    
    if (empty($_FILES['maps'])) {
        echo json_encode(['success' => false, 'message' => 'No files uploaded']);
        return;
    }
    
    $uploadDir = 'uploads/floor_maps/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadedFiles = [];
    $errors = [];
    
    $files = $_FILES['maps'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading $fileName";
            continue;
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid file type for $fileName";
            continue;
        }
        
        // Validate file size (10MB max)
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "File too large: $fileName";
            continue;
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = uniqid('map_', true) . '.' . $fileExtension;
        $filePath = $uploadDir . $uniqueFileName;
        
        if (move_uploaded_file($fileTmpName, $filePath)) {
            // Save to database
            $stmt = $conn->prepare("INSERT INTO floor_maps (original_name, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $fileName, $uniqueFileName, $filePath, $fileType, $fileSize);
            
            if ($stmt->execute()) {
                $uploadedFiles[] = [
                    'id' => $conn->insert_id,
                    'original_name' => $fileName,
                    'file_name' => $uniqueFileName
                ];
            } else {
                $errors[] = "Database error for $fileName";
                unlink($filePath); // Remove file if DB insert failed
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to move uploaded file: $fileName";
        }
    }
    
    $response = [
        'success' => count($uploadedFiles) > 0,
        'uploaded_count' => count($uploadedFiles),
        'uploaded_files' => $uploadedFiles
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = count($uploadedFiles) > 0 ? 
            "Partial upload success. Some files failed." : 
            "Upload failed: " . implode(', ', $errors);
    } else {
        $response['message'] = "Successfully uploaded " . count($uploadedFiles) . " file(s)";
    }
    
    echo json_encode($response);
}

/**
 * Get recent uploaded maps
 */
function handleGetRecentMaps() {
    global $conn;
    
    $sql = "
        SELECT 
            m.id,
            m.original_name,
            m.file_name,
            m.file_type,
            m.file_size,
            m.uploaded_at,
            f.floor_number
        FROM floor_maps m
        LEFT JOIN floors f ON m.floor_id = f.id
        ORDER BY m.uploaded_at DESC
        LIMIT 5
    ";
    
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $maps = [];
    while ($row = $result->fetch_assoc()) {
        $maps[] = [
            'id' => (int)$row['id'],
            'original_name' => $row['original_name'],
            'file_name' => $row['file_name'],
            'file_type' => $row['file_type'],
            'file_size' => (int)$row['file_size'],
            'floor_number' => $row['floor_number'],
            'uploaded_at' => $row['uploaded_at']
        ];
    }
    
    echo json_encode(['success' => true, 'maps' => $maps]);
}

/**
 * Get all uploaded maps
 */
function handleGetAllMaps() {
    global $conn;
    
    $sql = "
        SELECT 
            m.id,
            m.original_name,
            m.file_name,
            m.file_type,
            m.file_size,
            m.uploaded_at,
            f.floor_number
        FROM floor_maps m
        LEFT JOIN floors f ON m.floor_id = f.id
        ORDER BY m.uploaded_at DESC
    ";
    
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $maps = [];
    while ($row = $result->fetch_assoc()) {
        $maps[] = [
            'id' => (int)$row['id'],
            'original_name' => $row['original_name'],
            'file_name' => $row['file_name'],
            'file_type' => $row['file_type'],
            'file_size' => (int)$row['file_size'],
            'floor_number' => $row['floor_number'],
            'uploaded_at' => $row['uploaded_at']
        ];
    }
    
    echo json_encode(['success' => true, 'maps' => $maps]);
}

/**
 * View/download a map file
 */
function handleViewMap() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid map ID']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT original_name, file_path, file_type FROM floor_maps WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Map not found']);
        return;
    }
    
    $map = $result->fetch_assoc();
    $stmt->close();
    
    $filePath = $map['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found on server']);
        return;
    }
    
    // Set appropriate headers
    header('Content-Type: ' . $map['file_type']);
    header('Content-Disposition: inline; filename="' . $map['original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=3600');
    
    readfile($filePath);
    exit();
}

/**
 * Delete a map file
 */
function handleDeleteMap() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid map ID']);
        return;
    }
    
    // Get file info
    $stmt = $conn->prepare("SELECT file_path FROM floor_maps WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Map not found']);
        return;
    }
    
    $map = $result->fetch_assoc();
    $stmt->close();
    
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM floor_maps WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Delete physical file
        if (file_exists($map['file_path'])) {
            unlink($map['file_path']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
    } else {
        throw new Exception("Failed to delete map: " . $stmt->error);
    }
    
    $stmt->close();
}

/**
 * Export floors and offices data to CSV
 */
function handleExport() {
    global $conn;
    
    try {
        $sql = "
            SELECT 
                'Floor' as type,
                f.floor_number,
                f.floor_name as name,
                'Building Management' as department,
                COALESCE(v.total_visitors, 0) as guests_received,
                CASE WHEN f.is_active THEN 'Active' ELSE 'Inactive' END as status,
                f.created_at,
                CASE WHEN m.floor_id IS NOT NULL THEN 'Yes' ELSE 'No' END as has_map
            FROM floors f
            LEFT JOIN (
                SELECT 
                    SUBSTRING_INDEX(floor_of_visit, ' - ', 1) as floor_number,
                    COUNT(*) as total_visitors
                FROM visitors 
                WHERE floor_of_visit IS NOT NULL 
                AND floor_of_visit != ''
                AND status IN ('checked_in', 'checked_out')
                GROUP BY SUBSTRING_INDEX(floor_of_visit, ' - ', 1)
            ) v ON f.floor_number = v.floor_number
            LEFT JOIN (
                SELECT DISTINCT floor_id 
                FROM floor_maps 
                WHERE floor_id IS NOT NULL
            ) m ON f.id = m.floor_id
            
            UNION ALL
            
            SELECT 
                'Office' as type,
                f.floor_number,
                o.office_name as name,
                COALESCE(o.department, 'N/A') as department,
                COALESCE(v.visitors_count, 0) as guests_received,
                CASE WHEN o.is_active THEN 'Active' ELSE 'Inactive' END as status,
                o.created_at,
                'N/A' as has_map
            FROM offices o
            LEFT JOIN floors f ON o.floor_id = f.id
            LEFT JOIN (
                SELECT 
                    CONCAT(SUBSTRING_INDEX(floor_of_visit, ' - ', 1), ' - ', SUBSTRING_INDEX(floor_of_visit, ' - ', -1)) as office_location,
                    COUNT(*) as visitors_count
                FROM visitors 
                WHERE floor_of_visit IS NOT NULL 
                AND floor_of_visit != ''
                AND status IN ('checked_in', 'checked_out')
                GROUP BY office_location
            ) v ON CONCAT(f.floor_number, ' - ', o.office_name) = v.office_location
            
            ORDER BY floor_number, type, name
        ";
        
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="floors_offices_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, [
            'Type',
            'Floor Number',
            'Name',
            'Department',
            'Guests Received',
            'Status',
            'Has Floor Map',
            'Created Date',
            'Export Date',
            'Export Time'
        ]);
        
        // CSV Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['type'],
                $row['floor_number'] ?? 'N/A',
                $row['name'] ?? 'Unnamed',
                $row['department'] ?? 'N/A',
                $row['guests_received'],
                $row['status'],
                $row['has_map'],
                date('Y-m-d', strtotime($row['created_at'])),
                date('Y-m-d'),
                date('H:i:s')
            ]);
        }
        
        fclose($output);
        exit();
        
    } catch (Exception $e) {
        // If export fails, return JSON error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Export failed: ' . $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Get overall statistics
 */
function handleGetStatistics() {
    global $conn;
    
    $statistics = getOverallStatistics();
    
    echo json_encode([
        'success' => true,
        'statistics' => $statistics
    ]);
}

/**
 * Helper function to get overall statistics
 */
function getOverallStatistics() {
    global $conn;
    
    $stats = [];
    
    try {
        // Total floors
        $result = $conn->query("SELECT COUNT(*) as total FROM floors");
        $stats['total_floors'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
        // Total offices
        $result = $conn->query("SELECT COUNT(*) as total FROM offices");
        $stats['total_offices'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
        // Total visitors to all floors/offices
        $result = $conn->query("
            SELECT COUNT(*) as total 
            FROM visitors 
            WHERE floor_of_visit IS NOT NULL 
            AND floor_of_visit != ''
            AND status IN ('checked_in', 'checked_out')
        ");
        $stats['total_visitors'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
        // Today's visitors
        $result = $conn->query("
            SELECT COUNT(*) as total 
            FROM visitors 
            WHERE DATE(created_at) = CURDATE()
            AND floor_of_visit IS NOT NULL 
            AND floor_of_visit != ''
            AND status IN ('checked_in', 'checked_out')
        ");
        $stats['today_visitors'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
        // Active floors
        $result = $conn->query("SELECT COUNT(*) as total FROM floors WHERE is_active = 1");
        $stats['active_floors'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
        // Active offices
        $result = $conn->query("SELECT COUNT(*) as total FROM offices WHERE is_active = 1");
        $stats['active_offices'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
        // Floors with maps
        $result = $conn->query("
            SELECT COUNT(DISTINCT floor_id) as total 
            FROM floor_maps 
            WHERE floor_id IS NOT NULL
        ");
        $stats['floors_with_maps'] = $result ? (int)$result->fetch_assoc()['total'] : 0;
        
    } catch (Exception $e) {
        error_log("Error getting statistics: " . $e->getMessage());
        // Return default values if there's an error
        $stats = [
            'total_floors' => 0,
            'total_offices' => 0,
            'total_visitors' => 0,
            'today_visitors' => 0,
            'active_floors' => 0,
            'active_offices' => 0,
            'floors_with_maps' => 0
        ];
    }
    
    return $stats;
}

?>