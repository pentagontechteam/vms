<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floors & Offices Management - AATC Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/admin-dashboard.css" rel="stylesheet">
    <link href="assets/floors-offices-additional.css" rel="stylesheet">
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
                <a href="#" class="nav-link">
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
                <a href="floors_offices.php" class="nav-link active">
                    <i class="bi bi-building"></i>
                    <span>Floors & Offices</span>
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
                <h1>Floors & Offices</h1>
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
                    <h2 class="text-primary fw-bold mb-1">Building Management</h2>
                    <p class="text-muted mb-0">Manage floors, offices, and track visitor traffic by location</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="showAddFloorModal()">
                        <i class="bi bi-plus-circle"></i> Add Floor
                    </button>
                    <button class="btn btn-outline-primary" onclick="showAddOfficeModal()">
                        <i class="bi bi-door-open"></i> Add Office
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card floors">
                    <i class="bi bi-building stat-icon"></i>
                    <div class="stat-number" id="totalFloors">0</div>
                    <div class="stat-label">Total Floors</div>
                </div>
                <div class="stat-card floors">
                    <i class="bi bi-door-open stat-icon"></i>
                    <div class="stat-number" id="totalOffices">0</div>
                    <div class="stat-label">Total Offices</div>
                </div>
                <div class="stat-card floors">
                    <i class="bi bi-people stat-icon"></i>
                    <div class="stat-number" id="totalVisitors">0</div>
                    <div class="stat-label">Total Guests Received</div>
                </div>
                <div class="stat-card floors">
                    <i class="bi bi-calendar-week stat-icon"></i>
                    <div class="stat-number" id="todayVisitors">0</div>
                    <div class="stat-label">Today's Guests</div>
                </div>
            </div>

            <!-- Floor Map Upload Section -->
            <div class="widget mb-4">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="bi bi-map me-2"></i>Floor Map Management
                    </h3>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewAllMaps()">
                        <i class="bi bi-images"></i> View All Maps
                    </button>
                </div>
                <div class="widget-content">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="map-upload-area" id="mapUploadArea">
                                <div class="upload-placeholder">
                                    <i class="bi bi-cloud-upload display-1 text-muted"></i>
                                    <h5 class="mt-3">Upload Floor Map</h5>
                                    <p class="text-muted">Drag & drop your floor plan or click to browse</p>
                                    <p class="text-muted small">Supported: JPG, PNG, PDF (Max: 10MB)</p>
                                    <input type="file" class="d-none" id="mapFileInput" accept=".jpg,.jpeg,.png,.pdf" multiple>
                                    <button class="btn btn-outline-primary" onclick="document.getElementById('mapFileInput').click()">
                                        <i class="bi bi-folder2-open"></i> Browse Files
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="recent-uploads">
                                <h6 class="text-muted mb-3">Recently Uploaded Maps</h6>
                                <div id="recentMaps">
                                    <!-- Recent maps will be loaded here -->
                                    <div class="text-center py-3">
                                        <i class="bi bi-hourglass-split text-muted"></i>
                                        <p class="text-muted mb-0 small">Loading recent uploads...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="widget mb-4">
                <div class="widget-content">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute" style="left: 10px; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 5;"></i>
                                <input type="text" class="form-control ps-5" id="searchInput" placeholder="Search floors, offices, or departments...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="floorFilter">
                                <option value="">All Floors</option>
                                <!-- Options will be populated dynamically -->
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
                            <select class="form-select" id="departmentFilter">
                                <option value="">All Departments</option>
                                <!-- Options will be populated dynamically -->
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

            <!-- Floors and Offices Table -->
            <div class="widget mb-4">
                <div class="widget-header">
                    <h3 class="widget-title">Building Directory</h3>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info" id="totalCount">0 locations</span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshData()" title="Refresh Data">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportData()" title="Export to CSV">
                                <i class="bi bi-download"></i>
                            </button>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="setViewMode('floors')">Floors Only</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="setViewMode('offices')">Offices Only</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="setViewMode('all')">All Locations</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="widget-content p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="floorsOfficesTable">
                            <thead>
                                <tr>
                                    <th width="80">Type</th>
                                    <th>Floor Number</th>
                                    <th>Office/Area Name</th>
                                    <th>Department</th>
                                    <th>Guests Received</th>
                                    <th>Status</th>
                                    <th>Floor Map</th>
                                    <th width="140">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="floorsOfficesTableBody">
                                <!-- Loading skeleton -->
                                <tr class="loading-row">
                                    <td colspan="8" class="text-center py-5">
                                        <div class="spinner"></div>
                                        Loading building data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="widget-grid">
                <!-- Visitor Traffic by Floor -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Visitor Traffic by Floor</h3>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleChartView()">
                            <i class="bi bi-bar-chart"></i> Toggle View
                        </button>
                    </div>
                    <div class="widget-content">
                        <div style="height: 300px; position: relative;">
                            <canvas id="trafficChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Department Distribution -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">Popular Locations</h3>
                    </div>
                    <div class="widget-content">
                        <div id="popularLocations">
                            <!-- Popular locations will be populated here -->
                            <div class="text-center py-3">
                                <i class="bi bi-hourglass-split"></i>
                                <p class="mb-0 small text-muted">Loading location data...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions mt-4">
                <button class="quick-action-btn" onclick="showAddFloorModal()">
                    <i class="bi bi-plus-circle"></i>
                    Add New Floor
                </button>
                <button class="quick-action-btn" onclick="showAddOfficeModal()">
                    <i class="bi bi-door-open"></i>
                    Add New Office
                </button>
                <button class="quick-action-btn" onclick="viewAllMaps()">
                    <i class="bi bi-map"></i>
                    View Floor Maps
                </button>
                <button class="quick-action-btn" onclick="exportData()">
                    <i class="bi bi-download"></i>
                    Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Add Floor Modal -->
    <div class="modal fade" id="floorModal" tabindex="-1" aria-labelledby="floorModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="floorModalTitle">
                        <i class="bi bi-building me-2"></i>Add Floor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="floorForm" novalidate>
                        <div class="mb-3">
                            <label for="floorNumber" class="form-label">Floor Number *</label>
                            <input type="text" class="form-control" id="floorNumber" name="floor_number" required placeholder="e.g., Ground Floor, Floor 1, Mezzanine">
                            <div class="invalid-feedback">Please provide a floor number.</div>
                        </div>
                        <div class="mb-3">
                            <label for="floorName" class="form-label">Floor Name</label>
                            <input type="text" class="form-control" id="floorName" name="floor_name" placeholder="Optional: Floor nickname or description">
                        </div>
                        <div class="mb-3">
                            <label for="floorDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="floorDescription" name="description" rows="3" placeholder="Brief description of this floor"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="floorActive" name="is_active" checked>
                                <label class="form-check-label" for="floorActive">
                                    Floor is Active
                                </label>
                            </div>
                            <small class="text-muted">Inactive floors won't appear in visitor registration forms</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveFloor()" id="saveFloorBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check-circle"></i> Save Floor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Office Modal -->
    <div class="modal fade" id="officeModal" tabindex="-1" aria-labelledby="officeModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="officeModalTitle">
                        <i class="bi bi-door-open me-2"></i>Add Office
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="officeForm" novalidate>
                        <div class="mb-3">
                            <label for="officeFloor" class="form-label">Floor *</label>
                            <select class="form-select" id="officeFloor" name="floor_id" required>
                                <option value="">Select Floor</option>
                                <!-- Options will be populated dynamically -->
                            </select>
                            <div class="invalid-feedback">Please select a floor.</div>
                        </div>
                        <div class="mb-3">
                            <label for="officeName" class="form-label">Office/Room Name *</label>
                            <input type="text" class="form-control" id="officeName" name="office_name" required placeholder="e.g., Conference Room A, CEO Office, Reception">
                            <div class="invalid-feedback">Please provide an office name.</div>
                        </div>
                        <div class="mb-3">
                            <label for="officeDepartment" class="form-label">Department</label>
                            <input type="text" class="form-control" id="officeDepartment" name="department" placeholder="e.g., HR, IT, Finance, Operations">
                        </div>
                        <div class="mb-3">
                            <label for="officeCapacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="officeCapacity" name="capacity" min="1" placeholder="Maximum number of people">
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="officeActive" name="is_active" checked>
                                <label class="form-check-label" for="officeActive">
                                    Office is Active
                                </label>
                            </div>
                            <small class="text-muted">Inactive offices won't appear in visitor forms</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveOffice()" id="saveOfficeBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check-circle"></i> Save Office
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floor Map Viewer Modal -->
    <div class="modal fade" id="mapViewerModal" tabindex="-1" aria-labelledby="mapViewerTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mapViewerTitle">
                        <i class="bi bi-map me-2"></i>Floor Maps
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="mapViewerContent">
                        <!-- Map content will be loaded here -->
                    </div>
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
                        <h6 class="mt-3 mb-2">Are you sure you want to delete this item?</h6>
                        <p class="text-muted" id="deleteItemDescription">This action cannot be undone.</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> All related data will be permanently removed.
                        </div>
                        <p class="text-danger fw-bold mb-0" id="deleteItemName"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/floors-offices.js"></script>
</body>
</html>