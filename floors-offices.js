function loadFloorsOfficesData() {
    console.log('Loading floors and offices data...');
    showLoading();
    
    // Load floors and offices data
    fetch('floors_offices_api.php?action=get_all')
        .then(response => {
            console.log('API response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
                console.log('Raw response:', text.substring(0, 500));
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            
            if (data && data.success) {
                floorsData = data.floors || [];
                officesData = data.offices || [];
                
                console.log(`Loaded ${floorsData.length} floors and ${officesData.length} offices`);
                
                updateStatistics(data.statistics || {});
                populateFilterDropdowns();
                renderFloorsOfficesTable();
                updateTrafficChart();
                updatePopularLocations();
                loadRecentMaps();
            } else {
                throw new Error(data.message || 'Server returned success=false');
            }
        })
        .catch(error => {
            console.error('Error loading floors & offices:', error);
            showErrorInTable('Failed to load building data: ' + error.message);
        })
        .finally(() => {
            hideLoading();
        });
}

/* ===================================
   STATISTICS UPDATE
   =================================== */

function updateStatistics(stats) {
    const elements = {
        'totalFloors': stats.total_floors || 0,
        'totalOffices': stats.total_offices || 0,
        'totalVisitors': stats.total_visitors || 0,
        'todayVisitors': stats.today_visitors || 0
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            animateNumber(element, parseInt(element.textContent) || 0, elements[id]);
        }
    });
}

/* ===================================
   TABLE RENDERING
   =================================== */

