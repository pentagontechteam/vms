<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitors Management - AATC Admin</title>
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
                <a href="#" class="nav-link active">
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
                <h1>Visitors Management</h1>
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
                    <h2 class="text-primary fw-bold mb-1">Visitors</h2>
                    <p class="text-muted mb-0">Manage all visitor records and activities</p>
                </div>
                <button class="btn btn-primary" onclick="addNewVisitor()">
                    <i class="bi bi-person-plus"></i> Add Visitor
                </button>
            </div>

            <!-- Search and Filter Bar -->
            <div class="widget mb-4">
                <div class="widget-content">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute" style="left: 10px; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
                                <input type="text" class="form-control ps-5" id="searchInput" placeholder="Search visitors...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="checked_in">Checked In</option>
                                <option value="checked_out">Checked Out</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="hostFilter">
                                <option value="">All Hosts</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" id="dateFilter">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" onclick="clearFilters()">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visitors Table -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">All Visitors</h3>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info" id="totalCount">0 visitors</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
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
                                    <i class="bi bi-download"></i> Export
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="bulkPrint()">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-link text-muted" onclick="clearSelection()">
                            Clear Selection
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="visitorsTable">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Host</th>
                                    <th>Organization</th>
                                    <th>Check-In Time</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="visitorsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="spinner"></div>
                                        Loading visitors...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> visitors
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination">
                                <!-- Pagination will be generated here -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Visitor Detail Modal -->
    <div class="modal fade" id="visitorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visitor Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="visitorModalBody">
                    <!-- Visitor details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" onclick="printVisitorBadge()">
                        <i class="bi bi-printer"></i> Print Badge
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editVisitor()">
                        <i class="bi bi-pencil"></i> Edit
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
                    <p>Are you sure you want to delete this visitor record?</p>
                    <p class="text-danger fw-bold mb-0" id="deleteVisitorName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        let currentPage = 1;
        let totalPages = 1;
        let currentVisitors = [];
        let selectedVisitors = [];
        let currentVisitorId = null;
        let refreshInterval = 30000; // 30 seconds
        let refreshTimer;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadHosts();
            loadVisitors();
            initializeEventListeners();
            startAutoRefresh();
        });

        // Event listeners
        function initializeEventListeners() {
            // Search input
            document.getElementById('searchInput').addEventListener('input', debounce(filterVisitors, 300));
            
            // Filter dropdowns
            document.getElementById('statusFilter').addEventListener('change', filterVisitors);
            document.getElementById('hostFilter').addEventListener('change', filterVisitors);
            document.getElementById('dateFilter').addEventListener('change', filterVisitors);
            
            // Select all checkbox
            document.getElementById('selectAll').addEventListener('change', toggleSelectAll);
            
            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        }

        // Load hosts for filter dropdown
        function loadHosts() {
            fetch('visitors_management_api.php?action=get_hosts')
                .then(response => response.json())
                .then(data => {
                    const hostFilter = document.getElementById('hostFilter');
                    data.forEach(host => {
                        const option = document.createElement('option');
                        option.value = host.name;
                        option.textContent = host.name;
                        hostFilter.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading hosts:', error));
        }

        // Load visitors data
        function loadVisitors(page = 1) {
            showRefreshIndicator();
            
            const params = new URLSearchParams({
                action: 'get_visitors',
                page: page,
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                host: document.getElementById('hostFilter').value,
                date: document.getElementById('dateFilter').value
            });

            fetch(`visitors_management_api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    currentVisitors = data.visitors;
                    updateVisitorsTable(data.visitors);
                    updatePagination(data.pagination);
                    updateStats(data.stats);
                    currentPage = page;
                })
                .catch(error => {
                    console.error('Error loading visitors:', error);
                    showError('Failed to load visitors data');
                })
                .finally(() => {
                    hideRefreshIndicator();
                });
        }

        // Update visitors table
        function updateVisitorsTable(visitors) {
            const tbody = document.getElementById('visitorsTableBody');
            
            if (visitors.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people" style="font-size: 48px; opacity: 0.5;"></i>
                            <p class="mt-2 mb-0">No visitors found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = visitors.map(visitor => `
                <tr data-visitor-id="${visitor.id}">
                    <td>
                        <input type="checkbox" class="form-check-input visitor-checkbox" value="${visitor.id}">
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="admin-avatar me-2" style="width: 32px; height: 32px; font-size: 12px;">
                                ${visitor.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div class="fw-semibold">${escapeHtml(visitor.name)}</div>
                                <small class="text-muted">${escapeHtml(visitor.email || '')}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge ${getStatusBadgeClass(visitor.status)}">${getStatusText(visitor.status)}</span>
                    </td>
                    <td>${escapeHtml(visitor.host_name || 'Walk-In')}</td>
                    <td>${escapeHtml(visitor.organization || '-')}</td>
                    <td>${formatDateTime(visitor.check_in_time)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewVisitor(${visitor.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editVisitorInline(${visitor.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteVisitor(${visitor.id}, '${escapeHtml(visitor.name)}')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            // Add event listeners to checkboxes
            document.querySelectorAll('.visitor-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedVisitors);
            });
        }

        // Update pagination
        function updatePagination(pagination) {
            const paginationEl = document.getElementById('pagination');
            totalPages = pagination.total_pages;
            
            let html = '';
            
            // Previous button
            html += `
                <li class="page-item ${pagination.current_page <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadVisitors(${pagination.current_page - 1})">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                    html += `
                        <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="loadVisitors(${i})">${i}</a>
                        </li>
                    `;
                } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            html += `
                <li class="page-item ${pagination.current_page >= totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadVisitors(${pagination.current_page + 1})">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            `;
            
            paginationEl.innerHTML = html;
        }

        // Update stats
        function updateStats(stats) {
            document.getElementById('totalCount').textContent = `${stats.total} visitors`;
            document.getElementById('showingStart').textContent = stats.showing_start;
            document.getElementById('showingEnd').textContent = stats.showing_end;
            document.getElementById('totalRecords').textContent = stats.total;
        }

        // Filter visitors
        function filterVisitors() {
            loadVisitors(1);
        }

        // Clear filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('hostFilter').value = '';
            document.getElementById('dateFilter').value = '';
            loadVisitors(1);
        }

        // Select all functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.visitor-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedVisitors();
        }

        // Update selected visitors
        function updateSelectedVisitors() {
            const checkboxes = document.querySelectorAll('.visitor-checkbox:checked');
            selectedVisitors = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedVisitors.length > 0) {
                bulkActionsBar.style.display = 'flex';
                selectedCount.textContent = `${selectedVisitors.length} selected`;
            } else {
                bulkActionsBar.style.display = 'none';
            }
            
            // Update select all checkbox
            const selectAll = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('.visitor-checkbox');
            selectAll.indeterminate = selectedVisitors.length > 0 && selectedVisitors.length < allCheckboxes.length;
            selectAll.checked = selectedVisitors.length === allCheckboxes.length && allCheckboxes.length > 0;
        }

        // Clear selection
        function clearSelection() {
            document.querySelectorAll('.visitor-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelectedVisitors();
        }

        // View visitor details
        function viewVisitor(id) {
            const visitor = currentVisitors.find(v => v.id === id);
            if (!visitor) return;
            
            currentVisitorId = id;
            
            const modalBody = document.getElementById('visitorModalBody');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Personal Information</h6>
                        <table class="table table-sm">
                            <tr><td class="fw-semibold">Name:</td><td>${escapeHtml(visitor.name)}</td></tr>
                            <tr><td class="fw-semibold">Email:</td><td>${escapeHtml(visitor.email || '-')}</td></tr>
                            <tr><td class="fw-semibold">Phone:</td><td>${escapeHtml(visitor.phone || '-')}</td></tr>
                            <tr><td class="fw-semibold">Organization:</td><td>${escapeHtml(visitor.organization || '-')}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Visit Information</h6>
                        <table class="table table-sm">
                            <tr><td class="fw-semibold">Status:</td><td><span class="badge ${getStatusBadgeClass(visitor.status)}">${getStatusText(visitor.status)}</span></td></tr>
                            <tr><td class="fw-semibold">Host:</td><td>${escapeHtml(visitor.host_name || 'Walk-In')}</td></tr>
                            <tr><td class="fw-semibold">Visit Date:</td><td>${formatDate(visitor.visit_date)}</td></tr>
                            <tr><td class="fw-semibold">Check-In:</td><td>${formatDateTime(visitor.check_in_time)}</td></tr>
                            <tr><td class="fw-semibold">Check-Out:</td><td>${formatDateTime(visitor.check_out_time)}</td></tr>
                            <tr><td class="fw-semibold">Floor:</td><td>${escapeHtml(visitor.floor_of_visit || '-')}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="text-primary">Visit Reason</h6>
                        <p class="text-muted">${escapeHtml(visitor.reason || 'No reason provided')}</p>
                    </div>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('visitorModal')).show();
        }

        // Edit visitor inline (placeholder)
        function editVisitorInline(id) {
            // For now, just show the view modal
            // In a full implementation, this would enable inline editing
            viewVisitor(id);
        }

        // Edit visitor from modal
        function editVisitor() {
            if (!currentVisitorId) return;
            // Implement edit functionality
            alert('Edit functionality would be implemented here');
        }

        // Delete visitor
        function deleteVisitor(id, name) {
            currentVisitorId = id;
            document.getElementById('deleteVisitorName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Confirm delete
        function confirmDelete() {
            if (!currentVisitorId) return;
            
            fetch('visitors_management_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_visitor',
                    id: currentVisitorId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    loadVisitors(currentPage);
                    showSuccess('Visitor deleted successfully');
                } else {
                    showError(data.message || 'Failed to delete visitor');
                }
            })
            .catch(error => {
                console.error('Error deleting visitor:', error);
                showError('Failed to delete visitor');
            });
        }

        // Bulk actions
        function bulkDelete() {
            if (selectedVisitors.length === 0) return;
            
            if (confirm(`Are you sure you want to delete ${selectedVisitors.length} selected visitors?`)) {
                fetch('visitors_management_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: selectedVisitors
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadVisitors(currentPage);
                        clearSelection();
                        showSuccess(`${selectedVisitors.length} visitors deleted successfully`);
                    } else {
                        showError(data.message || 'Failed to delete visitors');
                    }
                })
                .catch(error => {
                    console.error('Error bulk deleting:', error);
                    showError('Failed to delete visitors');
                });
            }
        }

        function bulkExport() {
            if (selectedVisitors.length === 0) return;
            
            const params = new URLSearchParams({
                action: 'export_visitors',
                ids: selectedVisitors.join(',')
            });
            
            window.open(`visitors_management_api.php?${params}`, '_blank');
        }

        function bulkPrint() {
            if (selectedVisitors.length === 0) return;
            
            const params = new URLSearchParams({
                action: 'print_badges',
                ids: selectedVisitors.join(',')
            });
            
            window.open(`visitors_management_api.php?${params}`, '_blank');
        }

        // Add new visitor
        function addNewVisitor() {
            window.location.href = 'register_walkin.php';
        }

        // Print visitor badge
        function printVisitorBadge() {
            if (!currentVisitorId) return;
            window.open(`print_badge.php?id=${currentVisitorId}`, '_blank');
        }

        // Auto refresh
        function startAutoRefresh() {
            refreshTimer = setInterval(() => {
                if (!document.hidden) {
                    loadVisitors(currentPage);
                }
            }, refreshInterval);
        }

        function refreshData() {
            loadVisitors(currentPage);
        }

        // Utility functions
        function getStatusBadgeClass(status) {
            const classes = {
                'pending': 'bg-warning',
                'approved': 'bg-info',
                'checked_in': 'bg-success',
                'checked_out': 'bg-secondary',
                'rejected': 'bg-danger'
            };
            return classes[status] || 'bg-secondary';
        }

        function getStatusText(status) {
            const texts = {
                'pending': 'Pending',
                'approved': 'Approved',
                'checked_in': 'Checked In',
                'checked_out': 'Checked Out',
                'rejected': 'Rejected'
            };
            return texts[status] || status;
        }

        function formatDateTime(datetime) {
            if (!datetime) return '-';
            return new Date(datetime).toLocaleString();
        }

        function formatDate(date) {
            if (!date) return '-';
            return new Date(date).toLocaleDateString();
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function showRefreshIndicator() {
            const indicator = document.getElementById('refreshIndicator');
            if (indicator) indicator.classList.add('show');
        }

        function hideRefreshIndicator() {
            setTimeout(() => {
                const indicator = document.getElementById('refreshIndicator');
                if (indicator) indicator.classList.remove('show');
            }, 1000);
        }

        function showSuccess(message) {
            // Create toast notification or use alert for now
            alert(message);
        }

        function showError(message) {
            // Create toast notification or use alert for now
            alert('Error: ' + message);
        }

        // Handle page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshTimer);
            } else {
                startAutoRefresh();
                loadVisitors(currentPage);
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(refreshTimer);
        });
    </script>
</body>
</html>