<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Control Panel - AATC Admin</title>
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
                    <i class="bi bi-grid-3x3-gap"></i>
                    <span>CRUD</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-table"></i>
                    <span>Tables</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="bi bi-person-badge"></i>
                    <span>Employees</span>
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
                <h1>CRUD Control Panel</h1>
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
                    <h2 class="text-primary fw-bold mb-1">Database Management</h2>
                    <p class="text-muted mb-0">Create, Read, Update, and Delete database records</p>
                </div>
                <div>
                    <select class="form-select" id="tableSelector" style="width: 200px;" aria-label="Select database table">
                        <option value="">Select Table...</option>
                        <option value="visitors">Visitors</option>
                        <option value="employees">Employees</option>
                        <option value="receptionists">Receptionists</option>
                        <option value="cso">CSO</option>
                        <option value="daily_premises_entries">Daily Premises Entries</option>
                        <option value="visitor_categories">Visitor Categories</option>
                        <option value="premises_entry_log">Premises Entry Log</option>
                        <option value="notifications">Notifications</option>
                        <option value="password_resets">Password Resets</option>
                        <option value="reception_notifications">Reception Notifications</option>
                        <option value="daily_statistics">Daily Statistics</option>
                        <option value="enhanced_entry_log">Enhanced Entry Log</option>
                    </select>
                    <button class="btn btn-outline-primary" onclick="refreshData()" title="Refresh current data">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <!-- CRUD Operations Grid -->
            <div class="stats-grid mb-4" id="crudGrid" style="display: none;">
                <div class="stat-card" onclick="showCreateModal()" style="cursor: pointer;" tabindex="0" role="button" aria-label="Create new record">
                    <i class="bi bi-plus-circle stat-icon" style="color: #28a745;"></i>
                    <div class="stat-number">CREATE</div>
                    <div class="stat-label">Add New Record</div>
                </div>
                <div class="stat-card" onclick="loadTableData()" style="cursor: pointer;" tabindex="0" role="button" aria-label="View all records">
                    <i class="bi bi-eye stat-icon" style="color: #17a2b8;"></i>
                    <div class="stat-number">READ</div>
                    <div class="stat-label">View All Records</div>
                </div>
                <div class="stat-card" onclick="enableBulkUpdate()" style="cursor: pointer;" tabindex="0" role="button" aria-label="Update records">
                    <i class="bi bi-pencil-square stat-icon" style="color: #ffc107;"></i>
                    <div class="stat-number">UPDATE</div>
                    <div class="stat-label">Edit Records</div>
                </div>
                <div class="stat-card" onclick="enableBulkDelete()" style="cursor: pointer;" tabindex="0" role="button" aria-label="Delete records">
                    <i class="bi bi-trash stat-icon" style="color: #dc3545;"></i>
                    <div class="stat-number">DELETE</div>
                    <div class="stat-label">Remove Records</div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="widget mb-4" id="searchWidget" style="display: none;">
                <div class="widget-content">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute" style="left: 10px; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
                                <input type="text" class="form-control ps-5" id="searchInput" placeholder="Search records...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="columnFilter" aria-label="Filter by column">
                                <option value="">All Columns</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100" onclick="clearSearch()">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="widget" id="dataTableWidget" style="display: none;">
                <div class="widget-header">
                    <h3 class="widget-title" id="tableTitle">Table Data</h3>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info" id="recordCount">0 records</span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-success" onclick="showCreateModal()" title="Add new record">
                                <i class="bi bi-plus"></i> Add Entry
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="exportTable()" title="Export table data">
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
                                <button class="btn btn-sm btn-outline-warning" onclick="bulkEdit()">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-link text-muted" onclick="clearSelection()">
                            Clear Selection
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="dataTable">
                            <thead id="tableHead">
                                <!-- Table headers will be dynamically generated -->
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="100%" class="text-center py-5 text-muted">
                                        <i class="bi bi-table" style="font-size: 48px; opacity: 0.5;"></i>
                                        <p class="mt-2 mb-0">Select a table to view data</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4" id="paginationContainer" style="display: none;">
                        <div class="text-muted">
                            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> records
                        </div>
                        <nav aria-label="Table pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination">
                                <!-- Pagination will be generated here -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="recordModalTitle">Add Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="recordModalBody">
                    <!-- Form will be dynamically generated -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveRecord()" id="saveRecordBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check"></i> Save
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
                    <p>Are you sure you want to delete the selected record(s)?</p>
                    <p class="text-danger fw-bold mb-0" id="deleteRecordInfo"></p>
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

