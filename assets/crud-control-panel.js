/* ===================================
   CRUD CONTROL PANEL - EXTERNAL JS
   File: assets/crud-control-panel.js
   =================================== */

// Global variables
let currentTable = '';
let currentTableColumns = [];
let currentTableData = [];
let selectedRecords = [];
let currentRecord = null;
let currentPage = 1;
let totalPages = 1;
let isEditMode = false;

/* ===================================
   INITIALIZATION
   =================================== */

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
});

// Event listeners
function initializeEventListeners() {
    // Table selector
    document.getElementById('tableSelector').addEventListener('change', function() {
        const table = this.value;
        if (table) {
            selectTable(table);
        } else {
            hideAllSections();
        }
    });

    // Search input
    document.getElementById('searchInput').addEventListener('input', debounce(searchTable, 300));
    
    // Column filter
    document.getElementById('columnFilter').addEventListener('change', searchTable);
    
    // Select all checkbox
    document.addEventListener('change', function(e) {
        if (e.target.id === 'selectAll') {
            toggleSelectAll();
        }
    });

    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
}

/* ===================================
   TABLE MANAGEMENT
   =================================== */

// Select table and load its data
function selectTable(tableName) {
    currentTable = tableName;
    showAllSections();
    loadTableStructure();
    loadTableData();
}

// Show all interface sections
function showAllSections() {
    document.getElementById('crudGrid').style.display = 'grid';
    document.getElementById('searchWidget').style.display = 'block';
    document.getElementById('dataTableWidget').style.display = 'block';
}

// Hide all interface sections
function hideAllSections() {
    document.getElementById('crudGrid').style.display = 'none';
    document.getElementById('searchWidget').style.display = 'none';
    document.getElementById('dataTableWidget').style.display = 'none';
}