function renderFloorsOfficesTable() {
    const tbody = document.getElementById('floorsOfficesTableBody');
    if (!tbody) return;
    
    let combinedData = [];
    
    // Add floors to combined data
    if (currentViewMode === 'all' || currentViewMode === 'floors') {
        floorsData.forEach(floor => {
            combinedData.push({
                type: 'floor',
                id: floor.id,
                floor_number: floor.floor_number,
                name: floor.floor_name || floor.floor_number,
                department: 'Building Management',
                guests_received: floor.total_visitors || 0,
                is_active: floor.is_active,
                has_map: floor.has_map || false,
                data: floor
            });
        });
    }
    
    // Add offices to combined data
    if (currentViewMode === 'all' || currentViewMode === 'offices') {
        officesData.forEach(office => {
            combinedData.push({
                type: 'office',
                id: office.id,
                floor_number: office.floor_number,
                name: office.office_name,
                department: office.department || 'N/A',
                guests_received: office.visitors_count || 0,
                is_active: office.is_active,
                has_map: false,
                data: office
            });
        });
    }
    
    // Sort by floor number, then by name
    combinedData.sort((a, b) => {
        const floorA = a.floor_number || '';
        const floorB = b.floor_number || '';
        
        if (floorA !== floorB) {
            return floorA.localeCompare(floorB, undefined, { numeric: true });
        }
        return a.name.localeCompare(b.name);
    });
    
    if (combinedData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <i class="bi bi-building" style="font-size: 48px; opacity: 0.3; color: var(--primary);"></i>
                    <h5 class="mt-3 text-muted">No Building Data Found</h5>
                    <p class="text-muted mb-3">Start by adding floors and offices to your building</p>
                    <button class="btn btn-primary me-2" onclick="showAddFloorModal()">
                        <i class="bi bi-plus-circle"></i> Add Floor
                    </button>
                    <button class="btn btn-outline-primary" onclick="showAddOfficeModal()">
                        <i class="bi bi-door-open"></i> Add Office
                    </button>
                </td>
            </tr>
        `;
        updateTotalCount(0);
        return;
    }
    
    tbody.innerHTML = combinedData.map(item => {
        const safeFloorNumber = escapeHtml(item.floor_number || 'N/A');
        const safeName = escapeHtml(item.name || 'Unnamed');
        const safeDepartment = escapeHtml(item.department || 'N/A');
        
        return `
            <tr data-id="${item.id}" data-type="${item.type}">
                <td>
                    <span class="badge ${item.type === 'floor' ? 'bg-primary' : 'bg-secondary'}">
                        <i class="bi ${item.type === 'floor' ? 'bi-building' : 'bi-door-open'}"></i>
                        ${item.type.charAt(0).toUpperCase() + item.type.slice(1)}
                    </span>
                </td>
                <td>
                    <span class="fw-semibold">${safeFloorNumber}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="location-icon me-2">
                            <i class="bi ${item.type === 'floor' ? 'bi-layers' : 'bi-door-closed'} text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">${safeName}</div>
                            ${item.data.capacity ? `<small class="text-muted">Capacity: ${item.data.capacity}</small>` : ''}
                        </div>
                    </div>
                </td>
                <td>${safeDepartment}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="fw-bold me-2">${item.guests_received}</span>
                        <small class="text-muted">guests</small>
                    </div>
                </td>
                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input status-toggle" type="checkbox" 
                               ${item.is_active ? 'checked' : ''} 
                               onchange="toggleStatus('${item.type}', ${item.id}, this.checked)"
                               title="Toggle ${item.type} status">
                        <span class="badge ${item.is_active ? 'bg-success' : 'bg-secondary'} ms-2">
                            ${item.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                </td>
                <td>
                    ${item.type === 'floor' ? `
                        <div class="map-status">
                            ${item.has_map ? 
                                `<span class="badge bg-success"><i class="bi bi-check-circle"></i> Available</span>
                                 <button class="btn btn-sm btn-outline-primary ms-1" onclick="viewFloorMap(${item.id})" title="View Map">
                                    <i class="bi bi-eye"></i>
                                 </button>` :
                                `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> No Map</span>
                                 <button class="btn btn-sm btn-outline-success ms-1" onclick="uploadFloorMap(${item.id})" title="Upload Map">
                                    <i class="bi bi-upload"></i>
                                 </button>`
                            }
                        </div>
                    ` : '<span class="text-muted">N/A</span>'}
                </td>
                <td>
                    <div class="btn-group action-buttons">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="edit${item.type.charAt(0).toUpperCase() + item.type.slice(1)}(${item.id})" 
                                title="Edit ${item.type}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="showDeleteModal('${item.type}', ${item.id}, '${safeName.replace(/'/g, "\\'")}')" 
                                title="Delete ${item.type}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    updateTotalCount(combinedData.length);
}

function updateTotalCount(count) {
    const totalCountElement = document.getElementById('totalCount');
    if (totalCountElement) {
        const label = currentViewMode === 'floors' ? 'floors' : 
                     currentViewMode === 'offices' ? 'offices' : 'locations';
        totalCountElement.textContent = `${count} ${label}`;
    }
}

/* ===================================
   FILTER FUNCTIONS
   =================================== */

function populateFilterDropdowns() {
    // Populate floor filter
    const floorFilter = document.getElementById('floorFilter');
    if (floorFilter) {
        const floorOptions = [...new Set(floorsData.map(f => f.floor_number))].sort((a, b) => 
            a.localeCompare(b, undefined, { numeric: true })
        );
        
        floorFilter.innerHTML = '<option value="">All Floors</option>' +
            floorOptions.map(floor => `<option value="${escapeHtml(floor)}">${escapeHtml(floor)}</option>`).join('');
    }
    
    // Populate department filter
    const departmentFilter = document.getElementById('departmentFilter');
    if (departmentFilter) {
        const departments = [...new Set(officesData.map(o => o.department).filter(d => d))].sort();
        
        departmentFilter.innerHTML = '<option value="">All Departments</option>' +
            departments.map(dept => `<option value="${escapeHtml(dept)}">${escapeHtml(dept)}</option>`).join('');
    }
    
    // Populate office floor dropdown in modal
    const officeFloorSelect = document.getElementById('officeFloor');
    if (officeFloorSelect) {
        officeFloorSelect.innerHTML = '<option value="">Select Floor</option>' +
            floorsData.filter(f => f.is_active).map(floor => 
                `<option value="${floor.id}">${escapeHtml(floor.floor_number)}</option>`
            ).join('');
    }
}

function filterData() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const floorFilter = document.getElementById('floorFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const departmentFilter = document.getElementById('departmentFilter').value;
    
    const allRows = document.querySelectorAll('#floorsOfficesTableBody tr[data-id]');
    let visibleCount = 0;
    
    allRows.forEach(row => {
        const type = row.dataset.type;
        const floorNumber = row.cells[1].textContent.trim();
        const name = row.cells[2].textContent.toLowerCase();
        const department = row.cells[3].textContent.trim();
        const isActive = row.querySelector('.status-toggle').checked;
        
        const matchesSearch = !searchTerm || name.includes(searchTerm) || 
                             floorNumber.toLowerCase().includes(searchTerm) ||
                             department.toLowerCase().includes(searchTerm);
        
        const matchesFloor = !floorFilter || floorNumber === floorFilter;
        const matchesStatus = !statusFilter || 
                             (statusFilter === 'active' && isActive) ||
                             (statusFilter === 'inactive' && !isActive);
        const matchesDepartment = !departmentFilter || department === departmentFilter;
        
        const isVisible = matchesSearch && matchesFloor && matchesStatus && matchesDepartment;
        
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
    });
    
    updateTotalCount(visibleCount);
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('floorFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('departmentFilter').value = '';
    filterData();
}

/* ===================================
   CHART UPDATES
   =================================== */

function updateTrafficChart() {
    if (!trafficChart || floorsData.length === 0) return;
    
    try {
        const chartData = floorsData
            .filter(f => f.is_active)
            .sort((a, b) => (b.total_visitors || 0) - (a.total_visitors || 0))
            .slice(0, 10);
        
        trafficChart.data.labels = chartData.map(f => f.floor_number || 'Unknown');
        trafficChart.data.datasets[0].data = chartData.map(f => f.total_visitors || 0);
        trafficChart.update('none');
    } catch (error) {
        console.error('Error updating traffic chart:', error);
    }
}

function toggleChartView() {
    if (!trafficChart) return;
    
    try {
        const currentType = trafficChart.config.type;
        const newType = currentType === 'bar' ? 'doughnut' : 'bar';
        
        const labels = trafficChart.data.labels;
        const data = trafficChart.data.datasets[0].data;
        
        trafficChart.destroy();
        
        const chartCtx = document.getElementById('trafficChart');
        
        if (newType === 'doughnut') {
            trafficChart = new Chart(chartCtx.getContext('2d'), {
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
                            labels: { padding: 15, usePointStyle: true, font: { size: 11 } }
                        }
                    }
                }
            });
        } else {
            initializeCharts();
            updateTrafficChart();
        }
    } catch (error) {
        console.error('Error toggling chart view:', error);
        initializeCharts();
        updateTrafficChart();
    }
}

