/* ===================================
   RECEPTIONISTS MANAGEMENT - EXTERNAL JS
   File: assets/receptionists.js
   =================================== */

// Global variables
let receptionistsData = [];
let performanceChart = null;
let currentEditingId = null;
let currentPasswordResetId = null;
let selectedReceptionists = [];

/* ===================================
   INITIALIZATION
   =================================== */

document.addEventListener('DOMContentLoaded', function() {
    try {
        initializeCharts();
        loadReceptionistsData();
        setupEventListeners();
        initializeSidebar();
        
        console.log('Receptionists Management initialized successfully');
    } catch (error) {
        console.error('Error initializing receptionists management:', error);
        showErrorAlert('Failed to initialize the page. Please refresh and try again.');
    }
});

/* ===================================
   INITIALIZATION FUNCTIONS
   =================================== */

function initializeCharts() {
    // Status Donut Chart
    const statusCtx = document.getElementById('performanceChart');
    if (statusCtx) {
        try {
            performanceChart = new Chart(statusCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Guests Processed',
                        data: [],
                        backgroundColor: 'rgba(7, 175, 139, 0.8)',
                        borderColor: '#07AF8B',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return context[0].label || 'Receptionist';
                                },
                                label: function(context) {
                                    return `Guests: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                stepSize: 1
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                maxRotation: 45,
                                callback: function(value, index, values) {
                                    const label = this.getLabelForValue(value);
                                    return label.length > 10 ? label.substring(0, 10) + '...' : label;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing performance chart:', error);
        }
    } else {
        console.warn('Performance chart canvas element not found');
    }
}

function setupEventListeners() {
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', debounce(filterReceptionists, 300));
    
    // Filter dropdowns
    document.getElementById('statusFilter').addEventListener('change', filterReceptionists);
    document.getElementById('performanceFilter').addEventListener('change', filterReceptionists);
    
    // Select all checkbox
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('#receptionistsTableBody input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkActions();
    });
    
    // Form validation
    document.getElementById('receptionistForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveReceptionist();
    });
    
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        changePassword();
    });
    
    // Password confirmation validation
    document.getElementById('confirmPassword').addEventListener('input', validatePasswordMatch);
}

/* ===================================
   DATA LOADING FUNCTIONS
   =================================== */

function loadReceptionistsData() {
    console.log('Starting to load receptionists data...');
    showLoading();
    
    // First test if API is working
    fetch('receptionists_api.php?action=test')
        .then(response => {
            console.log('Test API response status:', response.status);
            if (response.ok) {
                return response.json();
            }
            throw new Error('Test API failed: ' + response.status);
        })
        .then(testData => {
            console.log('Test API response:', testData);
            
            // Now load actual data
            return fetch('receptionists_api.php?action=get_all');
        })
        .then(response => {
            console.log('Main API response status:', response.status);
            console.log('Main API response headers:', [...response.headers.entries()]);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
                console.log('Raw response length:', text.length);
                console.log('Raw response (first 500 chars):', text.substring(0, 500));
                
                if (!text.trim()) {
                    throw new Error('Empty response from server');
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Full response text:', text);
                    throw new Error('Invalid JSON response: ' + e.message);
                }
            });
        })
        .then(data => {
            console.log('Parsed main data:', data);
            
            if (data && data.success) {
                receptionistsData = data.data || [];
                console.log('Loaded receptionists:', receptionistsData.length, 'items');
                
                if (receptionistsData.length === 0) {
                    console.warn('No receptionists data found - showing empty state');
                    showEmptyState();
                } else {
                    updateStatistics(data.statistics || {});
                    renderReceptionistsTable();
                    updatePerformanceChart();
                    updateTopPerformers();
                }
            } else {
                throw new Error(data.message || 'Server returned success=false');
            }
        })
        .catch(error => {
            console.error('Error loading receptionists:', error);
            console.error('Error stack:', error.stack);
            
            // Show different error messages based on error type
            let errorMessage = 'Failed to load receptionists data. ';
            
            if (error.message.includes('NetworkError') || error.message.includes('fetch')) {
                errorMessage += 'Network connection issue. Check your internet connection.';
            } else if (error.message.includes('JSON')) {
                errorMessage += 'Server response format error. Check server logs.';
            } else if (error.message.includes('HTTP error! status: 404')) {
                errorMessage += 'API endpoint not found. Check if receptionists_api.php exists.';
            } else if (error.message.includes('HTTP error! status: 500')) {
                errorMessage += 'Server error. Check server logs and database connection.';
            } else {
                errorMessage += error.message;
            }
            
            showErrorInTable(errorMessage);
        })
        .finally(() => {
            hideLoading();
        });
}

function showEmptyState() {
    const tbody = document.getElementById('receptionistsTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="bi bi-person-workspace" style="font-size: 48px; opacity: 0.3; color: var(--primary);"></i>
                    <h5 class="mt-3 text-muted">No Receptionists Found</h5>
                    <p class="text-muted mb-3">Get started by adding your first receptionist</p>
                    <button class="btn btn-primary" onclick="showAddReceptionistModal()">
                        <i class="bi bi-person-plus"></i> Add First Receptionist
                    </button>
                </td>
            </tr>
        `;
    }
    
    // Update statistics to show zeros
    updateStatistics({
        total_receptionists: 0,
        active_receptionists: 0,
        total_guests_processed: 0,
        todays_processed: 0
    });
    
    // Update count
    const totalCountElement = document.getElementById('totalCount');
    if (totalCountElement) {
        totalCountElement.textContent = '0 receptionists';
        totalCountElement.className = 'badge bg-secondary';
    }
}

/* ===================================
   STATISTICS UPDATE FUNCTIONS
   =================================== */

function updateStatistics(stats) {
    const elements = {
        'totalReceptionists': stats.total_receptionists || 0,
        'activeReceptionists': stats.active_today || 0,
        'totalGuestsProcessed': stats.total_guests_processed || 0,
        'todaysProcessed': stats.todays_processed || 0
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            animateNumber(element, parseInt(element.textContent) || 0, elements[id]);
        }
    });
}