// Load table structure
function loadTableStructure() {
    showRefreshIndicator();
    
    fetch(`crud_api.php?action=get_table_structure&table=${currentTable}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentTableColumns = data.columns;
                updateColumnFilter();
                document.getElementById('tableTitle').textContent = `${currentTable} Table`;
            } else {
                showError(data.message || 'Failed to load table structure');
            }
        })
        .catch(error => {
            console.error('Error loading table structure:', error);
            showError('Failed to load table structure');
        })
        .finally(() => {
            hideRefreshIndicator();
        });
}

// Load table data
function loadTableData(page = 1) {
    if (!currentTable) return;
    
    // Validate page number
    page = Math.max(1, parseInt(page) || 1);
    
    showRefreshIndicator();
    
    const params = new URLSearchParams({
        action: 'get_table_data',
        table: currentTable,
        page: page,
        search: document.getElementById('searchInput').value || '',
        column: document.getElementById('columnFilter').value || ''
    });

    fetch(`crud_api.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentTableData = data.data || [];
                updateDataTable(data.data || [], data.columns || []);
                updatePagination(data.pagination || {});
                updateStats(data.stats || {});
                currentPage = page;
                
                // Clear selection when loading new data
                clearSelection();
            } else {
                showError(data.message || 'Failed to load table data');
            }
        })
        .catch(error => {
            console.error('Error loading table data:', error);
            showError('Failed to load table data: ' + error.message);
            
            // Show empty state on error
            const tbody = document.getElementById('tableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="100%" class="text-center py-5 text-danger">
                            <i class="bi bi-exclamation-triangle" style="font-size: 48px; opacity: 0.5;"></i>
                            <p class="mt-2 mb-0">Failed to load data</p>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadTableData(${page})">
                                <i class="bi bi-arrow-clockwise"></i> Retry
                            </button>
                        </td>
                    </tr>
                `;
            }
        })
        .finally(() => {
            hideRefreshIndicator();
        });
}

/* ===================================
   UI UPDATE FUNCTIONS
   =================================== */

// Update column filter dropdown
function updateColumnFilter() {
    const columnFilter = document.getElementById('columnFilter');
    columnFilter.innerHTML = '<option value="">All Columns</option>';
    
    currentTableColumns.forEach(column => {
        const option = document.createElement('option');
        option.value = column.Field;
        option.textContent = column.Field;
        columnFilter.appendChild(option);
    });
}

// Update data table
function updateDataTable(data, columns) {
    const thead = document.getElementById('tableHead');
    const tbody = document.getElementById('tableBody');
    
    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="100%" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size: 48px; opacity: 0.5;"></i>
                    <p class="mt-2 mb-0">No records found</p>
                </td>
            </tr>
        `;
        thead.innerHTML = '';
        return;
    }

    // Build table header
    let headerHtml = '<tr><th width="40"><input type="checkbox" class="form-check-input" id="selectAll"></th>';
    columns.forEach(column => {
        headerHtml += `<th>${column}</th>`;
    });
    headerHtml += '<th width="120">Actions</th></tr>';
    thead.innerHTML = headerHtml;

    // Build table body
    tbody.innerHTML = data.map((row, index) => {
        const primaryKey = row.id || row[columns[0]]; // Use id or first column as primary key
        
        let rowHtml = `<tr data-id="${primaryKey}">
            <td><input type="checkbox" class="form-check-input record-checkbox" value="${primaryKey}"></td>`;
        
        columns.forEach(column => {
            let cellValue = row[column];
            if (cellValue === null || cellValue === undefined) {
                cellValue = '-';
            } else if (typeof cellValue === 'string' && cellValue.length > 50) {
                cellValue = cellValue.substring(0, 50) + '...';
            }
            rowHtml += `<td>${escapeHtml(cellValue)}</td>`;
        });
        
        rowHtml += `
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="editRecord('${primaryKey}')" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteRecord('${primaryKey}')" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
        
        return rowHtml;
    }).join('');

    // Add event listeners to checkboxes
    document.querySelectorAll('.record-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedRecords);
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
            <a class="page-link" href="#" onclick="${prevDisabled ? 'return false;' : `loadTableData(${pagination.current_page - 1}); return false;`}" ${prevDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                <i class="bi bi-chevron-left"></i>
                <span class="visually-hidden">Previous</span>
            </a>
        </li>
    `;
    
    // Calculate page range to show
    const maxPagesToShow = 5;
    let startPage = Math.max(1, pagination.current_page - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    
    // Adjust if we're near the end
    if (endPage - startPage + 1 < maxPagesToShow) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    
    // First page + ellipsis
    if (startPage > 1) {
        html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadTableData(1); return false;">1</a>
            </li>
        `;
        if (startPage > 2) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === pagination.current_page;
        html += `
            <li class="page-item ${isActive ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadTableData(${i}); return false;" ${isActive ? 'aria-current="page"' : ''}>${i}</a>
            </li>
        `;
    }
    
    // Last page + ellipsis
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadTableData(${totalPages}); return false;">${totalPages}</a>
            </li>
        `;
    }
    
    // Next button
    const nextDisabled = pagination.current_page >= totalPages;
    html += `
        <li class="page-item ${nextDisabled ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="${nextDisabled ? 'return false;' : `loadTableData(${pagination.current_page + 1}); return false;`}" ${nextDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                <i class="bi bi-chevron-right"></i>
                <span class="visually-hidden">Next</span>
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

/* ===================================
   CRUD OPERATIONS
   =================================== */

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
    const saveBtn = document.getElementById('saveRecordBtn');
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
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
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
        showError('Failed to save record: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        saveBtn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
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
    
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const spinner = deleteBtn.querySelector('.spinner-border');
    const icon = deleteBtn.querySelector('.bi-trash');
    
    // Show loading state
    deleteBtn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    
    fetch('crud_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_records',
            table: currentTable,
            ids: selectedRecords
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
            if (currentTableData.length === selectedRecords.length && currentPage > 1) {
                loadTableData(currentPage - 1);
            } else {
                loadTableData(currentPage);
            }
            clearSelection();
            showSuccess(`${selectedRecords.length} record(s) deleted successfully`);
        } else {
            showError(result.message || 'Failed to delete records');
        }
    })
    .catch(error => {
        console.error('Error deleting records:', error);
        showError('Failed to delete records: ' + error.message);
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

/* ===================================
   SEARCH & EXPORT
   =================================== */

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

/* ===================================
   UTILITY FUNCTIONS
   =================================== */

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

console.log('CRUD Control Panel JS loaded successfully');