-item ${pagination.current_page <= 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTableData(${pagination.current_page - 1})">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                    html += `
                        <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="loadTableData(${i})">${i}</a>
                        </li>
                    `;
                } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            html += `
                <li class="page-item ${pagination.current_page >= totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTableData(${pagination.current_page + 1})">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            `;
            
            paginationEl.innerHTML = html;
        }

        // Update stats
        function updateStats(stats) {
            document.getElementById('recordCount').textContent = `${stats.total} records`;
            document.getElementById('showingStart').textContent = stats.showing_start;
            document.getElementById('showingEnd').textContent = stats.showing_end;
            document.getElementById('totalRecords').textContent = stats.total;
        }

        // Search table
        function searchTable() {
            loadTableData(1);
        }

        // Clear search
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('columnFilter').value = '';
            loadTableData(1);
        }

        // Show create modal
        function showCreateModal() {
            if (!currentTable) {
                showError('Please select a table first');
                return;
            }
            
            isEditMode = false;
            currentRecord = null;
            document.getElementById('recordModalTitle').textContent = `Add New ${currentTable} Record`;
            generateForm();
            new bootstrap.Modal(document.getElementById('recordModal')).show();
        }

        // Edit record
        function editRecord(id) {
            const record = currentTableData.find(r => r.id == id || r[Object.keys(r)[0]] == id);
            if (!record) {
                showError('Record not found');
                return;
            }
            
            isEditMode = true;
            currentRecord = record;
            document.getElementById('recordModalTitle').textContent = `Edit ${currentTable} Record`;
            generateForm(record);
            new bootstrap.Modal(document.getElementById('recordModal')).show();
        }

        // Generate form based on table columns
        function generateForm(record = null) {
            const modalBody = document.getElementById('recordModalBody');
            
            let formHtml = '<form id="recordForm">';
            
            currentTableColumns.forEach(column => {
                const fieldName = column.Field;
                const fieldType = column.Type;
                const isNullable = column.Null === 'YES';
                const defaultValue = record ? record[fieldName] : '';
                
                // Skip auto-increment primary keys when creating
                if (!isEditMode && column.Key === 'PRI' && column.Extra === 'auto_increment') {
                    return;
                }
                
                formHtml += `<div class="mb-3">`;
                formHtml += `<label for="${fieldName}" class="form-label">${fieldName}${!isNullable ? ' *' : ''}</label>`;
                
                // Determine input type based on MySQL field type
                if (fieldType.includes('text') || fieldType.includes('varchar')) {
                    if (fieldType.includes('text')) {
                        formHtml += `<textarea class="form-control" id="${fieldName}" name="${fieldName}" ${!isNullable ? 'required' : ''}>${escapeHtml(defaultValue || '')}</textarea>`;
                    } else {
                        formHtml += `<input type="text" class="form-control" id="${fieldName}" name="${fieldName}" value="${escapeHtml(defaultValue || '')}" ${!isNullable ? 'required' : ''}>`;
                    }
                } else if (fieldType.includes('int')) {
                    // For primary keys in edit mode, make read-only
                    const readonly = isEditMode && column.Key === 'PRI' ? 'readonly' : '';
                    formHtml += `<input type="number" class="form-control" id="${fieldName}" name="${fieldName}" value="${defaultValue || ''}" ${!isNullable ? 'required' : ''} ${readonly}>`;
                } else if (fieldType.includes('date')) {
                    if (fieldType.includes('datetime') || fieldType.includes('timestamp')) {
                        const dateValue = defaultValue ? new Date(defaultValue).toISOString().slice(0, 16) : '';
                        formHtml += `<input type="datetime-local" class="form-control" id="${fieldName}" name="${fieldName}" value="${dateValue}" ${!isNullable ? 'required' : ''}>`;
                    } else if (fieldType.includes('time')) {
                        formHtml += `<input type="time" class="form-control" id="${fieldName}" name="${fieldName}" value="${defaultValue || ''}" ${!isNullable ? 'required' : ''}>`;
                    } else {
                        const dateValue = defaultValue ? new Date(defaultValue).toISOString().split('T')[0] : '';
                        formHtml += `<input type="date" class="form-control" id="${fieldName}" name="${fieldName}" value="${dateValue}" ${!isNullable ? 'required' : ''}>`;
                    }
                } else if (fieldType.includes('enum')) {
                    // Extract enum values
                    const enumMatch = fieldType.match(/enum\((.+)\)/);
                    if (enumMatch) {
                        const enumValues = enumMatch[1].split(',').map(v => v.replace(/'/g, ''));
                        formHtml += `<select class="form-select" id="${fieldName}" name="${fieldName}" ${!isNullable ? 'required' : ''}>`;
                        if (isNullable) formHtml += '<option value="">Select...</option>';
                        enumValues.forEach(value => {
                            const selected = defaultValue === value ? 'selected' : '';
                            formHtml += `<option value="${value}" ${selected}>${value}</option>`;
                        });
                        formHtml += '</select>';
                    } else {
                        formHtml += `<input type="text" class="form-control" id="${fieldName}" name="${fieldName}" value="${escapeHtml(defaultValue || '')}" ${!isNullable ? 'required' : ''}>`;
                    }
                } else if (fieldType.includes('decimal') || fieldType.includes('float') || fieldType.includes('double')) {
                    formHtml += `<input type="number" step="0.01" class="form-control" id="${fieldName}" name="${fieldName}" value="${defaultValue || ''}" ${!isNullable ? 'required' : ''}>`;
                } else {
                    // Default to text input
                    formHtml += `<input type="text" class="form-control" id="${fieldName}" name="${fieldName}" value="${escapeHtml(defaultValue || '')}" ${!isNullable ? 'required' : ''}>`;
                }
                
                formHtml += '</div>';
            });
            
            formHtml += '</form>';
            modalBody.innerHTML = formHtml;
        }

        // Save record (create or update)
        function saveRecord() {
            const form = document.getElementById('recordForm');
            const formData = new FormData(form);
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            const action = isEditMode ? 'update_record' : 'create_record';
            
            fetch('crud_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    table: currentTable,
                    data: data
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('recordModal')).hide();
                    loadTableData(currentPage);
                    showSuccess(isEditMode ? 'Record updated successfully' : 'Record created successfully');
                } else {
                    showError(result.message || 'Failed to save record');
                }
            })
            .catch(error => {
                console.error('Error saving record:', error);
                showError('Failed to save record');
            });
        }

        // Delete record
        function deleteRecord(id) {
            selectedRecords = [id];
            document.getElementById('deleteRecordInfo').textContent = `Record ID: ${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Confirm delete
        function confirmDelete() {
            if (selectedRecords.length === 0) return;
            
            fetch('crud_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_records',
                    table: currentTable,
                    ids: selectedRecords
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    loadTableData(currentPage);
                    clearSelection();
                    showSuccess(`${selectedRecords.length} record(s) deleted successfully`);
                } else {
                    showError(result.message || 'Failed to delete records');
                }
            })
            .catch(error => {
                console.error('Error deleting records:', error);
                showError('Failed to delete records');
            });
        }

        // Select all functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.record-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedRecords();
        }

        // Update selected records
        function updateSelectedRecords() {
            const checkboxes = document.querySelectorAll('.record-checkbox:checked');
            selectedRecords = Array.from(checkboxes).map(cb => cb.value);
            
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedRecords.length > 0) {
                bulkActionsBar.style.display = 'flex';
                selectedCount.textContent = `${selectedRecords.length} selected`;
            } else {
                bulkActionsBar.style.display = 'none';
            }
            
            // Update select all checkbox
            const selectAll = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('.record-checkbox');
            if (allCheckboxes.length > 0) {
                selectAll.indeterminate = selectedRecords.length > 0 && selectedRecords.length < allCheckboxes.length;
                selectAll.checked = selectedRecords.length === allCheckboxes.length;
            }
        }

        // Clear selection
        function clearSelection() {
            document.querySelectorAll('.record-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelectedRecords();
        }

        // Bulk operations
        function enableBulkUpdate() {
            if (selectedRecords.length === 0) {
                showError('Please select records to update');
                return;
            }
            showError('Bulk update feature coming soon');
        }

        function enableBulkDelete() {
            if (selectedRecords.length === 0) {
                showError('Please select records to delete');
                return;
            }
            
            document.getElementById('deleteRecordInfo').textContent = `${selectedRecords.length} selected records`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function bulkEdit() {
            if (selectedRecords.length === 0) return;
            showError('Bulk edit feature coming soon');
        }

        function bulkDelete() {
            if (selectedRecords.length === 0) return;
            enableBulkDelete();
        }

        // Export table
        function exportTable() {
            if (!currentTable) {
                showError('Please select a table first');
                return;
            }
            
            const params = new URLSearchParams({
                action: 'export_table',
                table: currentTable,
                search: document.getElementById('searchInput').value,
                column: document.getElementById('columnFilter').value
            });
            
            window.open(`crud_api.php?${params}`, '_blank');
        }

        // Refresh data
        function refreshData() {
            if (currentTable) {
                loadTableData(currentPage);
            }
        }

        // Utility functions
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
    </script>
</body>
</html>