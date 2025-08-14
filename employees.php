<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - AATC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/admin-dashboard.css" rel="stylesheet">
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
                <a href="employees.php" class="nav-link active">
                    <i class="bi bi-person-badge"></i>
                    <span>Employees</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="receptionists.php" class="nav-link">
                    <i class="bi bi-person-workspace"></i>
                    <span>Receptionists</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="crud_control_panel.php" class="nav-link">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <span>CRUD</span>
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
                <h1>Employees</h1>
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

        <!-- Page Content -->
        <div class="dashboard-container">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary fw-bold mb-1">Employee Management</h2>
                    <p class="text-muted mb-0">Manage all employee records and host assignments</p>
                </div>
                <button class="btn btn-primary" onclick="showAddEmployeeModal()">
                    <i class="bi bi-person-plus"></i> Add Employee
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-number" id="totalEmployees">0</div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-person-check stat-icon"></i>
                    <div class="stat-number" id="activeHosts">0</div>
                    <div class="stat-label">Active Hosts</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-calendar-week stat-icon"></i>
                    <div class="stat-number" id="todaysHosts">0</div>
                    <div class="stat-label">Today's Hosts</div>
                </div>
                <div class="stat-card">
                    <i class="bi bi-person-lines-fill stat-icon"></i>
                    <div class="stat-number" id="totalGuests">0</div>
                    <div class="stat-label">Assigned Guests</div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="widget mb-4">
                <div class="widget-content">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute" style="left: 10px; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
                                <input type="text" class="form-control ps-5" id="searchInput" placeholder="Search employees...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="departmentFilter">
                                <option value="">All Departments</option>
                                <option value="Administration">Administration</option>
                                <option value="IT">IT</option>
                                <option value="Human Resources">Human Resources</option>
                                <option value="Finance">Finance</option>
                                <option value="Operations">Operations</option>
                                <option value="Security">Security</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="hostFilter">
                                <option value="">All Employees</option>
                                <option value="hosts_only">Hosts Only</option>
                                <option value="non_hosts">Non-Hosts</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" onclick="clearFilters()">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employees Table -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">All Employees</h3>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info" id="totalCount">0 employees</span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportEmployees()">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                <div class="widget-content">
                    <!-- Bulk Actions Bar -->
                    <div class="d-flex justify-content-between align-items-center mb-3" id="bulkActionsBar" style="display: none !important;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-muted" id="selectedCount">0 selected</span>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="bulkExport()">
                                    <i class="bi bi-download"></i> Export Selected
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-link text-muted" onclick="clearSelection()">
                            Clear Selection
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="employeesTable">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Assigned Guests</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employeesTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="spinner"></div>
                                        Loading employees...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4" id="paginationContainer" style="display: none;">
                        <div class="text-muted">
                            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> employees
                        </div>
                        <nav aria-label="Employee pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination">
                                <!-- Pagination will be generated here -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Profile Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="employeeModalBody">
                    <!-- Employee profile will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" onclick="editEmployee()">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeModalTitle">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="employeeForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="employeeName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="employeeName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="employeeEmail" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="employeeEmail" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="employeePhone" class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <select class="form-select" id="countryCode" name="country_code" style="max-width: 100px;">
                                        <option value="+234" selected>+234</option>
                                        <option value="+1">+1</option>
                                        <option value="+44">+44</option>
                                        <option value="+91">+91</option>
                                        <option value="+86">+86</option>
                                        <option value="+49">+49</option>
                                        <option value="+33">+33</option>
                                        <option value="+81">+81</option>
                                        <option value="+82">+82</option>
                                        <option value="+55">+55</option>
                                    </select>
                                    <input type="tel" class="form-control" id="employeePhone" name="phone" placeholder="8012345678">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="employeeDesignation" class="form-label">Designation *</label>
                                <input type="text" class="form-control" id="employeeDesignation" name="designation" required>
                            </div>
                            <div class="col-md-6">
                                <label for="employeeDepartment" class="form-label">Department</label>
                                <select class="form-select" id="employeeDepartment" name="department">
                                    <option value="">Select Department</option>
                                    <option value="Administration">Administration</option>
                                    <option value="IT">IT</option>
                                    <option value="Human Resources">Human Resources</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Security">Security</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="employeeOrganization" class="form-label">Organization</label>
                                <input type="text" class="form-control" id="employeeOrganization" name="organization" placeholder="AATC">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="canHost" name="can_host">
                                    <label class="form-check-label" for="canHost">
                                        Can host visitors
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveEmployee()" id="saveEmployeeBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check"></i> Save Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this employee?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        This will also remove them as a host for any assigned visitors.
                    </div>
                    <p class="text-danger fw-bold mb-0" id="deleteEmployeeName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Preview Tooltip -->
    <div id="profilePreview" class="position-absolute bg-white border rounded shadow-lg p-3 d-none" style="z-index: 1070; width: 300px;">
        <div class="d-flex align-items-center mb-2">
            <div class="admin-avatar me-2" style="width: 40px; height: 40px;" id="previewAvatar">A</div>
            <div>
                <div class="fw-bold" id="previewName">Employee Name</div>
                <small class="text-muted" id="previewDesignation">Designation</small>
            </div>
        </div>
        <div class="small">
            <div><i class="bi bi-envelope text-muted me-1"></i> <span id="previewEmail">email@domain.com</span></div>
            <div class="mt-1"><i class="bi bi-telephone text-muted me-1"></i> <span id="previewPhone">+234 xxx xxxx</span></div>
            <div class="mt-1"><i class="bi bi-building text-muted me-1"></i> <span id="previewDepartment">Department</span></div>
            <div class="mt-2">
                <span class="badge bg-primary" id="previewGuestCount">0 Assigned Guests</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/employees.js"></script>
</body>
</html>