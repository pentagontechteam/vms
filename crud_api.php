function deleteRecords() {
global $conn;

$input = json_decode(file_get_contents('php://input'), true);
$table = $input['table'] ?? '';
$ids = $input['ids'] ?? [];

error_log("Deleting records from table: $table, IDs: " . json_encode($ids));

if (empty($table) || !isValidTableName($table) || empty($ids)) {
echo json_encode(['success' => false, 'message' => 'Invalid input data']);
return;
}

try {
// Find the primary key
$primaryKey = getPrimaryKey($table);
if (!$primaryKey) {
echo json_encode(['success' => false, 'message' => 'Primary key not found for table']);
return;
}

// Sanitize IDs
$ids = array_filter($ids, function($id) {
return !empty($id) && $id !== '';
});

if (empty($ids)) {
echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
return;
}

$placeholders = str_repeat('?,', count($ids) - 1) . '?';
$query = "DELETE FROM `$table` WHERE `$primaryKey` IN ($placeholders)";

$stmt = $conn->prepare($query);
if (!$stmt) {
throw new Exception('Failed to prepare statement: ' . $conn->error);
}

// Use string type for all IDs to handle mixed data types
$types = str_repeat('s', count($ids));
$stmt->bind_param($types, ...$ids);

if ($stmt->execute()) {
$deletedCount = $stmt->affected_rows;
error_log("Successfully deleted $deletedCount records");
echo json_encode(['success' => true, 'message' => "$deletedCount record(s) deleted successfully"]);
} else {
throw new Exception('Failed to delete records: '<?php
                                                session_start();

                                                // Error reporting for debugging (remove in production)
                                                error_reporting(E_ALL);
                                                ini_set('display_errors', 1);

                                                // Database connection
                                                require 'db_connection.php';

                                                // Set charset to prevent encoding issues
                                                $conn->set_charset("utf8");

                                                // Set timezone
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
                                                if ($action === 'export_table') {
                                                    // Headers will be set in the export function
                                                } else {
                                                    header('Content-Type: application/json; charset=utf-8');
                                                }

                                                try {
                                                    switch ($action) {
                                                        case 'get_table_structure':
                                                            getTableStructure();
                                                            break;
                                                        case 'get_table_data':
                                                            getTableData();
                                                            break;
                                                        case 'create_record':
                                                            createRecord();
                                                            break;
                                                        case 'update_record':
                                                            updateRecord();
                                                            break;
                                                        case 'delete_records':
                                                            deleteRecords();
                                                            break;
                                                        case 'export_table':
                                                            exportTable();
                                                            break;
                                                        default:
                                                            http_response_code(400);
                                                            echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
                                                    }
                                                } catch (Exception $e) {
                                                    error_log("CRUD API Error: " . $e->getMessage());
                                                    http_response_code(500);
                                                    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
                                                } finally {
                                                    if ($conn && !$conn->connect_error) {
                                                        $conn->close();
                                                    }
                                                }

                                                function getTableStructure() {
                                                    global $conn;

                                                    $table = $_GET['table'] ?? '';

                                                    // Debug logging
                                                    error_log("Getting table structure for: " . $table);

                                                    if (empty($table)) {
                                                        echo json_encode(['success' => false, 'message' => 'Table name required']);
                                                        return;
                                                    }

                                                    // Validate table name to prevent SQL injection
                                                    if (!isValidTableName($table)) {
                                                        echo json_encode(['success' => false, 'message' => 'Invalid table name: ' . $table]);
                                                        return;
                                                    }

                                                    try {
                                                        $query = "DESCRIBE `$table`";
                                                        $result = $conn->query($query);

                                                        if (!$result) {
                                                            error_log("Table structure query failed: " . $conn->error);
                                                            echo json_encode(['success' => false, 'message' => 'Table not found or query failed: ' . $conn->error]);
                                                            return;
                                                        }

                                                        $columns = [];
                                                        while ($row = $result->fetch_assoc()) {
                                                            $columns[] = $row;
                                                        }

                                                        error_log("Found " . count($columns) . " columns for table " . $table);
                                                        echo json_encode(['success' => true, 'columns' => $columns]);
                                                    } catch (Exception $e) {
                                                        error_log("Exception in getTableStructure: " . $e->getMessage());
                                                        echo json_encode(['success' => false, 'message' => 'Error getting table structure: ' . $e->getMessage()]);
                                                    }
                                                }

                                                function getTableData() {
                                                    global $conn;

                                                    $table = $_GET['table'] ?? '';
                                                    $page = max(1, (int)($_GET['page'] ?? 1));
                                                    $limit = 15;
                                                    $offset = ($page - 1) * $limit;

                                                    error_log("Getting table data for: $table, page: $page");

                                                    if (empty($table) || !isValidTableName($table)) {
                                                        echo json_encode(['success' => false, 'message' => 'Invalid table name']);
                                                        return;
                                                    }

                                                    try {
                                                        // Build WHERE clause for search
                                                        $whereConditions = [];
                                                        $params = [];
                                                        $types = '';

                                                        // Search functionality
                                                        if (!empty($_GET['search'])) {
                                                            $searchTerm = '%' . $_GET['search'] . '%';

                                                            // Get searchable columns
                                                            $searchColumns = getSearchableColumns($table);

                                                            if (!empty($searchColumns)) {
                                                                $searchConditions = [];
                                                                foreach ($searchColumns as $column) {
                                                                    $searchConditions[] = "`$column` LIKE ?";
                                                                    $params[] = $searchTerm;
                                                                    $types .= 's';
                                                                }
                                                                $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
                                                            }
                                                        }

                                                        // Column-specific search
                                                        if (!empty($_GET['column']) && !empty($_GET['search'])) {
                                                            $column = $_GET['column'];
                                                            // Validate column name
                                                            if (isValidColumnName($table, $column)) {
                                                                $whereConditions = ["`$column` LIKE ?"];
                                                                $params = ['%' . $_GET['search'] . '%'];
                                                                $types = 's';
                                                            }
                                                        }

                                                        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

                                                        // Get total count
                                                        $countQuery = "SELECT COUNT(*) as total FROM `$table` $whereClause";
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

                                                        // Get primary key for ordering
                                                        $primaryKey = getPrimaryKey($table);
                                                        $orderBy = $primaryKey ? "ORDER BY `$primaryKey` DESC" : "ORDER BY 1 DESC";

                                                        // Get data
                                                        $query = "SELECT * FROM `$table` $whereClause $orderBy LIMIT ? OFFSET ?";
                                                        $stmt = $conn->prepare($query);
                                                        if (!$stmt) {
                                                            throw new Exception("Prepare failed for data query: " . $conn->error);
                                                        }

                                                        $allParams = $params;
                                                        $allParams[] = $limit;
                                                        $allParams[] = $offset;
                                                        $allTypes = $types . 'ii';

                                                        $stmt->bind_param($allTypes, ...$allParams);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();

                                                        $data = [];
                                                        $columns = [];

                                                        if ($result->num_rows > 0) {
                                                            // Get column names
                                                            $fields = $result->fetch_fields();
                                                            foreach ($fields as $field) {
                                                                $columns[] = $field->name;
                                                            }

                                                            // Get data rows
                                                            while ($row = $result->fetch_assoc()) {
                                                                $data[] = $row;
                                                            }
                                                        }

                                                        $stmt->close();

                                                        // Calculate pagination
                                                        $totalPages = ceil($totalRecords / $limit);
                                                        $showingStart = $totalRecords > 0 ? $offset + 1 : 0;
                                                        $showingEnd = min($offset + $limit, $totalRecords);

                                                        error_log("Retrieved " . count($data) . " records for table " . $table);

                                                        echo json_encode([
                                                            'success' => true,
                                                            'data' => $data,
                                                            'columns' => $columns,
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
                                                        error_log("Exception in getTableData: " . $e->getMessage());
                                                        echo json_encode(['success' => false, 'message' => 'Error getting table data: ' . $e->getMessage()]);
                                                    }
                                                }

                                                function createRecord() {
                                                    global $conn;

                                                    $input = json_decode(file_get_contents('php://input'), true);
                                                    $table = $input['table'] ?? '';
                                                    $data = $input['data'] ?? [];

                                                    error_log("Creating record in table: $table with data: " . json_encode($data));

                                                    if (empty($table) || !isValidTableName($table) || empty($data)) {
                                                        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                                                        return;
                                                    }

                                                    try {
                                                        // Filter out empty values and validate columns
                                                        $filteredData = [];
                                                        foreach ($data as $column => $value) {
                                                            if (!isValidColumnName($table, $column)) {
                                                                echo json_encode(['success' => false, 'message' => "Invalid column: $column"]);
                                                                return;
                                                            }

                                                            // Handle empty values - convert empty strings to NULL for nullable fields
                                                            if ($value === '' || $value === null) {
                                                                $columnInfo = getColumnInfo($table, $column);
                                                                if ($columnInfo && $columnInfo['Null'] === 'YES') {
                                                                    $filteredData[$column] = null;
                                                                } elseif ($value !== '') {
                                                                    $filteredData[$column] = $value;
                                                                }
                                                                // Skip empty required fields - they'll cause a database error
                                                            } else {
                                                                $filteredData[$column] = $value;
                                                            }
                                                        }

                                                        if (empty($filteredData)) {
                                                            echo json_encode(['success' => false, 'message' => 'No valid data to insert']);
                                                            return;
                                                        }

                                                        // Prepare the INSERT query
                                                        $columns = array_keys($filteredData);
                                                        $values = array_values($filteredData);

                                                        $columnsList = '`' . implode('`, `', $columns) . '`';
                                                        $placeholders = str_repeat('?,', count($values) - 1) . '?';

                                                        $query = "INSERT INTO `$table` ($columnsList) VALUES ($placeholders)";
                                                        $stmt = $conn->prepare($query);

                                                        if (!$stmt) {
                                                            throw new Exception('Failed to prepare statement: ' . $conn->error);
                                                        }

                                                        // Build parameter types
                                                        $types = '';
                                                        foreach ($values as $value) {
                                                            if ($value === null) {
                                                                $types .= 's'; // NULL values as strings
                                                            } elseif (is_int($value)) {
                                                                $types .= 'i';
                                                            } elseif (is_float($value)) {
                                                                $types .= 'd';
                                                            } else {
                                                                $types .= 's';
                                                            }
                                                        }

                                                        $stmt->bind_param($types, ...$values);

                                                        if ($stmt->execute()) {
                                                            $insertId = $conn->insert_id;
                                                            error_log("Record created successfully with ID: $insertId");
                                                            echo json_encode(['success' => true, 'message' => 'Record created successfully', 'id' => $insertId]);
                                                        } else {
                                                            throw new Exception('Failed to create record: ' . $stmt->error);
                                                        }

                                                        $stmt->close();
                                                    } catch (Exception $e) {
                                                        error_log("Exception in createRecord: " . $e->getMessage());
                                                        echo json_encode(['success' => false, 'message' => 'Error creating record: ' . $e->getMessage()]);
                                                    }
                                                }

                                                function updateRecord() {
                                                    global $conn;

                                                    $input = json_decode(file_get_contents('php://input'), true);
                                                    $table = $input['table'] ?? '';
                                                    $data = $input['data'] ?? [];

                                                    error_log("Updating record in table: $table with data: " . json_encode($data));

                                                    if (empty($table) || !isValidTableName($table) || empty($data)) {
                                                        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                                                        return;
                                                    }

                                                    try {
                                                        // Find the primary key
                                                        $primaryKey = getPrimaryKey($table);
                                                        if (!$primaryKey || !isset($data[$primaryKey])) {
                                                            echo json_encode(['success' => false, 'message' => 'Primary key not found']);
                                                            return;
                                                        }

                                                        $primaryKeyValue = $data[$primaryKey];
                                                        unset($data[$primaryKey]); // Remove primary key from update data

                                                        if (empty($data)) {
                                                            echo json_encode(['success' => false, 'message' => 'No data to update']);
                                                            return;
                                                        }

                                                        // Filter and validate data
                                                        $filteredData = [];
                                                        foreach ($data as $column => $value) {
                                                            if (!isValidColumnName($table, $column)) {
                                                                echo json_encode(['success' => false, 'message' => "Invalid column: $column"]);
                                                                return;
                                                            }

                                                            // Handle empty values
                                                            if ($value === '') {
                                                                $columnInfo = getColumnInfo($table, $column);
                                                                if ($columnInfo && $columnInfo['Null'] === 'YES') {
                                                                    $filteredData[$column] = null;
                                                                } else {
                                                                    $filteredData[$column] = $value; // Let database handle validation
                                                                }
                                                            } else {
                                                                $filteredData[$column] = $value;
                                                            }
                                                        }

                                                        // Prepare the UPDATE query
                                                        $columns = array_keys($filteredData);
                                                        $values = array_values($filteredData);

                                                        $setClause = implode(' = ?, ', array_map(function ($col) {
                                                            return "`$col`";
                                                        }, $columns)) . ' = ?';

                                                        $query = "UPDATE `$table` SET $setClause WHERE `$primaryKey` = ?";
                                                        $stmt = $conn->prepare($query);

                                                        if (!$stmt) {
                                                            throw new Exception('Failed to prepare statement: ' . $conn->error);
                                                        }

                                                        // Build parameter types
                                                        $types = '';
                                                        foreach ($values as $value) {
                                                            if ($value === null) {
                                                                $types .= 's';
                                                            } elseif (is_int($value)) {
                                                                $types .= 'i';
                                                            } elseif (is_float($value)) {
                                                                $types .= 'd';
                                                            } else {
                                                                $types .= 's';
                                                            }
                                                        }

                                                        // Add primary key type
                                                        $types .= is_int($primaryKeyValue) ? 'i' : 's';

                                                        $values[] = $primaryKeyValue;
                                                        $stmt->bind_param($types, ...$values);

                                                        if ($stmt->execute()) {
                                                            if ($stmt->affected_rows > 0) {
                                                                error_log("Record updated successfully");
                                                                echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
                                                            } else {
                                                                echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
                                                            }
                                                        } else {
                                                            throw new Exception('Failed to update record: ' . $stmt->error);
                                                        }

                                                        $stmt->close();
                                                    } catch (Exception $e) {
                                                        error_log("Exception in updateRecord: " . $e->getMessage());
                                                        echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $e->getMessage()]);
                                                    }
                                                }

                                                function deleteRecords() {
                                                    global $conn;

                                                    $input = json_decode(file_get_contents('php://input'), true);
                                                    $table = $input['table'] ?? '';
                                                    $ids = $input['ids'] ?? [];

                                                    error_log("Deleting records from table: $table, IDs: " . json_encode($ids));

                                                    if (empty($table) || !isValidTableName($table) || empty($ids)) {
                                                        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                                                        return;
                                                    }

                                                    try {
                                                        // Find the primary key
                                                        $primaryKey = getPrimaryKey($table);
                                                        if (!$primaryKey) {
                                                            echo json_encode(['success' => false, 'message' => 'Primary key not found for table']);
                                                            return;
                                                        }

                                                        // Sanitize IDs
                                                        $ids = array_filter($ids, function ($id) {
                                                            return !empty($id) && $id !== '';
                                                        });

                                                        if (empty($ids)) {
                                                            echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
                                                            return;
                                                        }

                                                        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                                                        $query = "DELETE FROM `$table` WHERE `$primaryKey` IN ($placeholders)";

                                                        $stmt = $conn->prepare($query);
                                                        if (!$stmt) {
                                                            throw new Exception('Failed to prepare statement: ' . $conn->error);
                                                        }

                                                        // Use string type for all IDs to handle mixed data types
                                                        $types = str_repeat('s', count($ids));
                                                        $stmt->bind_param($types, ...$ids);

                                                        if ($stmt->execute()) {
                                                            $deletedCount = $stmt->affected_rows;
                                                            error_log("Successfully deleted $deletedCount records");
                                                            echo json_encode(['success' => true, 'message' => "$deletedCount record(s) deleted successfully"]);
                                                        } else {
                                                            throw new Exception('Failed to delete records: ' . $stmt->error);
                                                        }

                                                        $stmt->close();
                                                    } catch (Exception $e) {
                                                        error_log("Exception in deleteRecords: " . $e->getMessage());
                                                        echo json_encode(['success' => false, 'message' => 'Error deleting records: ' . $e->getMessage()]);
                                                    }
                                                }

                                                function exportTable() {
                                                    global $conn;

                                                    $table = $_GET['table'] ?? '';

                                                    error_log("Exporting table: $table");

                                                    if (empty($table) || !isValidTableName($table)) {
                                                        http_response_code(400);
                                                        echo json_encode(['success' => false, 'error' => 'Invalid table name']);
                                                        return;
                                                    }

                                                    try {
                                                        // Build WHERE clause for search (same logic as getTableData)
                                                        $whereConditions = [];
                                                        $params = [];
                                                        $types = '';

                                                        if (!empty($_GET['search'])) {
                                                            $searchTerm = '%' . $_GET['search'] . '%';
                                                            $searchColumns = getSearchableColumns($table);

                                                            if (!empty($searchColumns)) {
                                                                $searchConditions = [];
                                                                foreach ($searchColumns as $column) {
                                                                    $searchConditions[] = "`$column` LIKE ?";
                                                                    $params[] = $searchTerm;
                                                                    $types .= 's';
                                                                }
                                                                $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
                                                            }
                                                        }

                                                        // Column-specific search
                                                        if (!empty($_GET['column']) && !empty($_GET['search'])) {
                                                            $column = $_GET['column'];
                                                            if (isValidColumnName($table, $column)) {
                                                                $whereConditions = ["`$column` LIKE ?"];
                                                                $params = ['%' . $_GET['search'] . '%'];
                                                                $types = 's';
                                                            }
                                                        }

                                                        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

                                                        // Get primary key for ordering
                                                        $primaryKey = getPrimaryKey($table);
                                                        $orderBy = $primaryKey ? "ORDER BY `$primaryKey` DESC" : "ORDER BY 1 DESC";

                                                        $query = "SELECT * FROM `$table` $whereClause $orderBy";

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
                                                        header('Content-Disposition: attachment; filename="' . $table . '_export_' . date('Y-m-d_H-i-s') . '.csv"');
                                                        header('Cache-Control: no-cache, must-revalidate');
                                                        header('Expires: 0');

                                                        // Output CSV with BOM for Excel compatibility
                                                        $output = fopen('php://output', 'w');
                                                        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

                                                        // Get column headers
                                                        if ($result->num_rows > 0) {
                                                            $fields = $result->fetch_fields();
                                                            $headers = [];
                                                            foreach ($fields as $field) {
                                                                $headers[] = $field->name;
                                                            }
                                                            fputcsv($output, $headers);

                                                            // Output data rows
                                                            while ($row = $result->fetch_assoc()) {
                                                                // Handle NULL values and special characters
                                                                $csvRow = [];
                                                                foreach ($row as $value) {
                                                                    if ($value === null) {
                                                                        $csvRow[] = '';
                                                                    } else {
                                                                        $csvRow[] = $value;
                                                                    }
                                                                }
                                                                fputcsv($output, $csvRow);
                                                            }
                                                        } else {
                                                            // Empty table - just output headers
                                                            $columnsQuery = "DESCRIBE `$table`";
                                                            $columnsResult = $conn->query($columnsQuery);
                                                            $headers = [];
                                                            while ($col = $columnsResult->fetch_assoc()) {
                                                                $headers[] = $col['Field'];
                                                            }
                                                            fputcsv($output, $headers);
                                                        }

                                                        fclose($output);

                                                        if (isset($stmt)) {
                                                            $stmt->close();
                                                        }

                                                        error_log("Successfully exported table: $table");
                                                    } catch (Exception $e) {
                                                        error_log("Exception in exportTable: " . $e->getMessage());
                                                        http_response_code(500);
                                                        header('Content-Type: application/json');
                                                        echo json_encode(['success' => false, 'error' => 'Export failed: ' . $e->getMessage()]);
                                                    }
                                                }

                                                // Helper functions
                                                function isValidTableName($table) {
                                                    // List of allowed tables from your database
                                                    $allowedTables = [
                                                        'visitors',
                                                        'employees',
                                                        'receptionists',
                                                        'cso',
                                                        'daily_premises_entries',
                                                        'visitor_categories',
                                                        'premises_entry_log',
                                                        'notifications',
                                                        'password_resets',
                                                        'reception_notifications',
                                                        'daily_statistics',
                                                        'enhanced_entry_log'
                                                    ];

                                                    return in_array($table, $allowedTables);
                                                }

                                                function isValidColumnName($table, $column) {
                                                    global $conn;

                                                    try {
                                                        $query = "DESCRIBE `$table`";
                                                        $result = $conn->query($query);

                                                        if (!$result) {
                                                            error_log("Failed to describe table $table: " . $conn->error);
                                                            return false;
                                                        }

                                                        $validColumns = [];
                                                        while ($row = $result->fetch_assoc()) {
                                                            $validColumns[] = $row['Field'];
                                                        }

                                                        return in_array($column, $validColumns);
                                                    } catch (Exception $e) {
                                                        error_log("Exception in isValidColumnName: " . $e->getMessage());
                                                        return false;
                                                    }
                                                }

                                                function getPrimaryKey($table) {
                                                    global $conn;

                                                    try {
                                                        $query = "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'";
                                                        $result = $conn->query($query);

                                                        if ($result && $result->num_rows > 0) {
                                                            $row = $result->fetch_assoc();
                                                            return $row['Column_name'];
                                                        }

                                                        return null;
                                                    } catch (Exception $e) {
                                                        error_log("Exception in getPrimaryKey: " . $e->getMessage());
                                                        return null;
                                                    }
                                                }

                                                function getColumnInfo($table, $column) {
                                                    global $conn;

                                                    try {
                                                        $query = "DESCRIBE `$table` `$column`";
                                                        $result = $conn->query($query);

                                                        if ($result && $result->num_rows > 0) {
                                                            return $result->fetch_assoc();
                                                        }

                                                        return null;
                                                    } catch (Exception $e) {
                                                        error_log("Exception in getColumnInfo: " . $e->getMessage());
                                                        return null;
                                                    }
                                                }

                                                function getSearchableColumns($table) {
                                                    global $conn;

                                                    try {
                                                        $query = "DESCRIBE `$table`";
                                                        $result = $conn->query($query);

                                                        if (!$result) {
                                                            error_log("Failed to get searchable columns for $table: " . $conn->error);
                                                            return [];
                                                        }

                                                        $searchableColumns = [];
                                                        while ($col = $result->fetch_assoc()) {
                                                            $type = strtolower($col['Type']);
                                                            // Only search in text-based columns
                                                            if (
                                                                strpos($type, 'varchar') !== false ||
                                                                strpos($type, 'text') !== false ||
                                                                strpos($type, 'char') !== false ||
                                                                strpos($type, 'enum') !== false
                                                            ) {
                                                                $searchableColumns[] = $col['Field'];
                                                            }
                                                        }

                                                        return $searchableColumns;
                                                    } catch (Exception $e) {
                                                        error_log("Exception in getSearchableColumns: " . $e->getMessage());
                                                        return [];
                                                    }
                                                }

                                                ?>