/* ===================================
   TABLE RENDERING FUNCTIONS
   =================================== */

function renderReceptionistsTable(dataToRender = null) {
    const tbody = document.getElementById('receptionistsTableBody');
    const data = dataToRender || receptionistsData;
    
    if (!tbody) {
        console.error('Table body element not found');
        return;
    }
    
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="bi bi-person-workspace" style="font-size: 48px; opacity: 0.3;"></i>
                    <p class="mt-2 mb-0 text-muted">No receptionists found</p>
                </td>
            </tr>
        `;
        const totalCountElement = document.getElementById('totalCount');
        if (totalCountElement) {
            totalCountElement.textContent = '0 receptionists';
        }
        return;
    }
    
    tbody.innerHTML = data.map(receptionist => {
        const safeReceptionistName = escapeHtml(receptionist.name || 'Unknown');
        const safeUsername = escapeHtml(receptionist.username || 'N/A');
        const guestsProcessed = parseInt(receptionist.guests_processed) || 0;
        const performanceLevel = receptionist.performance_level || 'Low';
        const status = receptionist.status || 'inactive';
        
        return `
        <tr data-id="${receptionist.id}">
            <td>
                <input type="checkbox" class="form-check-input receptionist-checkbox" 
                       value="${receptionist.id}" onchange="updateBulkActions()">
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="admin-avatar me-3" style="width: 35px; height: 35px; font-size: 14px;">
                        ${(receptionist.name || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="fw-semibold">${safeReceptionistName}</div>
                        <small class="text-muted">ID: ${receptionist.id}</small>
                    </div>
                </div>
            </td>
            <td>
                <code class="bg-light px-2 py-1 rounded">${safeUsername}</code>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <span class="fw-bold me-2">${guestsProcessed}</span>
                    <small class="text-muted">guests</small>
                </div>
            </td>
            <td>
                <span class="badge ${getStatusBadgeClass(status)}">
                    <i class="bi ${getStatusIcon(status)}"></i>
                    ${getStatusText(status)}
                </span>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="progress me-2" style="width: 80px; height: 8px;">
                        <div class="progress-bar ${getPerformanceBarClass(performanceLevel)}" 
                             style="width: ${getPerformancePercentage(guestsProcessed)}%"></div>
                    </div>
                    <span class="badge ${getPerformanceBadgeClass(performanceLevel)}">
                        ${performanceLevel}
                    </span>
                </div>
            </td>
            <td>
                <div class="btn-group action-buttons">
                    <button class="btn btn-sm btn-outline-primary" onclick="editReceptionist(${receptionist.id})" 
                            title="Edit Receptionist">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="showPasswordModal(${receptionist.id}, '${safeReceptionistName.replace(/'/g, "\\'")}')" 
                            title="Change Password">
                        <i class="bi bi-key"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="showDeleteModal(${receptionist.id}, '${safeReceptionistName.replace(/'/g, "\\'")}')" 
                            title="Delete Receptionist">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        `;
    }).join('');
    
    // Update count safely
    const totalCountElement = document.getElementById('totalCount');
    if (totalCountElement) {
        totalCountElement.textContent = `${data.length} receptionist${data.length !== 1 ? 's' : ''}`;
    }
}

/* ===================================
   PERFORMANCE CHART FUNCTIONS
   =================================== */

function updatePerformanceChart() {
    if (!performanceChart || !receptionistsData || receptionistsData.length === 0) return;
    
    try {
        // Sort by guests processed (descending) and take top 10
        const sortedData = [...receptionistsData]
            .filter(r => r && r.name) // Filter out null/undefined entries
            .sort((a, b) => (parseInt(b.guests_processed) || 0) - (parseInt(a.guests_processed) || 0))
            .slice(0, 10);
        
        if (sortedData.length === 0) {
            performanceChart.data.labels = [];
            performanceChart.data.datasets[0].data = [];
        } else {
            performanceChart.data.labels = sortedData.map(r => (r.name || 'Unknown').substring(0, 15)); // Limit name length
            performanceChart.data.datasets[0].data = sortedData.map(r => parseInt(r.guests_processed) || 0);
        }
        
        performanceChart.update('none'); // Use 'none' to prevent animation errors
    } catch (error) {
        console.error('Error updating performance chart:', error);
    }
}

function updateTopPerformers() {
    const container = document.getElementById('topPerformers');
    if (!container) return;
    
    if (!receptionistsData || receptionistsData.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-3"><i class="bi bi-info-circle me-2"></i>No data available</p>';
        return;
    }
    
    try {
        const topPerformers = [...receptionistsData]
            .filter(r => r && r.name) // Filter out invalid entries
            .sort((a, b) => (parseInt(b.guests_processed) || 0) - (parseInt(a.guests_processed) || 0))
            .slice(0, 5);
        
        if (topPerformers.length === 0) {
            container.innerHTML = '<p class="text-muted text-center py-3"><i class="bi bi-info-circle me-2"></i>No performers found</p>';
            return;
        }
        
        container.innerHTML = topPerformers.map((performer, index) => {
            const safeName = escapeHtml(performer.name || 'Unknown');
            const guestsProcessed = parseInt(performer.guests_processed) || 0;
            
            return `
            <div class="top-performer-item mb-3">
                <div class="d-flex align-items-center">
                    <div class="position-relative me-3">
                        <div class="admin-avatar" style="width: 35px; height: 35px; font-size: 14px;">
                            ${(performer.name || 'U').charAt(0).toUpperCase()}
                        </div>
                        <span class="performer-rank">
                            ${index + 1}
                        </span>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="fw-semibold text-truncate">${safeName}</div>
                        <small class="text-muted">${guestsProcessed} guests processed</small>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
    } catch (error) {
        console.error('Error updating top performers:', error);
        container.innerHTML = '<p class="text-danger text-center py-3"><i class="bi bi-exclamation-triangle me-2"></i>Error loading data</p>';
    }
}

