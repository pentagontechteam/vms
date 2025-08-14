/* ===================================
   EMPLOYEES PAGE - EXTERNAL JS
   File: assets/employees.js
   =================================== */

// Global variables
let currentPage = 1;
let totalPages = 1;
let currentEmployees = [];
let selectedEmployees = [];
let currentEmployeeId = null;
let isEditMode = false;
let refreshInterval = 30000; // 30 seconds
let refreshTimer;
let profilePreviewTimeout;

/* ===================================
   INITIALIZATION
   =================================== */

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadEmployees();
    loadStats();
    initializeEventListeners();
    startAutoRefresh();
});

// Event listeners
function initializeEventListeners() {
    // Search input
    document.getElementById('searchInput').addEventListener('input', debounce(filterEmployees, 300));
    
    // Filter dropdowns
    document.getElementById('departmentFilter').addEventListener('change', filterEmployees);
    document.getElementById('statusFilter').addEventListener('change', filterEmployees);
    document.getElementById('hostFilter').addEventListener('change', filterEmployees);
    
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

    // Profile preview events
    document.addEventListener('mouseenter', function(e) {
        if (e.target.classList.contains('employee-name-cell')) {
            showProfilePreview(e);
        }
    }, true);

    document.addEventListener('mouseleave', function(e) {
        if (e.target.classList.contains('employee-name-cell')) {
            hideProfilePreview();
        }
    }, true);
}

/* ===================================
   DATA LOADING
   =================================== */

// Load employees data
function loadEmployees(page = 1) {
    showRefreshIndicator();
    
    const params = new URLSearchParams({
        action: 'get_employees',
        page: page,
        search: document.getElementById('searchInput').value || '',
        department: document.getElementById('departmentFilter').value || '',
        status: document.getElementById('statusFilter').value || '',
        host_filter: document.getElementById('hostFilter').value || ''
    });

    fetch(`employees_api.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentEmployees = data.employees || [];
                updateEmployeesTable(data.employees || []);
                updatePagination(data.pagination || {});
                updateStats(data.stats || {});
                currentPage = page;
                clearSelection();
            } else {
                showError(data.message || 'Failed to load employees data');
            }
        })
        .catch(error => {
            console.error('Error loading employees:', error);
            showError('Failed to load employees: ' + error.message);
            showErrorInTable();
        })
        .finally(() => {
            hideRefreshIndicator();
        });
}

// Load statistics
function loadStats() {
    fetch('employees_api.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalEmployees').textContent = data.stats.total_employees || 0;
                document.getElementById('activeHosts').textContent = data.stats.active_hosts || 0;
                document.getElementById('todaysHosts').textContent = data.stats.todays_hosts || 0;
                document.getElementById('totalGuests').textContent = data.stats.total_guests || 0;
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
        });
}

/* ===================================
   UI UPDATE FUNCTIONS
   =================================== */

// Update employees table
function updateEmployeesTable(employees) {
    const tbody = document.getElementById('employeesTableBody');
    
    if (employees.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people" style="font-size: 48px; opacity: 0.5;"></i>
                    <p class="mt-2 mb-0">No employees found</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = employees.map(employee => {
        const guestCount = employee.guest_count || 0;
        const isHost = guestCount > 0;
        
        return `
            <tr data-employee-id="${employee.id}">
                <td>
                    <input type="checkbox" class="form-check-input employee-checkbox" value="${employee.id}">
                </td>
                <td>
                    <div class="d-flex align-items-center employee-name-cell" 
                         data-employee='${JSON.stringify(employee).replace(/'/g, "&#39;")}'>
                        <div class="admin-avatar me-2" style="width: 32px; height: 32px; font-size: 12px;">
                            ${employee.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="fw-semibold">${escapeHtml(employee.name)}</div>
                            <small class="text-muted">${escapeHtml(employee.designation || 'No designation')}</small>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(employee.department || '-')}</td>
                <td>${escapeHtml(employee.email || '-')}</td>
                <td>${formatPhone(employee.country_code, employee.phone)}</td>
                <td>
                    <span class="badge ${isHost ? 'bg-success' : 'bg-secondary'}">
                        ${guestCount} ${guestCount === 1 ? 'guest' : 'guests'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewEmployee(${employee.id})" title="View Profile">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="editEmployee(${employee.id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteEmployee(${employee.id}, '${escapeHtml(employee.name)}')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Add event listeners to checkboxes
    document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedEmployees);
    });
}

