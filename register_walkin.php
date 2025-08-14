<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
// $conn = new mysqli("localhost", "root", "", "visitor_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch list of hosts
$hosts = [];
$host_query = "SELECT id, name, email FROM employees";
$host_result = $conn->query($host_query);
while ($row = $host_result->fetch_assoc()) {
    $hosts[] = $row;
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Handle submission
$success_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guests'])) {
    $guests = $_POST['guests'];

    foreach ($guests as $guest) {
        $name = $conn->real_escape_string($guest['name']);
        $phone = $conn->real_escape_string($guest['phone']);
        $email = $conn->real_escape_string($guest['email']);
        $organization = $conn->real_escape_string($guest['organization']);
        $reason = $conn->real_escape_string($guest['reason']);
        $visit_date = date('Y-m-d'); // Always use current date
        $arrival_time = $conn->real_escape_string($guest['time_of_visit']);
        $floor_of_visit = $conn->real_escape_string($guest['floor_of_visit']);

        $host_id = isset($guest['host_id']) && is_numeric($guest['host_id']) ? intval($guest['host_id']) : null;
        $host_name = 'Walk-In';
        $host_email = null;

        if ($host_id) {
            $host_query = $conn->prepare("SELECT name, email FROM employees WHERE id = ?");
            $host_query->bind_param("i", $host_id);
            $host_query->execute();
            $host_query->bind_result($host_name, $host_email);
            $host_query->fetch();
            $host_query->close();
        }

        // Get current datetime for check-in
        $current_datetime = date("Y-m-d H:i:s");

        // Insert guest into DB
        $receptionist_id = $_SESSION['receptionist_id']; // Ensure receptionist is logged in

        $stmt = $conn->prepare("INSERT INTO visitors 
            (name, phone, email, organization, reason, host_name, visit_date, time_of_visit, floor_of_visit, status, requested_by_receptionist, receptionist_id, employee_id, check_in_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'checked_in', 1, ?, ?, ?)");

        $stmt->bind_param("sssssssssiis", 
            $name, $phone, $email, $organization, $reason, $host_name, $visit_date, $arrival_time, $floor_of_visit,
            $receptionist_id, $host_id, $current_datetime);

        $stmt->execute();
        $stmt->close();

        // Send Email
        if ($host_email && $host_name) {
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
                $mail->addAddress($host_email, $host_name);
                $mail->Subject = 'Visitor Arrival Notification';
                $mail->Body = "$name is in the premises to see you.";

                $mail->send();
            } catch (Exception $e) {
                error_log("Mail error: " . $mail->ErrorInfo);
            }
        }
    }

    $success_message = "Visitor(s) registered successfully!";
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-In Visitor Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --primary: #07AF8B;
            --primary-dark: #007570;
            --accent: #FFCA00;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        body {
            background-color: var(--light-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .navbar-brand {
            font-weight: 600;
            color: var(--primary-dark) !important;
        }
        
        .container {
            max-width: 900px;
            margin-top: 40px;
            margin-bottom: 80px;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            border-top: 4px solid var(--primary);
        }
        
        .form-title {
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .form-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--accent);
        }
        
        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(7, 175, 139, 0.15);
        }
        
        .form-control:disabled {
            background-color: #f8f9fa;
            opacity: 0.8;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .visitor-badge {
            background-color: var(--accent);
            color: #333;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }
        
        .success-modal .modal-header {
            background-color: var(--primary);
            color: white;
        }
        
        .success-icon {
            color: var(--primary);
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin-top: 20px;
                padding: 0 15px;
            }
            
            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center mb-5">
                    <h1 class="fw-bold" style="color: var(--primary-dark);">Walk-In Visitor Registration</h1>
                    <p class="text-muted">Please fill in the details of the visitors</p>
                </div>

        <div class="mb-4">
    <a href="vmc_dashboard.php" class="btn btn-outline">
        <i class="bi bi-house-door-fill me-2"></i>Go Back Home
    </a>
</div>

                <form method="POST" id="visitorForm">
                    <div id="guest-forms">
                        <div class="form-card">
                            <span class="visitor-badge"><i class="bi bi-person-plus me-2"></i>Visitor 1</span>
                            <h4 class="form-title">Visitor Information</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="guests[0][name]" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number (Optional)</label>
                                    <input type="tel" name="guests[0][phone]" class="form-control">
                                </div>
                               <!-- <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="guests[0][email]" class="form-control">
                                </div> -->
                                <div class="col-md-6">
                                    <label class="form-label">Organization</label>
                                    <input type="text" name="guests[0][organization]" class="form-control" required>
                                </div>
                                <!-- Visit date is automatically set to current date - no user input needed -->
                                <div class="col-md-6">
                                    <label class="form-label">Time of Visit</label>
                                    <input type="time" name="guests[0][time_of_visit]" class="form-control" required>
                                </div>
                                <div class="col-md-6">
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
                                 <div class="col-md-6">
                                    <label class="form-label">Host (Optional)</label>
                                    <select name="guests[0][host_id]" class="form-select" >
                                        <option value="">-- Select Host --</option>
                                        <?php foreach ($hosts as $host): ?>
                                            <option value="<?= $host['id'] ?>"><?= htmlspecialchars($host['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Purpose</label>
                                    <textarea name="guests[0][reason]" class="form-control" rows="2" required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-outline" onclick="addGuestForm()">
                            <i class="bi bi-plus-circle me-2"></i>Add Another Visitor
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send-check me-2"></i>Submit Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade success-modal" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title w-100 text-center">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="success-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h4 class="mb-3">Registration Complete!</h4>
                    <p class="text-muted">Walk-in visitor information has been successfully recorded.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let guestCount = 1;
        function addGuestForm() {
            const container = document.getElementById('guest-forms');
            const index = guestCount++;
            
            const formHTML = `
            <div class="form-card mt-4">
                <span class="visitor-badge"><i class="bi bi-person-plus me-2"></i>Visitor ${index + 1}</span>
                <h4 class="form-title">Visitor Information</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="guests[${index}][name]" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="guests[${index}][phone]" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Organization</label>
                        <input type="text" name="guests[${index}][organization]" class="form-control" required>
                    </div>
                    <!-- Visit date is automatically set to current date - no user input needed -->
                    <div class="col-md-6">
                        <label class="form-label">Time of Visit</label>
                        <input type="time" name="guests[${index}][time_of_visit]" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Floor of Visit</label>
                        <select name="guests[${index}][floor_of_visit]" class="form-control" required>
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
                    <div class="col-md-6">
                        <label class="form-label">Host (Optional)</label>
                        <select name="guests[${index}][host_id]" class="form-select">
                            <option value="">-- Select Host --</option>
                            <?php foreach ($hosts as $host): ?>
                                <option value="<?= $host['id'] ?>"><?= htmlspecialchars($host['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Purpose</label>
                        <textarea name="guests[${index}][reason]" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
            </div>`;
            container.insertAdjacentHTML('beforeend', formHTML);
            
            // Smooth scroll to the new form
            const newForm = container.lastElementChild;
            newForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        <?php if (!empty($success_message)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Clear form after successful submission if needed
            document.getElementById('visitorForm').reset();
        });
        <?php endif; ?>
    </script>
</body>
</html>