function toggleChartView() {
    if (!performanceChart) return;
    
    try {
        const currentType = performanceChart.config.type;
        const newType = currentType === 'bar' ? 'doughnut' : 'bar';
        
        // Store current data
        const labels = performanceChart.data.labels;
        const data = performanceChart.data.datasets[0].data;
        
        // Destroy current chart
        performanceChart.destroy();
        
        // Get canvas element
        const chartCtx = document.getElementById('performanceChart');
        if (!chartCtx) {
            console.error('Chart canvas element not found');
            return;
        }
        
        if (newType === 'doughnut') {
            performanceChart = new Chart(chartCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#07AF8B', '#FFCA00', '#3B82F6', '#EF4444', '#8B5CF6',
                            '#F59E0B', '#10B981', '#F97316', '#EC4899', '#6366F1'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return `${label}: ${value} guests`;
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // Reinitialize bar chart
            initializeCharts();
            updatePerformanceChart();
        }
    } catch (error) {
        console.error('Error toggling chart view:', error);
        // Try to reinitialize the original chart
        initializeCharts();
        updatePerformanceChart();
    }
}

/* ===================================
   MODAL FUNCTIONS
   =================================== */

function showAddReceptionistModal() {
    currentEditingId = null;
    document.getElementById('receptionistModalTitle').textContent = 'Add Receptionist';
    document.getElementById('receptionistForm').reset();
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('receptionistPassword').required = true;
    
    const modal = new bootstrap.Modal(document.getElementById('receptionistModal'));
    modal.show();
}

function editReceptionist(id) {
    const receptionist = receptionistsData.find(r => r.id === id);
    if (!receptionist) return;
    
    currentEditingId = id;
    document.getElementById('receptionistModalTitle').textContent = 'Edit Receptionist';
    document.getElementById('receptionistName').value = receptionist.name;
    document.getElementById('receptionistUsername').value = receptionist.username;
    document.getElementById('passwordField').style.display = 'none';
    document.getElementById('receptionistPassword').required = false;
    
    const modal = new bootstrap.Modal(document.getElementById('receptionistModal'));
    modal.show();
}

function showPasswordModal(id, name) {
    currentPasswordResetId = id;
    document.getElementById('passwordTargetName').textContent = name;
    document.getElementById('passwordForm').reset();
    
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

function showDeleteModal(id, name) {
    currentEditingId = id;
    document.getElementById('deleteReceptionistName').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

/* ===================================
   CRUD OPERATIONS
   =================================== */

function saveReceptionist() {
    const form = document.getElementById('receptionistForm');
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveReceptionistBtn');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Show loading state
    const spinner = saveBtn.querySelector('.spinner-border');
    const icon = saveBtn.querySelector('i');
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    saveBtn.disabled = true;
    
    const url = currentEditingId ? 
        `receptionists_api.php?action=update&id=${currentEditingId}` : 
        'receptionists_api.php?action=create';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            loadReceptionistsData();
            bootstrap.Modal.getInstance(document.getElementById('receptionistModal')).hide();
        } else {
            throw new Error(data.message || 'Failed to save receptionist');
        }
    })
    .catch(error => {
        console.error('Error saving receptionist:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        // Reset button state
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        saveBtn.disabled = false;
    });
}

function changePassword() {
    const form = document.getElementById('passwordForm');
    const formData = new FormData(form);
    const changeBtn = document.getElementById('changePasswordBtn');
    
    // Validate passwords match
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        showErrorAlert('Passwords do not match');
        return;
    }
    
    if (newPassword.length < 6) {
        showErrorAlert('Password must be at least 6 characters');
        return;
    }
    
    // Show loading state
    const spinner = changeBtn.querySelector('.spinner-border');
    const icon = changeBtn.querySelector('i');
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    changeBtn.disabled = true;
    
    formData.append('id', currentPasswordResetId);
    
    fetch('receptionists_api.php?action=change_password', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
        } else {
            throw new Error(data.message || 'Failed to change password');
        }
    })
    .catch(error => {
        console.error('Error changing password:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        // Reset button state
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        changeBtn.disabled = false;
    });
}

