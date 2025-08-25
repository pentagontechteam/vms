<?php
session_start();
require 'db_connection.php';

// Load PHPMailer
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// DB Connection
require 'db_connection.php';

$employee_id = $_SESSION['employee_id'] ?? 0;
$message = '';
$import_message = '';

// Fetch employee name
$employee_name = "Employee";
if (isset($_SESSION['employee_id'])) {
    $stmt = $conn->prepare("SELECT name FROM employees WHERE id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->bind_result($employee_name);
    $stmt->fetch();
    $stmt->close();
}


// Process CSV import
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['visitor_csv'])) {
    $file = $_FILES['visitor_csv']['tmp_name'];
    if (is_uploaded_file($file)) {
        $csv_data = array_map('str_getcsv', file($file));
        $header = array_shift($csv_data); // Get and remove header row

        // Map expected headers to column indexes
        $column_indexes = array(
            'name' => array_search('name', array_map('strtolower', $header)),
            'phone' => array_search('phone', array_map('strtolower', $header)),
            'email' => array_search('email', array_map('strtolower', $header)),
            'organization' => array_search('organization', array_map('strtolower', $header)),
            'visit_date' => array_search('visit_date', array_map('strtolower', $header)),
            'time_of_visit' => array_search('time_of_visit', array_map('strtolower', $header)),
            'floor_of_visit' => array_search('floor', array_map('strtolower', $header)),
            'reason' => array_search('reason', array_map('strtolower', $header))
        );

        // Validate required columns
        $missing_columns = array();
        foreach ($column_indexes as $key => $index) {
            if ($index === false) {
                $missing_columns[] = $key;
            }
        }

        if (!empty($missing_columns)) {
            $import_message = "Error: Missing required columns in CSV: " . implode(', ', $missing_columns);
        } else {
            // Process CSV data
            $imported_count = 0;
            $error_count = 0;

            foreach ($csv_data as $row) {
                $name = $conn->real_escape_string($row[$column_indexes['name']]);
                $phone = $conn->real_escape_string($row[$column_indexes['phone']]);
                $email = $conn->real_escape_string($row[$column_indexes['email']]);
                $organization = $conn->real_escape_string($row[$column_indexes['organization']]);
                $visit_date = $conn->real_escape_string($row[$column_indexes['visit_date']]);
                $time_of_visit = $conn->real_escape_string($row[$column_indexes['time_of_visit']]);
                $floor_of_visit = $conn->real_escape_string($row[$column_indexes['floor_of_visit']]);
                $reason = $conn->real_escape_string($row[$column_indexes['reason']]);

                if (empty($name) || empty($email)) {
                    $error_count++;
                    continue;
                }

                $stmt = $conn->prepare("INSERT INTO visitors (name, phone, email, employee_id, host_id, host_name, organization, visit_date, time_of_visit, floor_of_visit, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("sssiissssss", $name, $phone, $email, $employee_id, $employee_id, $employee_name, $organization, $visit_date, $time_of_visit, $floor_of_visit, $reason);



                if ($stmt->execute()) {
                    $imported_count++;

                    // Send email notification
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'mail.aatcabuja.com.ng';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'support@aatcabuja.com.ng';
                        $mail->Password = 'Dw2bbgvhZmsp7QA';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port = 465;

                        $mail->setFrom('support@aatcabuja.com.ng', 'Abuja-AATC');
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = 'Visitor Registration Confirmation';
                        $mail->Body = "
                            <h3>Visitor Registration Details</h3>
                            <p><strong>Name:</strong> $name</p>
                            <p><strong>Host:</strong> $employee_name</p>
                            <p><strong>Visit Date:</strong> $visit_date at $time_of_visit</p>
                            <p><strong>Floor:</strong> $floor_of_visit</p>
                            <p><strong>Purpose:</strong> $reason</p>
                            <p>Your visit request is <strong>pending approval</strong>. You'll receive another email once approved.</p>
                        ";

                        $mail->send();
                    } catch (Exception $e) {
                        // Email error - continue processing other guests
                    }
                } else {
                    $error_count++;
                }
                $stmt->close();
            }

            $import_message = "Imported $imported_count visitors successfully";
            if ($error_count > 0) {
                $import_message .= " ($error_count failed)";
            }
        }
    } else {
        $import_message = "Error uploading file.";
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guests'])) {
    $guests = $_POST['guests'];
    $success_count = 0;

    foreach ($guests as $guest) {
        $name = $conn->real_escape_string($guest['name']);
        $host_name = $conn->real_escape_string($guest['host_name']);
        $phone = $conn->real_escape_string($guest['phone']);
        $email = $conn->real_escape_string($guest['email']);
        $organization = $conn->real_escape_string($guest['organization']);
        $visit_date = $conn->real_escape_string($guest['visit_date']);
        $time_of_visit = $conn->real_escape_string($guest['time_of_visit']);
        $floor_of_visit = $conn->real_escape_string($guest['floor_of_visit']);
        $reason = $conn->real_escape_string($guest['reason']);

        $stmt = $conn->prepare("INSERT INTO visitors (name, phone, email, employee_id, host_id, host_name, organization, visit_date, time_of_visit, floor_of_visit, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssiissssss", $name, $phone, $email, $employee_id, $employee_id, $host_name, $organization, $visit_date, $time_of_visit, $floor_of_visit, $reason);

        if ($stmt->execute()) {
            $success_count++;

            // Send email notification
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'mail.aatcabuja.com.ng';
                $mail->SMTPAuth = true;
                $mail->Username = 'support@aatcabuja.com.ng';
                $mail->Password = 'Dw2bbgvhZmsp7QA';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('support@aatcabuja.com.ng', 'Abuja-AATC');
                $mail->addAddress($email);
                //$mail->addAddress('reception@yourcompany.com', 'Reception');
                $mail->isHTML(true);
                $mail->Subject = 'Visitor Registration Confirmation';
                $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                    <style>
                         body { 
                            font-family: Arial, sans-serif; 
                            line-height: 1.6; 
                            color: #333; 
                            margin: 0;
                            padding: 0;
                            background-color: #f5f5f5;
                        }
                        .container { 
                            max-width: 600px; 
                            margin: 20px auto; 
                            background: white;
                            border-radius: 8px;
                            overflow: hidden;
                            box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        }
                        .header { 
                            background-color: #07AF8B; 
                            color: white; 
                            padding: 20px; 
                            text-align: center;
                        }
                        .header h2 {
                            margin: 10px 0;
                            font-size: 24px;
                        }
                        .header-logo {
                            width: auto;
                            height: auto;
                            max-width: 100%;
                            display: block;
                            margin: 0 auto;
                        }
                        .content { 
                            padding: 25px;
                        }
                        .footer { 
                            background-color: #007570;
                            color: white;
                            padding: 15px 20px; 
                            text-align: center; 
                            font-size: 12px; 
                        }
                        .qr-code { 
                            text-align: center; 
                            margin: 25px 0;
                            padding: 15px;
                            background-color: #f9f9f9;
                            border-radius: 5px;
                            border-left: 4px solid #FFCA00;
                        }
                        .details-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                        }
                        .details-table td {
                            padding: 10px;
                            border-bottom: 1px solid #eee;
                        }
                        .details-table td:first-child {
                            font-weight: bold;
                            color: #007570;
                            width: 30%;
                        }
                        .highlight {
                            background-color: rgba(255, 202, 0, 0.1);
                            padding: 10px;
                            border-radius: 5px;
                            margin: 15px 0;
                            border-left: 3px solid #FFCA00;
                        }
                        .button {
                            display: inline-block;
                            background-color: #FFCA00;
                            color: #333;
                            padding: 10px 20px;
                            text-decoration: none;
                            border-radius: 5px;
                            font-weight: bold;
                            margin: 15px 0;
                        }
                    </style>
                </head>
                <body>
                <div class='container'>
                <div class='header'>
                            <h2>Abuja-AATC Visitor Registration Details</h2>
                        </div>
                        <div class='content'>
                <table class='details-table'>
                    <tr><td><p><strong>Name:</strong> $name</p></td></tr>
                    <tr><td><p><strong>Host:</strong> $host_name</p></td></tr>
                    <tr><td><p><strong>Visit Date:</strong> $visit_date at $time_of_visit</p></td></tr>
                    <tr><td><p><strong>Floor:</strong> $floor_of_visit</p></td></tr>
                    <tr><td><p><strong>Purpose:</strong> $reason</p></td></tr>
                    <tr><td><p>Your visit request is <strong>pending confirmation</strong>. You'll receive another email once confirmed.</p></td></tr>
                    </table>
                    </div>
                    <div class='footer'>
                            <p>This is an automated message. Please do not reply.</p>
                        </div>
                    </div>
                    </body>
                </html>
                ";

                $mail->send();
            } catch (Exception $e) {
                // Email error - continue processing other guests
            }
        }
        $stmt->close();
    }

    if ($success_count > 0) {
        $message = "$success_count visitor(s) registered successfully!";
    } else {
        $message = "Error registering visitors.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --primary: #07AF8B;
            --accent: #FFCA00;
            --deep: #007570;
            --bg: #f4f6f8;
            --text-dark: #1f2d3d;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--bg);
            color: var(--text-dark);
        }

        .header-bar {
            background-color: var(--deep);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-bar img {
            height: 40px;
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 10px rgba(0, 0, 0, 0.05);
        }

        .guest-form {
            background-color: white;
            border-left: 5px solid var(--accent);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .btn-custom {
            background-color: var(--deep);
            color: white;
        }

        .btn-custom:hover {
            background-color: var(--primary);
        }

        .success-message {
            border-left: 5px solid #28a745;
            padding: 1rem;
            margin-bottom: 1.5rem;
            background-color: #d4edda;
        }

        .error-message {
            border-left: 5px solid #dc3545;
            padding: 1rem;
            margin-bottom: 1.5rem;
            background-color: #f8d7da;
        }

        .profile-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #14532d;
            font-weight: bold;
            font-size: 16px;
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }

        .floating-back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: var(--deep);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .floating-back-btn:hover {
            background: var(--primary);
            transform: translateX(-3px);
        }

        .import-sample {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .modal-body ul {
            padding-left: 20px;
        }

        .modal-body li {
            margin-bottom: 5px;
        }
    </style>
</head>

<body>

    <div class="header-bar">
        <img src="assets/logo-green-yellow.png" alt="Logo">
        <a href="staff_dashboard.php" class="btn btn-outline-light me-2" style="margin-right: 10px;">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>

        <div class="employee-name">Welcome, <?= htmlspecialchars($employee_name); ?></div>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

    <div class="container">
        <h2 class="mb-4">Register Visitors</h2>

        <?php if ($message): ?>
            <div class="success-message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($import_message): ?>
            <div class="<?= strpos($import_message, 'Error') !== false ? 'error-message' : 'success-message' ?>">
                <?= htmlspecialchars($import_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div id="guest-forms">
                <div class="guest-form">
                    <h5>Visitor 1</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="guests[0][name]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Host Name</label>
                            <input type="text" name="guests[0][host_name]" class="form-control bg-light" value="<?= htmlspecialchars($employee_name); ?>" readonly>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="guests[0][phone]" class="form-control" placeholder="+234XXXXXXXXXX" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="guests[0][email]" class="form-control" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Organization</label>
                            <input type="text" name="guests[0][organization]" class="form-control" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Visit Date</label>
                            <input type="date" name="guests[0][visit_date]" class="form-control" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Time of Visit</label>
                            <input type="time" name="guests[0][time_of_visit]" class="form-control" required>
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="form-label">Floor of Visit</label>
                            <select name="guests[0][floor_of_visit]" class="form-control" required>
                                <option value="">Select Floor</option>
                                <option value="Ground Floor">Ground Floor</option>
                                <option value="Mezzanine">Mezzanine</option>
                                <option value="Floor 1">Floor 1</option>
                                <option value="Floor 2 - Right Wing">Floor 2 - Right Wing</option>
                                <option value="Floor 2 - Left Wing">Floor 2 - Left Wing</option>
                                <option value="Floor 3 - Right Wing">Floor 3 - Right Wing</option>
                                <option value="Floor 3 - Left Wing">Floor 3 - Left Wing</option>
                                <option value="Floor 4 - Right Wing">Floor 4 - Right Wing</option>
                                <option value="Floor 4 - Left Wing">Floor 4 - Left Wing</option>
                                <option value="Floor 5 - Right Wing">Floor 5 - Right Wing</option>
                                <option value="Floor 5 - Left Wing">Floor 5 - Left Wing</option>
                                <option value="Floor 6 - Right Wing">Floor 6 - Right Wing</option>
                                <option value="Floor 6 - Left Wing">Floor 6 - Left Wing</option>
                                <option value="Floor 7 - Right Wing">Floor 7 - Right Wing</option>
                                <option value="Floor 7 - Left Wing">Floor 7 - Left Wing</option>
                                <option value="Floor 8 - Right Wing">Floor 8 - Right Wing</option>
                                <option value="Floor 8 - Left Wing">Floor 8 - Left Wing</option>
                                <option value="Floor 9 - Right Wing">Floor 9 - Right Wing</option>
                                <option value="Floor 9 - Left Wing">Floor 9 - Left Wing</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label class="form-label">Purpose</label>
                            <textarea name="guests[0][reason]" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-4">
                <button type="button" class="btn btn-outline-primary me-2" onclick="addGuestForm()">
                    <i class="bi bi-plus-circle"></i> Add Another Visitor
                </button>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Import Visitors
                </button>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-custom btn-lg px-5">
                    <i class="bi bi-send-check"></i> Submit Visitor Requests
                </button>
            </div>
        </form>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="importModalLabel"><i class="bi bi-file-earmark-spreadsheet"></i> Import Visitors from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="importForm">
                        <div class="mb-4">
                            <h6><i class="bi bi-info-circle"></i> Instructions:</h6>
                            <ul>
                                <li>Prepare a CSV file with the following headers: name, phone, email, organization, visit_date, time_of_visit, floor, reason</li>
                                <li>Date format should be YYYY-MM-DD (e.g., 2025-05-20)</li>
                                <li>Time format should be HH:MM (e.g., 14:30)</li>
                                <li>Floor values should match our options (e.g., "Ground Floor", "Floor 1", etc.)</li>
                            </ul>
                        </div>

                        <div class="mb-3">
                            <label for="visitor_csv" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="visitor_csv" name="visitor_csv" accept=".csv" required>
                        </div>

                        <div class="alert alert-info">
                            <h6 class="mb-2"><i class="bi bi-file-earmark-text"></i> Sample CSV Format:</h6>
                            <pre class="import-sample">name,phone,email,organization,visit_date,time_of_visit,floor,reason
John Doe,+2341234567890,john@example.com,ABC Corp,2025-05-20,14:30,Floor 3,Business Meeting
Jane Smith,+2349876543210,jane@example.com,XYZ Ltd,2025-05-21,10:00,Floor 5,Interview</pre>
                            <a href="#" class="btn btn-sm btn-outline-dark mt-2" download="sample_visitors.csv"><i class="bi bi-download"></i> Download Sample</a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('importForm').submit()">
                        <i class="bi bi-check2"></i> Import Visitors
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let guestCount = 1;

        function addGuestForm() {
            const container = document.getElementById('guest-forms');
            const index = guestCount;
            guestCount++;

            const form = document.createElement('div');
            form.classList.add('guest-form');
            form.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Visitor ${index + 1}</h5>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.parentElement.remove()">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="guests[${index}][name]" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Host Name</label>
                    <input type="text" name="guests[${index}][host_name]" class="form-control bg-light" value="<?= htmlspecialchars($employee_name); ?>" readonly>
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="guests[${index}][phone]" class="form-control"placeholder="+2341234567890" required
                    required pattern="^\+?[1-9]\d{7,14}$" title="Enter a valid international phone number starting with + and digits only.">
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="guests[${index}][email]" class="form-control" required>
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Organization</label>
                    <input type="text" name="guests[${index}][organization]" class="form-control" required>
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Visit Date</label>
                    <input type="date" name="guests[${index}][visit_date]" class="form-control" required>
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Time of Visit</label>
                    <input type="time" name="guests[${index}][time_of_visit]" class="form-control" required>
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Floor of Visit</label>
                    <select name="guests[${index}][floor_of_visit]" class="form-control" required>
                        <option value="">Select Floor</option>
                        <option value="Ground Floor">Ground Floor</option>
                        <option value="Mezzanine">Mezzanine</option>
                        <option value="Floor 1">Floor 1</option>
                        <option value="Floor 2">Floor 2 - Right Wing</option>
                <option value="Floor 2">Floor 2 - Left Wing</option>
                <option value="Floor 3">Floor 3 - Right Wing</option>
                <option value="Floor 3">Floor 3 - Left Wing</option>
                <option value="Floor 4">Floor 4 - Right Wing</option>
                <option value="Floor 4">Floor 4 - Left Wing</option>
                <option value="Floor 5">Floor 5 - Right Wing</option>
                <option value="Floor 5">Floor 5 - Left Wing</option>
                <option value="Floor 6">Floor 6 - Right Wing</option>
                <option value="Floor 6">Floor 6 - Left Wing</option>
                <option value="Floor 7">Floor 7 - Right Wing</option>
                <option value="Floor 7">Floor 7 - Left Wing</option>
                <option value="Floor 8">Floor 8 - Right Wing</option>
                <option value="Floor 8">Floor 8 - Left Wing</option>
                <option value="Floor 9">Floor 9 - Right Wing</option>
                <option value="Floor 9">Floor 9 - Left Wing</option>
                    </select>
                </div>
                <div class="col-12 mt-3">
                    <label class="form-label">Purpose</label>
                    <textarea name="guests[${index}][reason]" class="form-control" rows="2" required></textarea>
                </div>
            </div>
        `;
            container.appendChild(form);
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            const phoneRegex = /^\+?[1-9]\d{7,14}$/;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            document.querySelectorAll('[name^="guests"]').forEach(input => {
                if (input.name.includes('[phone]')) {
                    if (!phoneRegex.test(input.value)) {
                        alert(`Invalid phone number: ${input.value}`);
                        isValid = false;
                    }
                }

                if (input.name.includes('[email]')) {
                    if (!emailRegex.test(input.value)) {
                        alert(`Invalid email address: ${input.value}`);
                        isValid = false;
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0]; // Get today's date in YYYY-MM-DD format
            const dateInputs = document.querySelectorAll('input[type="date"]');

            // Set the min attribute to today's date for all date input fields
            dateInputs.forEach(input => {
                input.setAttribute('min', today);
            });

            // Setup the sample CSV download
            setupSampleCSVDownload();
        });

        function setupSampleCSVDownload() {
            const downloadLink = document.querySelector('a[download]');
            if (downloadLink) {
                // Remove the href attribute to prevent default behavior
                downloadLink.removeAttribute('href');

                // Add click event listener
                downloadLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    downloadSampleCSV();
                });
            }
        }

        function downloadSampleCSV() {
            // Create sample CSV content
            const csvContent = `name,phone,email,organization,visit_date,time_of_visit,floor,reason
John Doe,+2341234567890,john@example.com,ABC Corp,${getTomorrowDate()},14:30,Floor 3,Business Meeting
Jane Smith,+2349876543210,jane@example.com,XYZ Ltd,${getDayAfterTomorrowDate()},10:00,Floor 5,Interview
Peter Johnson,+2348033445566,peter@company.com,Tech Solutions,${getTomorrowDate()},09:00,Floor 2,Technical Discussion
Sarah Williams,+2347011223344,sarah@business.com,Finance Inc,${getDayAfterTomorrowDate()},15:45,Floor 7,Contract Negotiation`;

            // Create a Blob from the CSV content
            const blob = new Blob([csvContent], {
                type: 'text/csv;charset=utf-8;'
            });

            // Create a temporary download link
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', 'sample_visitors.csv');
            link.style.visibility = 'hidden';

            // Add to DOM, click, and remove
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Clean up the URL object
            URL.revokeObjectURL(url);
        }

        function getTomorrowDate() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            return tomorrow.toISOString().split('T')[0];
        }

        function getDayAfterTomorrowDate() {
            const dayAfter = new Date();
            dayAfter.setDate(dayAfter.getDate() + 2);
            return dayAfter.toISOString().split('T')[0];
        }
    </script>
</body>

</html>