function updatePopularLocations() {
    const container = document.getElementById('popularLocations');
    if (!container) return;
    
    try {
        const popularOffices = officesData
            .filter(o => o.is_active && (o.visitors_count || 0) > 0)
            .sort((a, b) => (b.visitors_count || 0) - (a.visitors_count || 0))
            .slice(0, 8);
        
        if (popularOffices.length === 0) {
            container.innerHTML = '<p class="text-muted text-center py-3">No visitor data available yet</p>';
            return;
        }
        
        container.innerHTML = popularOffices.map((office, index) => {
            const visitors = office.visitors_count || 0;
            const percentage = popularOffices.length > 0 ? 
                Math.round((visitors / popularOffices[0].visitors_count) * 100) : 0;
            
            return `
                <div class="popular-location-item mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center">
                            <span class="location-rank me-2">#${index + 1}</span>
                            <div>
                                <div class="fw-semibold">${escapeHtml(office.office_name)}</div>
                                <small class="text-muted">${escapeHtml(office.floor_number)} - ${escapeHtml(office.department || 'N/A')}</small>
                            </div>
                        </div>
                        <span class="badge bg-primary">${visitors}</span>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" style="width: ${percentage}%"></div>
                    </div>
                </div>
            `;
        }).join('');
        
    } catch (error) {
        console.error('Error updating popular locations:', error);
        container.innerHTML = '<p class="text-danger text-center py-3">Error loading location data</p>';
    }
}

