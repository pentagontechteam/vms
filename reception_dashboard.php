<?php
session_start();

// DB Connection
$conn = new mysqli("localhost", "aatcabuj_admin", "Sgt.pro@501", "aatcabuj_visitors_version_2");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['receptionist_id'])) {
    header("Location: receptionist_login.php"); // Redirect to login page
    exit(); // Stop further execution
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

// Pagination settings
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $per_page;

// Fetch approved guests with pagination

$search_term = isset($_GET['search']) ? trim($_GET['search']) : "";

// Search functionality
$search_term = isset($_GET['search']) ? trim($_GET['search']) : "";
$search_mode = !empty($search_term);

if ($search_mode) {
    // Prepare search term for SQL
    $like_term = '%' . $conn->real_escape_string($search_term) . '%';
    
    // Search approved visitors
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date 
                           FROM visitors 
                           WHERE status = 'approved' 
                           AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                           LIMIT ?, ?");
    $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $start, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
    $approved_guests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Search authenticated visitors
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, check_in_time 
                           FROM visitors 
                           WHERE status = 'checked_in' 
                           AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)
                           LIMIT ?, ?");
    $stmt->bind_param("ssssii", $like_term, $like_term, $like_term, $like_term, $start, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
    $authenticated_guests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get total counts for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM visitors 
   WHERE status = 'approved' 
   AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR host_name LIKE ?)");
$stmt->bind_param("ssss", $like_term, $like_term, $like_term, $like_term);
$stmt->execute();
$result = $stmt->get_result();
$total_approved = $result->fetch_assoc()['count'];
$stmt->close();

    
    // Show search header
    echo '<div class="alert alert-info alert-dismissible fade show text-center m-0 rounded-0" role="alert" style="background-color: #FFCA00; color: #212529;">
            <strong>Search Result:</strong> Showing results for "<strong>'.htmlspecialchars($search_term).'</strong>"
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
} else {
    // Default view (no search)
    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date 
                           FROM visitors 
                           WHERE status = 'approved' 
                           LIMIT ?, ?");
    $stmt->bind_param("ii", $start, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
    $approved_guests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, name, phone, email, host_name, visit_date, check_in_time 
                           FROM visitors 
                           WHERE status = 'checked_in' 
                           LIMIT ?, ?");
    $stmt->bind_param("ii", $start, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
    $authenticated_guests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get total counts
    $total_approved = $conn->query("SELECT COUNT(*) AS count FROM visitors WHERE status = 'approved'")->fetch_assoc()['count'];
    $total_authenticated = $conn->query("SELECT COUNT(*) AS count FROM visitors WHERE status = 'checked_in'")->fetch_assoc()['count'];
}


// Handle check-out
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_out_id'])) {
    $check_out_id = $_POST['check_out_id'];
    $stmt = $conn->prepare("UPDATE visitors SET status = 'checked out', check_out_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $check_out_id);
    if ($stmt->execute()) {
        $success_message = "Guest checked out successfully!";
    } else {
        $error_message = "Error checking out guest.";
    }
    $stmt->close();
}

// Total count for pagination
$total_approved = $conn->query("SELECT COUNT(*) AS count FROM visitors WHERE status = 'approved'")->fetch_assoc()['count'];
$total_authenticated = $conn->query("SELECT COUNT(*) AS count FROM visitors WHERE status = 'checked_in'")->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reception Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e6f7f4;
            font-family: 'Segoe UI', sans-serif;
        }
        .header-bar {
            background-color: #007570;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-bar img {
            height: 40px;
        }
        .header-bar .receptionist-name {
            font-size: 1.2rem;
            font-weight: 500;
        }
        .section-title {
            color: #007570;
            text-align: center;
            margin-top: 2rem;
        }
        .table th {
            background-color: #07AF8B;
            color: white;
        }
        .btn-custom {
            background-color: #007570;
            color: white;
        }
        .btn-custom:hover {
            background-color: #07AF8B;
        }
        .btn-danger-custom {
            background-color: #f72585;
            color: white;
        }
        .btn-danger-custom:hover {
            background-color: #f1d3e4;
        }
        .guest-table {
            margin-top: 2rem;
        }
        .btn-logout {
            background-color: #FF6347;
            color: white;
        }
        .btn-logout:hover {
            background-color: #ff4500;
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>

<div class="header-bar sticky-top shadow-sm">
    <img src="assets/logo-green-yellow.png" alt="Logo">
    <div class="receptionist-name">Welcome, <?= htmlspecialchars($receptionist_name); ?></div>
    <a href="reception_logout.php" class="btn btn-logout btn-sm">Logout</a>
</div>

<div class="container mt-4">

    <!-- Search Bar -->
    <form method="GET" class="row justify-content-center mb-4">
        <div class="col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search by name, phone, email, or host"
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-custom">Search</button>
            <?php if (!empty($_GET['search'])): ?>
                <a href="reception_dashboard.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Walk-In Visitor Button -->
    <div class="text-center mb-4">
        <a href="register_walkin.php" class="btn btn-custom btn-lg shadow">+ Register Walk-In Visitor</a>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="visitorTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="checked-in-tab" data-bs-toggle="tab" data-bs-target="#checked-in"
                    type="button" role="tab">Checked-In Visitors</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved"
                    type="button" role="tab">Approved Visitors</button>
        </li>
    </ul>

    <div class="tab-content" id="visitorTabContent">

        <!-- Checked-In Visitors Tab -->
        <div class="tab-pane fade show active" id="checked-in" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($authenticated_guests)): ?>
                        <p class="text-center text-muted">No checked-in visitors at the moment.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Host</th>
                                        <th>Date</th>
                                        <th>Check-In Time</th>
                                        <th>Actions</th> 
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($authenticated_guests as $guest): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($guest['name']) ?></td>
                                            <td><?= htmlspecialchars($guest['phone']) ?></td>
                                            <td><?= htmlspecialchars($guest['email']) ?></td>
                                            <td><?= htmlspecialchars($guest['host_name']) ?></td>
                                            <td><?= htmlspecialchars($guest['visit_date']) ?></td>
                                            <td><?= htmlspecialchars($guest['check_in_time']) ?></td>
                                            <td>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                        data-bs-target="#checkOutModal<?= $guest['id'] ?>">
                    Check Out
                </button>
                
                <!-- Camera Button (NEW) -->
                <button onclick="openCameraModal(<?= $guest['id'] ?>, '<?= htmlspecialchars($guest['name']) ?>', '<?= htmlspecialchars($guest['host_name']) ?>')"
                        class="btn btn-sm btn-outline-primary ms-2">
                    📷 Card
                </button>
            </td>
                                        </tr>

                                        <!-- Modal -->
                                        <div class="modal fade" id="checkOutModal<?= $guest['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Check Out Confirmation</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to check out <strong><?= htmlspecialchars($guest['name']) ?></strong>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST">
                                                            <input type="hidden" name="check_out_id" value="<?= $guest['id'] ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger-custom">Confirm Check Out</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Approved Visitors Tab -->
        <div class="tab-pane fade" id="approved" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($approved_guests)): ?>
                        <p class="text-center text-muted">No approved visitors available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Host</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_guests as $guest): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($guest['name']) ?></td>
                                            <td><?= htmlspecialchars($guest['phone']) ?></td>
                                            <td><?= htmlspecialchars($guest['email']) ?></td>
                                            <td><?= htmlspecialchars($guest['host_name']) ?></td>
                                            <td><?= htmlspecialchars($guest['visit_date']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                        data-bs-target="#checkOutModal<?= $guest['id'] ?>">Check Out</button>
                                            </td>
                                            <td>
            <button onclick="openCameraModal(<?= $guest['id'] ?>, '<?= htmlspecialchars($guest['name']) ?>', '<?= htmlspecialchars($guest['host_name']) ?>')"
                    class="btn btn-sm btn-outline-primary">
                📷 Card
            </button>
        </td>
                                        </tr>

                                        <!-- Modal -->
                                        <div class="modal fade" id="checkOutModal<?= $guest['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Check Out Confirmation</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to check out <strong><?= htmlspecialchars($guest['name']) ?></strong>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST">
                                                            <input type="hidden" name="check_out_id" value="<?= $guest['id'] ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger-custom">Confirm Check Out</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Camera & Card Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Visitor Card</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Camera Feed -->
                <div class="text-center mb-3">
                    <video id="video" width="100%" autoplay></video>
                </div>
                
                <!-- Capture Button -->
                <div class="text-center mb-4">
                    <button id="captureBtn" class="btn btn-primary">Capture Photo</button>
                </div>
                
                <!-- Visitor Card Preview (Initially Hidden) -->
                <div id="cardPreview" class="d-none">
                    <div class="card shadow" style="width: 3.5in; margin: 0 auto;">
                        <div class="card-header bg-primary text-white text-center">
                            <h5 class="mb-0">VISITOR PASS</h5>
                        </div>
                        <div class="card-body p-2">
                            <div class="d-flex">
                                <!-- Photo -->
                                <div class="me-3">
                                    <canvas id="cardPhoto" width="120" height="120" style="border: 1px solid #ddd;"></canvas>
                                </div>
                                <!-- Details -->
                                <div>
                                    <h6 id="cardName" class="mb-1"></h6>
                                    <p class="small mb-1">Host: <span id="cardHost"></span></p>
                                    <p class="small mb-1">Date: <?= date('Y-m-d') ?></p>
                                    <div class="badge bg-warning text-dark mt-2">VISITOR</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Print Button -->
                    <div class="text-center mt-3">
                        <button onclick="printCard()" class="btn btn-success">
                            🖨️ Print Card
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
  document.querySelector('input[name="search"]').addEventListener('input', function() {
    const term = this.value.trim().toLowerCase();
    // select both tables
    const tables = document.querySelectorAll('.tab-pane table tbody');
    tables.forEach(tbody => {
      tbody.querySelectorAll('tr').forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
    });
  });
</script>

<script>
// Store visitor data temporarily
let currentVisitor = {};

// Open modal with camera
function openCameraModal(id, name, host) {
    currentVisitor = { id, name, host };
    const modal = new bootstrap.Modal('#cameraModal');
    modal.show();
    
    // Reset UI
    document.getElementById('cardPreview').classList.add('d-none');
    document.getElementById('captureBtn').classList.remove('d-none');
    
    // Start camera
    startCamera();
}

// Start webcam
function startCamera() {
    const video = document.getElementById('video');
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => video.srcObject = stream)
            .catch(err => console.error("Camera error: ", err));
    }
}