function confirmDelete() {
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    
    // Show loading state
    const spinner = deleteBtn.querySelector('.spinner-border');
    const icon = deleteBtn.querySelector('i');
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
    deleteBtn.disabled = true;
    
    fetch(`receptionists_api.php?action=delete&id=${currentEditingId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            loadReceptionistsData();
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        } else {
            throw new Error(data.message || 'Failed to delete receptionist');
        }
    })
    .catch(error => {
        console.error('Error deleting receptionist:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        // Reset button state
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        deleteBtn.disabled = false;
    });
}

/* ===================================
   FILTERING AND SEARCH FUNCTIONS
   =================================== */

function filterReceptionists() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const performanceFilter = document.getElementById('performanceFilter').value;
    
    let filteredData = receptionistsData.filter(receptionist => {
        // Search filter
        const matchesSearch = !searchTerm || 
            receptionist.name.toLowerCase().includes(searchTerm) ||
            receptionist.username.toLowerCase().includes(searchTerm);
        
        // Status filter
        const matchesStatus = !statusFilter || 
            (statusFilter === 'active' && receptionist.status === 'active') ||
            (statusFilter === 'inactive' && receptionist.status !== 'active');
        
        // Performance filter
        const guestsProcessed = receptionist.guests_processed || 0;
        const matchesPerformance = !performanceFilter ||
            (performanceFilter === 'high' && guestsProcessed >= 20) ||
            (performanceFilter === 'medium' && guestsProcessed >= 5 && guestsProcessed < 20) ||
            (performanceFilter === 'low' && guestsProcessed < 5);
        
        return matchesSearch && matchesStatus && matchesPerformance;
    });
    
    renderReceptionistsTable(filteredData);
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('performanceFilter').value = '';
    renderReceptionistsTable();
}

/* ===================================
   BULK ACTIONS FUNCTIONS
   =================================== */

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.receptionist-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    if (!bulkBar || !selectedCount) {
        console.error('Bulk action elements not found');
        return;
    }
    
    selectedReceptionists = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    if (selectedReceptionists.length > 0) {
        bulkBar.classList.remove('hide');
        bulkBar.classList.add('show');
        bulkBar.style.display = 'flex';
        selectedCount.textContent = `${selectedReceptionists.length} selected`;
    } else {
        bulkBar.classList.remove('show');
        bulkBar.classList.add('hide');
        setTimeout(() => {
            if (bulkBar.classList.contains('hide')) {
                bulkBar.style.display = 'none';
            }
        }, 300);
    }
    
    // Update select all checkbox state
    if (selectAllCheckbox) {
        const allCheckboxes = document.querySelectorAll('.receptionist-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.receptionist-checkbox:checked');
        
        if (checkedCheckboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedCheckboxes.length === allCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.receptionist-checkbox, #selectAll');
    checkboxes.forEach(cb => cb.checked = false);
    selectedReceptionists = [];
    updateBulkActions();
}

function bulkDelete() {
    if (selectedReceptionists.length === 0) return;
    
    if (!confirm(`Are you sure you want to delete ${selectedReceptionists.length} receptionists? This action cannot be undone.`)) {
        return;
    }
    
    showLoading();
    
    fetch('receptionists_api.php?action=bulk_delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: selectedReceptionists })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            loadReceptionistsData();
            clearSelection();
        } else {
            throw new Error(data.message || 'Failed to delete receptionists');
        }
    })
    .catch(error => {
        console.error('Error in bulk delete:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        hideLoading();
    });
}

function bulkResetPassword() {
    if (selectedReceptionists.length === 0) return;
    
    if (!confirm(`Are you sure you want to reset passwords for ${selectedReceptionists.length} receptionists? They will need to set new passwords on their next login.`)) {
        return;
    }
    
    showLoading();
    
    fetch('receptionists_api.php?action=bulk_reset_password', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ids: selectedReceptionists })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            clearSelection();
        } else {
            throw new Error(data.message || 'Failed to reset passwords');
        }
    })
    .catch(error => {
        console.error('Error in bulk password reset:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        hideLoading();
    });
}

/* ===================================
   UTILITY FUNCTIONS
   =================================== */

function refreshData() {
    loadReceptionistsData();
}

function exportReceptionists() {
    showLoading();
    
    fetch('receptionists_api.php?action=export')
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `receptionists_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        showSuccessAlert('Export completed successfully');
    })
    .catch(error => {
        console.error('Export error:', error);
        showErrorAlert('Failed to export data');
    })
    .finally(() => {
        hideLoading();
    });
}