/* ===================================
   MODAL FUNCTIONS
   =================================== */

function showAddFloorModal() {
    currentEditingId = null;
    currentEditingType = 'floor';
    document.getElementById('floorModalTitle').innerHTML = '<i class="bi bi-building me-2"></i>Add Floor';
    document.getElementById('floorForm').reset();
    document.getElementById('floorActive').checked = true;
    
    const modal = new bootstrap.Modal(document.getElementById('floorModal'));
    modal.show();
}

function showAddOfficeModal() {
    currentEditingId = null;
    currentEditingType = 'office';
    document.getElementById('officeModalTitle').innerHTML = '<i class="bi bi-door-open me-2"></i>Add Office';
    document.getElementById('officeForm').reset();
    document.getElementById('officeActive').checked = true;
    populateFilterDropdowns(); // Refresh floor options
    
    const modal = new bootstrap.Modal(document.getElementById('officeModal'));
    modal.show();
}

function editFloor(id) {
    const floor = floorsData.find(f => f.id === id);
    if (!floor) return;
    
    currentEditingId = id;
    currentEditingType = 'floor';
    document.getElementById('floorModalTitle').innerHTML = '<i class="bi bi-building me-2"></i>Edit Floor';
    
    document.getElementById('floorNumber').value = floor.floor_number || '';
    document.getElementById('floorName').value = floor.floor_name || '';
    document.getElementById('floorDescription').value = floor.description || '';
    document.getElementById('floorActive').checked = Boolean(floor.is_active);
    
    const modal = new bootstrap.Modal(document.getElementById('floorModal'));
    modal.show();
}

function editOffice(id) {
    const office = officesData.find(o => o.id === id);
    if (!office) return;
    
    currentEditingId = id;
    currentEditingType = 'office';
    document.getElementById('officeModalTitle').innerHTML = '<i class="bi bi-door-open me-2"></i>Edit Office';
    
    populateFilterDropdowns(); // Refresh floor options
    document.getElementById('officeFloor').value = office.floor_id || '';
    document.getElementById('officeName').value = office.office_name || '';
    document.getElementById('officeDepartment').value = office.department || '';
    document.getElementById('officeCapacity').value = office.capacity || '';
    document.getElementById('officeActive').checked = Boolean(office.is_active);
    
    const modal = new bootstrap.Modal(document.getElementById('officeModal'));
    modal.show();
}

/* ===================================
   CRUD OPERATIONS
   =================================== */

function saveFloor() {
    const form = document.getElementById('floorForm');
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveFloorBtn');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    setLoadingState(saveBtn, true);
    
    const url = currentEditingId ? 
        `floors_offices_api.php?action=update_floor&id=${currentEditingId}` : 
        'floors_offices_api.php?action=create_floor';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            loadFloorsOfficesData();
            bootstrap.Modal.getInstance(document.getElementById('floorModal')).hide();
        } else {
            throw new Error(data.message || 'Failed to save floor');
        }
    })
    .catch(error => {
        console.error('Error saving floor:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        setLoadingState(saveBtn, false);
    });
}

function saveOffice() {
    const form = document.getElementById('officeForm');
    const formData = new FormData(form);
    const saveBtn = document.getElementById('saveOfficeBtn');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    setLoadingState(saveBtn, true);
    
    const url = currentEditingId ? 
        `floors_offices_api.php?action=update_office&id=${currentEditingId}` : 
        'floors_offices_api.php?action=create_office';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            loadFloorsOfficesData();
            bootstrap.Modal.getInstance(document.getElementById('officeModal')).hide();
        } else {
            throw new Error(data.message || 'Failed to save office');
        }
    })
    .catch(error => {
        console.error('Error saving office:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        setLoadingState(saveBtn, false);
    });
}