// Capture photo
document.getElementById('captureBtn').addEventListener('click', function() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('cardPhoto');
    const ctx = canvas.getContext('2d');
    
    // Draw image to canvas
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Stop camera
    video.srcObject.getTracks().forEach(track => track.stop());
    
    // Show card preview
    document.getElementById('cardName').textContent = currentVisitor.name;
    document.getElementById('cardHost').textContent = currentVisitor.host;
    document.getElementById('cardPreview').classList.remove('d-none');
    document.getElementById('captureBtn').classList.add('d-none');
});

// Print card
function printCard() {
    const printContent = document.getElementById('cardPreview').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    
    // Refresh to restart camera
    window.location.reload();
}
</script>
<script>
let video = document.getElementById('video');
let canvas = document.getElementById('cardPhoto');
let context = canvas.getContext('2d');

function openCameraModal(id, name, host) {
    const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
    modal.show();
    document.getElementById('cardName').innerText = name;
    document.getElementById('cardHost').innerText = host;

    // Start camera
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            video.srcObject = stream;
        }).catch(err => {
            alert("Unable to access camera: " + err);
        });
}

document.getElementById('captureBtn').addEventListener('click', function () {
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    document.getElementById('cardPreview').classList.remove('d-none');
});

function printCard() {
    const printContents = document.getElementById('cardPreview').innerHTML;
    const originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    window.location.reload(); // Reload to reset the page
}
</script>

</body>

</html>
