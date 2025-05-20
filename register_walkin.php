<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
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
        $visit_date = date("Y-m-d");
        $arrival_time = date("H:i:s");

        $host_id = intval($guest['host_id']);
        $host_query = $conn->prepare("SELECT name, email FROM employees WHERE id = ?");
        $host_query->bind_param("i", $host_id);
        $host_query->execute();
        $host_query->bind_result($host_name, $host_email);
        $host_query->fetch();
        $host_query->close();

        // Insert guest into DB
        $stmt = $conn->prepare("INSERT INTO visitors (name, phone, email, organization, reason, visit_date, arrival_time, host_id, host_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
        $stmt->bind_param("sssssssis", $name, $phone, $email, $organization, $reason, $visit_date, $arrival_time, $host_id, $host_name);
        $stmt->execute();
        $stmt->close();

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com'; // change this
            $mail->SMTPAuth = true;
            $mail->Username = 'your_email@example.com'; // change this
            $mail->Password = 'your_password'; // change this
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('your_email@example.com', 'Visitor Management System');
            $mail->addAddress($host_email, $host_name);
            $mail->Subject = 'Visitor Arrival Notification';
            $mail->Body = "$name is in the premises to see you.";

            $mail->send();
        } catch (Exception $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
        }
    }

    $success_message = "Visitor(s) registered and host notified!";
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Walk-in Visitor Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #e9fef1;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            margin-top: 30px;
            margin-bottom: 50px;
        }
        .form-section {
            background: #ffffff;
            border-left: 5px solid #07AF8B;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 128, 0, 0.1);
        }
        h2 {
            color: #07AF8B;
            text-align: center;
            margin-bottom: 30px;
        }
        label {
            font-weight: 500;
        }
        .btn-custom {
            background-color: #07AF8B;
            color: white;
        }
        .btn-custom:hover {
            background-color: #038f70;
        }
        .alert {
            font-size: 1rem;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Walk-in Visitor Registration</h2>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success text-center"><?= $success_message ?></div>
    <?php endif; ?>

    <form method="POST">
        <div id="guest-forms">
            <div class="form-section">
                <h5>Visitor 1</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Full Name</label>
                        <input type="text" name="guests[0][name]" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Phone</label>
                        <input type="tel" name="guests[0][phone]" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input type="email" name="guests[0][email]" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Organization</label>
                        <input type="text" name="guests[0][organization]" class="form-control" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label>Reason for Visit</label>
                        <textarea name="guests[0][reason]" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label>Select Host</label>
                        <select name="guests[0][host_id]" class="form-select" required>
                            <option value="">-- Select Host --</option>
                            <?php foreach ($hosts as $host): ?>
                                <option value="<?= $host['id'] ?>"><?= htmlspecialchars($host['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
     
        <div class="text-center mb-4">
            <button type="button" class="btn btn-outline-success" onclick="addGuestForm()">Add Another Visitor</button>
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-custom btn-lg">Submit</button>
        </div>
    </form>
</div>

<script>
    let guestCount = 1;
    function addGuestForm() {
        const container = document.getElementById('guest-forms');
        const index = guestCount++;
        const formHTML = `
        <div class="form-section">
            <h5>Visitor ${index + 1}</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Full Name</label>
                    <input type="text" name="guests[${index}][name]" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Phone</label>
                    <input type="tel" name="guests[${index}][phone]" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Email</label>
                    <input type="email" name="guests[${index}][email]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Organization</label>
                    <input type="text" name="guests[${index}][organization]" class="form-control" required>
                </div>
                <div class="col-md-12 mb-3">
                    <label>Reason for Visit</label>
                    <textarea name="guests[${index}][reason]" class="form-control" required></textarea>
                </div>
                <div class="col-md-12 mb-3">
                    <label>Select Host</label>
                    <select name="guests[${index}][host_id]" class="form-select" required>
                        <option value="">-- Select Host --</option>
                        <?php foreach ($hosts as $host): ?>
                            <option value="<?= $host['id'] ?>"><?= htmlspecialchars($host['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>`;
        container.insertAdjacentHTML('beforeend', formHTML);
    }
</script>
<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title w-100" id="successModalLabel">Success</h5>
      </div>
      <div class="modal-body">
        Visitor registered successfully!
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($success_message)): ?>
<script>
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    window.onload = () => {
        successModal.show();
    };
</script>
<?php endif; ?>


</body>
</html>