function toggleStatus(type, id, isActive) {
    const formData = new FormData();
    formData.append('is_active', isActive ? '1' : '0');
    
    const endpoint = type === 'floor' ? 'toggle_floor_status' : 'toggle_office_status';
    
    fetch(`floors_offices_api.php?action=${endpoint}&id=${id}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(`${type.charAt(0).toUpperCase() + type.slice(1)} status updated`);
            // Update local data
            if (type === 'floor') {
                const floor = floorsData.find(f => f.id === id);
                if (floor) floor.is_active = isActive;
            } else {
                const office = officesData.find(o => o.id === id);
                if (office) office.is_active = isActive;
            }
        } else {
            throw new Error(data.message || 'Failed to update status');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        showErrorAlert(error.message);
        // Revert toggle
        const toggle = document.querySelector(`tr[data-id="${id}"] .status-toggle`);
        if (toggle) toggle.checked = !isActive;
    });
}

function showDeleteModal(type, id, name) {
    currentEditingId = id;
    currentEditingType = type;
    
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteItemDescription').textContent = 
        `This ${type} and all related data will be permanently removed.`;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function confirmDelete() {
    if (!currentEditingId || !currentEditingType) return;
    
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    setLoadingState(deleteBtn, true);
    
    const endpoint = currentEditingType === 'floor' ? 'delete_floor' : 'delete_office';
    
    fetch(`floors_offices_api.php?action=${endpoint}&id=${currentEditingId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(data.message);
            loadFloorsOfficesData();
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        } else {
            throw new Error(data.message || 'Failed to delete item');
        }
    })
    .catch(error => {
        console.error('Error deleting item:', error);
        showErrorAlert(error.message);
    })
    .finally(() => {
        setLoadingState(deleteBtn, false);
    });
}

/* ===================================
   FILE UPLOAD FUNCTIONS
   =================================== */

function handleFileUpload(files) {
    const validFiles = Array.from(files).filter(file => {
        const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!validTypes.includes(file.type)) {
            showErrorAlert(`Invalid file type: ${file.name}. Please use JPG, PNG, or PDF.`);
            return false;
        }
        
        if (file.size > maxSize) {
            showErrorAlert(`File too large: ${file.name}. Maximum size is 10MB.`);
            return false;
        }
        
        return true;
    });
    
    if (validFiles.length === 0) return;
    
    // Show upload progress
    const uploadArea = document.getElementById('mapUploadArea');
    const originalContent = uploadArea.innerHTML;
    
    uploadArea.innerHTML = `
        <div class="upload-progress">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Uploading ${validFiles.length} file(s)...</p>
            <div class="progress mt-2">
                <div class="progress-bar" id="uploadProgressBar" style="width: 0%"></div>
            </div>
        </div>
    `;
    
    const formData = new FormData();
    validFiles.forEach((file, index) => {
        formData.append(`maps[]`, file);
    });
    
    fetch('floors_offices_api.php?action=upload_maps', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert(`Successfully uploaded ${data.uploaded_count} map(s)`);
            loadRecentMaps();
            loadFloorsOfficesData(); // Refresh to update map status
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showErrorAlert('Upload failed: ' + error.message);
    })
    .finally(() => {
        uploadArea.innerHTML = originalContent;
        setupFileUpload(); // Re-setup event listeners
    });
}