// Update pagination
function updatePagination(pagination) {
    const paginationEl = document.getElementById('pagination');
    const paginationContainer = document.getElementById('paginationContainer');
    
    if (!pagination || pagination.total_pages <= 1) {
        paginationContainer.style.display = 'none';
        return;
    }
    
    paginationContainer.style.display = 'flex';
    totalPages = pagination.total_pages;
    
    let html = '';
    
    // Previous button
    const prevDisabled = pagination.current_page <= 1;
    html += `
        <li class="page-item ${prevDisabled ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="${prevDisabled ? 'return false;' : `loadEmployees(${pagination.current_page - 1}); return false;`}" ${prevDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                <i class="bi bi-chevron-left"></i>
                <span class="visually-hidden">Previous</span>
            </a>
        </li>
    `;
    
    // Page numbers (simplified for better UX)
    const maxPagesToShow = 5;
    let startPage = Math.max(1, pagination.current_page - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === pagination.current_page;
        html += `
            <li class="page-item ${isActive ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadEmployees(${i}); return false;" ${isActive ? 'aria-current="page"' : ''}>${i}</a>
            </li>
        `;
    }
    
    // Next button
    const nextDisabled = pagination.current_page >= totalPages;
    html += `
        <li class="page-item ${nextDisabled ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="${nextDisabled ? 'return false;' : `loadEmployees(${pagination.current_page + 1}); return false;`}" ${nextDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                <i class="bi bi-chevron-right"></i>
                <span class="visually-hidden">Next</span>
            </a>
        </li>
    `;
    
    paginationEl.innerHTML = html;
}

// Update stats display
function updateStats(stats) {
    document.getElementById('totalCount').textContent = `${stats.total || 0} employees`;
    document.getElementById('showingStart').textContent = stats.showing_start || 0;
    document.getElementById('showingEnd').textContent = stats.showing_end || 0;
    document.getElementById('totalRecords').textContent = stats.total || 0;
}

/* ===================================
   PROFILE PREVIEW FUNCTIONALITY
   =================================== */

// Show profile preview on hover
function showProfilePreview(event) {
    clearTimeout(profilePreviewTimeout);
    
    profilePreviewTimeout = setTimeout(() => {
        const employeeData = JSON.parse(event.target.closest('.employee-name-cell').dataset.employee);
        const preview = document.getElementById('profilePreview');
        
        // Update preview content
        document.getElementById('previewAvatar').textContent = employeeData.name.charAt(0).toUpperCase();
        document.getElementById('previewName').textContent = employeeData.name;
        document.getElementById('previewDesignation').textContent = employeeData.designation || 'No designation';
        document.getElementById('previewEmail').textContent = employeeData.email || 'No email';
        document.getElementById('previewPhone').textContent = formatPhone(employeeData.country_code, employeeData.phone);
        document.getElementById('previewDepartment').textContent = employeeData.department || 'No department';
        document.getElementById('previewGuestCount').textContent = `${employeeData.guest_count || 0} Assigned Guests`;
        
        // Position and show preview
        const rect = event.target.getBoundingClientRect();
        preview.style.left = (rect.right + 10) + 'px';
        preview.style.top = rect.top + 'px';
        preview.classList.remove('d-none');
    }, 500); // Show after 500ms hover
}

// Hide profile preview
function hideProfilePreview() {
    clearTimeout(profilePreviewTimeout);
    document.getElementById('profilePreview').classList.add('d-none');
}

/* ===================================
   MODAL FUNCTIONS
   =================================== */

// Show add employee modal
function showAddEmployeeModal() {
    isEditMode = false;
    currentEmployeeId = null;
    document.getElementById('addEmployeeModalTitle').textContent = 'Add New Employee';
    document.getElementById('employeeForm').reset();
    new bootstrap.Modal(document.getElementById('addEmployeeModal')).show();
}