function togglePassword() {
    const passwordInput = document.getElementById('receptionistPassword');
    const toggleIcon = document.getElementById('passwordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye';
    }
}

function toggleNewPassword() {
    const passwordInput = document.getElementById('newPassword');
    const toggleIcon = document.getElementById('newPasswordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye';
    }
}

function validatePasswordMatch() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const confirmInput = document.getElementById('confirmPassword');
    
    if (confirmPassword && newPassword !== confirmPassword) {
        confirmInput.setCustomValidity('Passwords do not match');
    } else {
        confirmInput.setCustomValidity('');
    }
}

/* ===================================
   HELPER FUNCTIONS
   =================================== */

function getStatusBadgeClass(status) {
    const classes = {
        'active': 'bg-success',
        'inactive': 'bg-secondary',
        'suspended': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusIcon(status) {
    const icons = {
        'active': 'bi-check-circle',
        'inactive': 'bi-pause-circle',
        'suspended': 'bi-x-circle'
    };
    return icons[status] || 'bi-question-circle';
}

function getStatusText(status) {
    const texts = {
        'active': 'Active',
        'inactive': 'Inactive',
        'suspended': 'Suspended'
    };
    return texts[status] || 'Unknown';
}

function getPerformanceBarClass(level) {
    const classes = {
        'High': 'bg-success',
        'Medium': 'bg-warning',
        'Low': 'bg-danger'
    };
    return classes[level] || 'bg-secondary';
}

function getPerformanceBadgeClass(level) {
    const classes = {
        'High': 'bg-success',
        'Medium': 'bg-warning text-dark',
        'Low': 'bg-danger'
    };
    return classes[level] || 'bg-secondary';
}

function getPerformancePercentage(guestsProcessed) {
    const guests = guestsProcessed || 0;
    if (guests >= 20) return 100;
    if (guests >= 10) return 75;
    if (guests >= 5) return 50;
    if (guests > 0) return 25;
    return 10;
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

function animateNumber(element, start, end) {
    const duration = 1000;
    const range = end - start;
    const minTimer = 50;
    const stepTime = Math.abs(Math.floor(duration / range));
    
    const timer = Math.max(stepTime, minTimer);
    const step = range > 0 ? 1 : -1;
    
    let current = start;
    const increment = () => {
        current += step;
        element.textContent = current;
        
        if ((step > 0 && current < end) || (step < 0 && current > end)) {
            setTimeout(increment, timer);
        } else {
            element.textContent = end;
        }
    };
    
    if (start !== end) {
        increment();
    }
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

function showLoading() {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.add('show');
    }
}

function hideLoading() {
    setTimeout(() => {
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
    }, 500);
}

function showErrorInTable(message) {
    const tbody = document.getElementById('receptionistsTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger py-5">
                    <i class="bi bi-exclamation-triangle" style="font-size: 48px; opacity: 0.5;"></i>
                    <p class="mt-2 mb-2 text-danger">${message}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadReceptionistsData()">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                    <br><br>
                    <details class="text-start">
                        <summary class="btn btn-link btn-sm">Show Debug Info</summary>
                        <div class="mt-2 small">
                            <p><strong>Troubleshooting steps:</strong></p>
                            <ol>
                                <li>Check if receptionists_api.php file exists</li>
                                <li>Verify database connection settings</li>
                                <li>Check browser console for errors (F12)</li>
                                <li>Ensure receptionists table has data</li>
                            </ol>
                            <p><strong>API Endpoint:</strong> receptionists_api.php?action=get_all</p>
                        </div>
                    </details>
                </td>
            </tr>
        `;
    }
    
    // Also update the count
    const totalCountElement = document.getElementById('totalCount');
    if (totalCountElement) {
        totalCountElement.textContent = 'Error loading data';
        totalCountElement.className = 'badge bg-danger';
    }
}

function showSuccessAlert(message) {
    // Create and show success alert
    const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show position-fixed" 
             style="top: 90px; right: 20px; z-index: 1055; min-width: 300px; max-width: 400px;">
            <i class="bi bi-check-circle-fill me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

function showErrorAlert(message) {
    // Create and show error alert
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show position-fixed" 
             style="top: 90px; right: 20px; z-index: 1055; min-width: 300px; max-width: 400px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto remove after 7 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert-danger');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 7000);
}

/* ===================================
   SIDEBAR FUNCTIONALITY
   =================================== */

function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
}

/* ===================================
   KEYBOARD SHORTCUTS
   =================================== */

document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + K for search focus
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        document.getElementById('searchInput').focus();
    }
    
    // Ctrl/Cmd + N for new receptionist
    if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
        event.preventDefault();
        showAddReceptionistModal();
    }
    
    // Ctrl/Cmd + R for refresh
    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        refreshData();
    }
    
    // ESC to close modals or clear selection
    if (event.key === 'Escape') {
        if (selectedReceptionists.length > 0) {
            clearSelection();
        }
        
        // Close sidebar on mobile
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        }
    }
});

/* ===================================
   WINDOW RESIZE HANDLER
   =================================== */

window.addEventListener('resize', function() {
    // Auto-close sidebar on desktop view
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('active');
        }
    }
    
    // Resize charts if they exist
    if (performanceChart) {
        performanceChart.resize();
    }
});

/* ===================================
   PAGE VISIBILITY HANDLER
   =================================== */

document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Page became visible, refresh data if it's been more than 5 minutes
        const lastRefresh = localStorage.getItem('receptionists_last_refresh');
        const now = Date.now();
        
        if (!lastRefresh || (now - parseInt(lastRefresh)) > 300000) { // 5 minutes
            refreshData();
        }
    }
});

/* ===================================
   LOCAL STORAGE MANAGEMENT
   =================================== */

function saveToLocalStorage() {
    try {
        localStorage.setItem('receptionists_last_refresh', Date.now().toString());
        localStorage.setItem('receptionists_data_timestamp', Date.now().toString());
    } catch (error) {
        console.warn('Could not save to localStorage:', error);
    }
}

/* ===================================
   PERFORMANCE MONITORING
   =================================== */

// Monitor page load performance
window.addEventListener('load', function() {
    const loadTime = performance.now();
    console.log(`Receptionists page loaded in ${Math.round(loadTime)}ms`);
});

// Monitor API request performance
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const startTime = performance.now();
    return originalFetch.apply(this, args).then(response => {
        const endTime = performance.now();
        if (args[0].includes('receptionists_api.php')) {
            console.log(`API request to ${args[0]} took ${Math.round(endTime - startTime)}ms`);
        }
        return response;
    });
};

/* ===================================
   EXPORT API FOR EXTERNAL USE
   =================================== */

// Make these functions available globally if needed
window.receptionistsAPI = {
    refresh: refreshData,
    loadData: loadReceptionistsData,
    addReceptionist: showAddReceptionistModal,
    exportData: exportReceptionists,
    clearFilters: clearFilters
};

console.log('Receptionists Management JS loaded successfully');