function loadRecentMaps() {
    fetch('floors_offices_api.php?action=get_recent_maps')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('recentMaps');
            if (!container) return;
            
            if (data.success && data.maps && data.maps.length > 0) {
                container.innerHTML = data.maps.map(map => `
                    <div class="recent-map-item mb-2">
                        <div class="d-flex align-items-center">
                            <i class="bi ${getFileIcon(map.file_type)} text-primary me-2"></i>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-semibold text-truncate">${escapeHtml(map.original_name)}</div>
                                <small class="text-muted">${escapeHtml(map.floor_number || 'Unassigned')} • ${formatFileSize(map.file_size)}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewMap(${map.id})" title="View Map">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted text-center py-2">No recent uploads</p>';
            }
        })
        .catch(error => {
            console.error('Error loading recent maps:', error);
            const container = document.getElementById('recentMaps');
            if (container) {
                container.innerHTML = '<p class="text-danger text-center py-2">Error loading maps</p>';
            }
        });
}

function viewAllMaps() {
    fetch('floors_offices_api.php?action=get_all_maps')
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('mapViewerModal');
            const content = document.getElementById('mapViewerContent');
            
            if (data.success && data.maps) {
                content.innerHTML = `
                    <div class="row g-3">
                        ${data.maps.map(map => `
                            <div class="col-md-6 col-lg-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi ${getFileIcon(map.file_type)} text-primary me-2"></i>
                                            <div class="flex-grow-1 min-width-0">
                                                <h6 class="card-title mb-0 text-truncate">${escapeHtml(map.original_name)}</h6>
                                                <small class="text-muted">${escapeHtml(map.floor_number || 'Unassigned')}</small>
                                            </div>
                                        </div>
                                        <p class="card-text small text-muted">
                                            Size: ${formatFileSize(map.file_size)}<br>
                                            Uploaded: ${formatDate(map.uploaded_at)}
                                        </p>
                                        <div class="btn-group w-100">
                                            <button class="btn btn-sm btn-primary" onclick="viewMap(${map.id})">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMap(${map.id})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                content.innerHTML = '<p class="text-muted text-center py-4">No maps uploaded yet</p>';
            }
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        })
        .catch(error => {
            console.error('Error loading maps:', error);
            showErrorAlert('Failed to load maps');
        });
}

function viewMap(mapId) {
    window.open(`floors_offices_api.php?action=view_map&id=${mapId}`, '_blank');
}

function deleteMap(mapId) {
    if (!confirm('Are you sure you want to delete this map?')) return;
    
    fetch(`floors_offices_api.php?action=delete_map&id=${mapId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessAlert('Map deleted successfully');
            viewAllMaps(); // Refresh the modal
            loadRecentMaps(); // Refresh recent maps
            loadFloorsOfficesData(); // Refresh main data
        } else {
            throw new Error(data.message || 'Failed to delete map');
        }
    })
    .catch(error => {
        console.error('Error deleting map:', error);
        showErrorAlert(error.message);
    });
}

/* ===================================
   VIEW MODE FUNCTIONS
   =================================== */

function setViewMode(mode) {
    currentViewMode = mode;
    renderFloorsOfficesTable();
    
    // Update button states
    document.querySelectorAll('.dropdown-item').forEach(item => {
        item.classList.remove('active');
    });
    
    event.target.classList.add('active');
}

/* ===================================
   UTILITY FUNCTIONS
   =================================== */

function refreshData() {
    loadFloorsOfficesData();
}

function exportData() {
    showLoading();
    
    fetch('floors_offices_api.php?action=export')
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
            a.download = `floors_offices_${new Date().toISOString().split('T')[0]}.csv`;
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

function getFileIcon(fileType) {
    switch (fileType) {
        case 'application/pdf':
            return 'bi-file-earmark-pdf';
        case 'image/jpeg':
        case 'image/jpg':
        case 'image/png':
            return 'bi-file-earmark-image';
        default:
            return 'bi-file-earmark';
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function setLoadingState(button, isLoading) {
    const spinner = button.querySelector('.spinner-border');
    const icon = button.querySelector('i:not(.spinner-border)');
    
    if (isLoading) {
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        button.disabled = true;
    } else {
        if (spinner) spinner.classList.add('d-none');
        if (icon) icon.classList.remove('d-none');
        button.disabled = false;
    }
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

function showSuccessAlert(message) {
    const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show position-fixed" 
             style="top: 90px; right: 20px; z-index: 1055; min-width: 300px; max-width: 400px;">
            <i class="bi bi-check-circle-fill me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

function showErrorAlert(message) {
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show position-fixed" 
             style="top: 90px; right: 20px; z-index: 1055; min-width: 300px; max-width: 400px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.querySelector('.alert-danger');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 7000);
}

function showErrorInTable(message) {
    const tbody = document.getElementById('floorsOfficesTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger py-5">
                    <i class="bi bi-exclamation-triangle" style="font-size: 48px; opacity: 0.5;"></i>
                    <p class="mt-2 mb-2 text-danger">${message}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadFloorsOfficesData()">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </td>
            </tr>
        `;
    }
    
    const totalCountElement = document.getElementById('totalCount');
    if (totalCountElement) {
        totalCountElement.textContent = 'Error loading data';
        totalCountElement.className = 'badge bg-danger';
    }
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
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        document.getElementById('searchInput').focus();
    }
    
    if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
        event.preventDefault();
        showAddFloorModal();
    }
    
    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        refreshData();
    }
    
    if (event.key === 'Escape') {
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
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('active');
        }
    }
    
    if (trafficChart) {
        trafficChart.resize();
    }
});

/* ===================================
   EXPORT API FOR EXTERNAL USE
   =================================== */

window.floorsOfficesAPI = {
    refresh: refreshData,
    loadData: loadFloorsOfficesData,
    addFloor: showAddFloorModal,
    addOffice: showAddOfficeModal,
    exportData: exportData,
    clearFilters: clearFilters,
    viewMaps: viewAllMaps
};

console.log('Floors & Offices Management JS loaded successfully');/* ===================================
   FLOORS & OFFICES MANAGEMENT - JS
   File: assets/floors-offices.js
   =================================== */

// Global variables
let floorsData = [];
let officesData = [];
let trafficChart = null;
let currentEditingId = null;
let currentEditingType = null; // 'floor' or 'office'
let currentViewMode = 'all'; // 'floors', 'offices', 'all'

/* ===================================
   INITIALIZATION
   =================================== */

document.addEventListener('DOMContentLoaded', function() {
    try {
        initializeCharts();
        loadFloorsOfficesData();
        setupEventListeners();
        initializeSidebar();
        setupFileUpload();
        
        console.log('Floors & Offices Management initialized successfully');
    } catch (error) {
        console.error('Error initializing floors & offices management:', error);
        showErrorAlert('Failed to initialize the page. Please refresh and try again.');
    }
});

/* ===================================
   CHART INITIALIZATION
   =================================== */

function initializeCharts() {
    const chartCtx = document.getElementById('trafficChart');
    if (chartCtx) {
        try {
            trafficChart = new Chart(chartCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Guests Received',
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
                                    return context[0].label || 'Floor';
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
                                font: { size: 11 },
                                stepSize: 1
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11 },
                                maxRotation: 45,
                                callback: function(value, index, values) {
                                    const label = this.getLabelForValue(value);
                                    return label.length > 12 ? label.substring(0, 12) + '...' : label;
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
            console.error('Error initializing traffic chart:', error);
        }
    }
}

/* ===================================
   EVENT LISTENERS SETUP
   =================================== */

function setupEventListeners() {
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', debounce(filterData, 300));
    
    // Filter dropdowns
    document.getElementById('floorFilter').addEventListener('change', filterData);
    document.getElementById('statusFilter').addEventListener('change', filterData);
    document.getElementById('departmentFilter').addEventListener('change', filterData);
    
    // Form submissions
    document.getElementById('floorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveFloor();
    });
    
    document.getElementById('officeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveOffice();
    });
}

function setupFileUpload() {
    const uploadArea = document.getElementById('mapUploadArea');
    const fileInput = document.getElementById('mapFileInput');
    
    if (!uploadArea || !fileInput) return;
    
    // Drag and drop functionality
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files);
        }
    });
    
    // Click to upload
    uploadArea.addEventListener('click', function(e) {
        if (e.target === uploadArea || e.target.closest('.upload-placeholder')) {
            fileInput.click();
        }
    });
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileUpload(e.target.files);
        }
    });
}

/* ===================================
   DATA LOADING FUNCTIONS
   =================================== */

function loadFloorsOfficesData() {
    console.log('Loading floors and offices data...');
    showLoading();
    
    // Load floors and offices data
    fetch('floors_offices_api.php?action=get_all')
        .then(response => {
            console.log('API response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status