// View employee profile
function viewEmployee(id) {
    const employee = currentEmployees.find(e => e.id == id);
    if (!employee) {
        showError('Employee not found');
        return;
    }
    
    currentEmployeeId = id;
    
    const modalBody = document.getElementById('employeeModalBody');
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="admin-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 32px;">
                    ${employee.name.charAt(0).toUpperCase()}
                </div>
                <h5>${escapeHtml(employee.name)}</h5>
                <p class="text-muted">${escapeHtml(employee.designation || 'No designation')}</p>
                <span class="badge ${employee.guest_count > 0 ? 'bg-success' : 'bg-secondary'} mb-3">
                    ${employee.guest_count || 0} Assigned Guests
                </span>
            </div>
            <div class="col-md-8">
                <h6 class="text-primary mb-3">Contact Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="fw-semibold">Email:</td>
                        <td>${escapeHtml(employee.email || '-')}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Phone:</td>
                        <td>${formatPhone(employee.country_code, employee.phone)}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Department:</td>
                        <td>${escapeHtml(employee.department || '-')}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Organization:</td>
                        <td>${escapeHtml(employee.organization || '-')}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Can Host:</td>
                        <td>${employee.guest_count > 0 || employee.can_host ? 'Yes' : 'No'}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Created:</td>
                        <td>${formatDateTime(employee.created_at)}</td>
                    </tr>
                </table>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('employeeModal')).show();
}

// Edit employee
function editEmployee(id) {
    const employee = currentEmployees.find(e => e.id == id);
    if (!employee) {
        showError('Employee not found');
        return;
    }
    
    isEditMode = true;
    currentEmployeeId = id;
    document.getElementById('addEmployeeModalTitle').textContent = 'Edit Employee';
    
    // Populate form
    document.getElementById('employeeName').value = employee.name || '';
    document.getElementById('employeeEmail').value = employee.email || '';
    document.getElementById('employeePhone').value = employee.phone || '';
    document.getElementById('countryCode').value = employee.country_code || '+234';
    document.getElementById('employeeDesignation').value = employee.designation || '';
    document.getElementById('employeeDepartment').value = employee.department || '';
    document.getElementById('employeeOrganization').value = employee.organization || '';
    document.getElementById('canHost').checked = employee.can_host || false;
    
    new bootstrap.Modal(document.getElementById('addEmployeeModal')).show();
}

// Save employee
function saveEmployee() {
    const form = document.getElementById('employeeForm');
    const saveBtn = document.getElementById('saveEmployeeBtn');
    const spinner = saveBtn.querySelector('.spinner-border');
    const icon = saveBtn.querySelector('.bi-check');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Show loading state
    saveBtn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Handle checkbox
    data.can_host = document.getElementById('canHost').checked;
    
    const action = isEditMode ? 'update_employee' : 'create_employee';
    if (isEditMode) {
        data.id = currentEmployeeId;
    }
    
    fetch('employees_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: action,
            data: data
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal')).hide();
            loadEmployees(currentPage);
            loadStats();
            showSuccess(isEditMode ? 'Employee updated successfully' : 'Employee added successfully');
        } else {
            showError(result.message || 'Failed to save employee');
        }
    })
    .catch(error => {
        console.error('Error saving employee:', error);
        showError('Failed to save employee: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
    });
}

// Delete employee
function deleteEmployee(id, name) {
    currentEmployeeId = id;
    document.getElementById('deleteEmployeeName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Confirm delete
function confirmDelete() {
    if (!currentEmployeeId) return;
    
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const spinner = deleteBtn.querySelector('.spinner-border');
    const icon = deleteBtn.querySelector('.bi-trash');
    
    // Show loading state
    deleteBtn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    
    fetch('employees_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_employee',
            id: currentEmployeeId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            
            // If we deleted all records on current page, go to previous page
            if (currentEmployees.length === 1 && currentPage > 1) {
                loadEmployees(currentPage - 1);
            } else {
                loadEmployees(currentPage);
            }
            loadStats();
            showSuccess('Employee deleted successfully');
        } else {
            showError(result.message || 'Failed to delete employee');
        }
    })
    .catch(error => {
        console.error('Error deleting employee:', error);
        showError('Failed to delete employee: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        deleteBtn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
    });
}

/* ===================================
   SELECTION & BULK OPERATIONS
   =================================== */

// Toggle select all
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelectedEmployees();
}

