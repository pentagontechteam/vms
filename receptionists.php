<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionists Management - AATC Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/admin-dashboard.css" rel="stylesheet">
    <link href="assets/receptionists-additional.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="bi bi-arrow-clockwise"></i> Refreshing...
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="assets/logo-green-yellow.png" alt="AATC Logo">
            <h4>Admin Dashboard</h4>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="visitors_management.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Visitors</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="employees.php" class="nav-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Employees</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="receptionists.php" class="nav-link active">
                    <i class="bi bi-person-workspace"></i>
                    <span>Receptionists</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="floors_offices.php" class="nav-link">
                    <i class="bi bi-building"></i>
                    <span>Offices & Floors</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-tags"></i>
                    <span>Visitor Types</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-camera"></i>
                    <span>Photo Gallery</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-geo-alt"></i>
                    <span>Map View</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="crud_control_panel.php" class="nav-link">
                    <i class="bi bi-database"></i>
                    <span>Database Control</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1>Receptionists Management</h1>
            </div>
            <div class="topbar-right">
                <div class="notifications">
                    <button class="btn btn-light position-relative">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount">
                            0
                        </span>
                    </button>
                </div>
                <div class="admin-profile">
                    <div class="admin-avatar">A</div>
                    <div class="admin-info">
                        <div class="admin-name">Admin</div>
                        <div class="admin-role">Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary fw-bold mb-1">Reception Staff Management</h2>
                    <p class="text-muted mb-0">Manage reception team and monitor their performance metrics</p>
                </div>
                <button class="btn btn-primary" onclick="showAddReceptionistModal()">
                    <i class="bi bi-person-plus"></i> Add Receptionist
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card receptionists">
                    <i class="bi bi-person-workspace stat-icon"></i>
                    <div class="stat-number" id="totalReceptionists">0</div>
                    <div class="stat-label">Total Receptionists</div>
                </div>
                <div class="stat-card receptionists">
                    <i class="bi bi-person-check stat-icon"></i>
                    <div class="stat-number" id="activeReceptionists">0</div>
                    <div class="stat-label">Active Staff</div>
                </div>
                <div class="stat-card receptionists">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-number" id="totalGuestsProcessed">0</div>
                    <div class="stat-label">Total Guests Processed</div>
                </div>
                <div class="stat-card receptionists">
                    <i class="bi bi-calendar-week stat-icon"></i>
                    <div class="stat-number" id="todaysProcessed">0</div>
                    <div class="stat-label">Processed Today</div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filter-section widget mb-4">
                <div class="widget-content">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="search-input-wrapper position-relative">
                                <i class="bi bi-search position-absolute" style="left: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 5;"></i>
                                <input type="text" class="form-control ps-5" id="searchInput" placeholder="Search receptionists by name or username...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select filter-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select filter-select" id="performanceFilter">
                                <option value="">All Performance</option>
                                <option value="high">High (20+ guests)</option>
                                <option value="medium">Medium (5-19 guests)</option>
                                <option value="low">Low (0-4 guests)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" onclick="clearFilters()">
                                <i class="bi bi-x-circle"></i> Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar (Hidden by default) -->
            <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-primary fw-semibold" id="selectedCount">0 selected</span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()" title="Delete Selected Receptionists">
                                <i class="bi bi-trash"></i> Delete Selected
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="bulkResetPassword()" title="Reset Passwords for Selected">
                                <i class="bi bi-key"></i> Reset Passwords
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-link text-muted" onclick="clearSelection()" title="Clear Selection">
                        <i class="bi bi-x"></i> Clear Selection
                    </button>
                </div>
            </div>

            <!-- Receptionists Table -->
            <div class="receptionists-table widget mb-4">
                <div class="widget-header">
                    <h3 class="widget-title">Reception Staff Directory</h3>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info" id="totalCount">0 receptionists</span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshData()" title="Refresh Data">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportReceptionists()" title="Export to CSV">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                <div class="widget-content p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="receptionistsTable">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAll" title="Select All">
                                    </th>
                                    <th>Receptionist</th>
                                    <th>Username</th>
                                    <th>Guests Processed</th>
                                    <th>Status</th>
                                    <th>Performance</th>
                                    <th width="140">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="receptionistsTableBody">
                                <!-- Loading skeleton -->
                                <tr class="loading-row">
                                    <td colspan="7" class="text-center py-5">
                                        <div class="spinner"></div>
                                        Loading receptionists data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Performance Analytics -->
            <div class="widget-grid">
                <!-- Performance Chart -->
                <div class="performance-chart-container widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Performance Rankings</h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleChartView()" title="Toggle Chart View">
                                <i class="bi bi-bar-chart"></i> Toggle View
                            </button>
                        </div>
                    </div>
                    <div class="widget-content">
                        <div class="row">
                            <div class="col-lg-8">
                                <div style="height: 300px; position: relative;">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="top-performers-section">
                                    <h6 class="text-primary mb-3 fw-bold">
                                        <i class="bi bi-trophy-fill me-2"></i>Top Performers
                                    </h6>
                                    <div id="topPerformers">
                                        <!-- Top performers list will be populated here -->
                                        <div class="text-center text-muted py-3">
                                            <i class="bi bi-hourglass-split"></i>
                                            <p class="mb-0 small">Loading performance data...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions mt-4">
                <button class="quick-action-btn" onclick="showAddReceptionistModal()">
                    <i class="bi bi-person-plus"></i>
                    Add New Staff
                </button>
                <button class="quick-action-btn" onclick="exportReceptionists()">
                    <i class="bi bi-download"></i>
                    Export Report
                </button>
                <button class="quick-action-btn" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh Data
                </button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Receptionist Modal -->
    <div class="modal fade receptionist-modal" id="receptionistModal" tabindex="-1" aria-labelledby="receptionistModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receptionistModalTitle">
                        <i class="bi bi-person-plus me-2"></i>Add Receptionist
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="receptionistForm" novalidate>
                        <div class="mb-3">
                            <label for="receptionistName" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="receptionistName" name="name" required placeholder="Enter full name" maxlength="100">
                            <div class="invalid-feedback">Please provide a valid name (1-100 characters).</div>
                        </div>
                        <div class="mb-3">
                            <label for="receptionistUsername" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="receptionistUsername" name="username" required placeholder="Enter username" maxlength="50" pattern="[a-zA-Z0-9_]+">
                            <div class="form-text">Username for login access (letters, numbers, underscore only)</div>
                            <div class="invalid-feedback">Please provide a valid username (letters, numbers, underscore only).</div>
                        </div>
                        <div class="mb-3" id="passwordField">
                            <label for="receptionistPassword" class="form-label">Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="receptionistPassword" name="password" required minlength="6" maxlength="255" placeholder="Enter password">
                                <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword()" title="Show/Hide Password">
                                    <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 6 characters long</div>
                            <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveReceptionist()" id="saveReceptionistBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check-circle"></i> Save Receptionist
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade receptionist-modal" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalTitle">
                        <i class="bi bi-key me-2"></i>Change Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Changing password for: <strong id="passwordTargetName">Receptionist</strong>
                    </div>
                    <form id="passwordForm" novalidate>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6" maxlength="255" placeholder="Enter new password">
                                <button class="btn btn-outline-secondary password-toggle" type="button" onclick="toggleNewPassword()" title="Show/Hide Password">
                                    <i class="bi bi-eye" id="newPasswordToggleIcon"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="6" maxlength="255" placeholder="Confirm new password">
                            <div class="invalid-feedback">Please confirm your password.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-warning" onclick="changePassword()" id="changePasswordBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-key"></i> Change Password
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalTitle">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="bi bi-trash text-danger" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 mb-2">Are you sure you want to delete this receptionist?</h6>
                        <p class="text-muted">This action cannot be undone and will remove all their processing records.</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will permanently delete the receptionist account.
                        </div>
                        <p class="text-danger fw-bold mb-0" id="deleteReceptionistName"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-trash"></i> Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/receptionists.js"></script>
    
    <!-- Performance monitoring script -->
    <script>
        // Log page load time
        window.addEventListener('load', function() {
            const navigationStart = performance.timing.navigationStart;
            const loadComplete = performance.timing.loadEventEnd;
            const loadTime = loadComplete - navigationStart;
            console.log(`Receptionists page loaded in ${loadTime}ms`);
        });
    </script>
</body>
</html>