// Update selected employees
function updateSelectedEmployees() {
    const checkboxes = document.querySelectorAll('.employee-checkbox:checked');
    selectedEmployees = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedEmployees.length > 0) {
        bulkActionsBar.style.display = 'flex';
        selectedCount.textContent = `${selectedEmployees.length} selected`;
    } else {
        bulkActionsBar.style.display = 'none';
    }
    
    // Update select all checkbox
    const selectAll = document.getElementById('selectAll');
    const allCheckboxes = document.querySelectorAll('.employee-checkbox');
    if (allCheckboxes.length > 0) {
        selectAll.indeterminate = selectedEmployees.length > 0 && selectedEmployees.length < allCheckboxes.length;
        selectAll.checked = selectedEmployees.length === allCheckboxes.length;
    }
}

// Clear selection
function clearSelection() {
    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateSelectedEmployees();
}

// Bulk delete
function bulkDelete() {
    if (selectedEmployees.length === 0) return;
    
    if (confirm(`Are you sure you want to delete ${selectedEmployees.length} selected employees?`)) {
        fetch('employees_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'bulk_delete',
                ids: selectedEmployees
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadEmployees(currentPage);
                loadStats();
                clearSelection();
                showSuccess(`${selectedEmployees.length} employees deleted successfully`);
            } else {
                showError(result.message || 'Failed to delete employees');
            }
        })
        .catch(error => {
            console.error('Error bulk deleting:', error);
            showError('Failed to delete employees');
        });
    }
}

// Bulk export
function bulkExport() {
    if (selectedEmployees.length === 0) return;
    
    const params = new URLSearchParams({
        action: 'export_employees',
        ids: selectedEmployees.join(',')
    });
    
    window.open(`employees_api.php?${params}`, '_blank');
}

/* ===================================
   FILTER & SEARCH
   =================================== */

// Filter employees
function filterEmployees() {
    loadEmployees(1);
}

// Clear filters
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('hostFilter').value = '';
    loadEmployees(1);
}

// Export employees
function exportEmployees() {
    const params = new URLSearchParams({
        action: 'export_employees',
        search: document.getElementById('searchInput').value || '',
        department: document.getElementById('departmentFilter').value || '',
        status: document.getElementById('statusFilter').value || '',
        host_filter: document.getElementById('hostFilter').value || ''
    });
    
    window.open(`employees_api.php?${params}`, '_blank');
}

// Refresh data
function refreshData() {
    loadEmployees(currentPage);
    loadStats();
}

/* ===================================
   AUTO REFRESH
   =================================== */

function startAutoRefresh() {
    refreshTimer = setInterval(() => {
        if (!document.hidden) {
            loadEmployees(currentPage);
            loadStats();
        }
    }, refreshInterval);
}

/* ===================================
   UTILITY FUNCTIONS
   =================================== */

function formatPhone(countryCode, phone) {
    if (!phone) return '-';
    return `${countryCode || '+234'} ${phone}`;
}

function formatDateTime(datetime) {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleString();
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
    alert(message); // Replace with toast notification
}

function showError(message) {
    alert('Error: ' + message); // Replace with toast notification
}

function showErrorInTable() {
    const tbody = document.getElementById('employeesTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-5 text-danger">
                <i class="bi bi-exclamation-triangle" style="font-size: 48px; opacity: 0.5;"></i>
                <p class="mt-2 mb-0">Failed to load employees</p>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadEmployees(${currentPage})">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </td>
        </tr>
    `;
}

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(refreshTimer);
    } else {
        startAutoRefresh();
        loadEmployees(currentPage);
        loadStats();
    }
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    clearInterval(refreshTimer);
});

console.log('Employees page JS loaded successfully');