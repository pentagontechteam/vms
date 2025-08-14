/* ===================================
   ADMIN DASHBOARD - EXTERNAL JS
   File: assets/admin-dashboard.js
   =================================== */

// Global variables
let statusChart, trafficChart;
let refreshInterval = 30000; // 30 seconds
let refreshTimer;

/* ===================================
   INITIALIZATION
   =================================== */

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    loadDashboardData();
    startAutoRefresh();
    initializeSidebar();
    
    console.log('Admin Dashboard initialized successfully');
});

/* ===================================
   CHART INITIALIZATION
   =================================== */

function initializeCharts() {
    // Status Donut Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        statusChart = new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Checked In', 'Checked Out', 'Rejected'],
                datasets: [{
                    data: [0, 0, 0, 0, 0],
                    backgroundColor: [
                        '#07AF8B',
                        '#FFCA00',
                        '#3B82F6',
                        '#6B7280',
                        '#EF4444'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed;
                                return label;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });
    }

    // Traffic Line Chart
    const trafficCtx = document.getElementById('trafficChart');
    if (trafficCtx) {
        trafficChart = new Chart(trafficCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [{
                    label: 'Visitors',
                    data: new Array(24).fill(0),
                    borderColor: '#07AF8B',
                    backgroundColor: 'rgba(7, 175, 139, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#07AF8B',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function(context) {
                                return `Time: ${context[0].label}`;
                            },
                            label: function(context) {
                                return `Visitors: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
}

/* ===================================
   DATA LOADING FUNCTIONS
   =================================== */

function loadDashboardData() {
    showRefreshIndicator();
    
    // Load main stats
    fetch('?ajax=dashboard_stats')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            updateStats(data);
            updateCharts(data);
        })
        .catch(error => {
            console.error('Error loading stats:', error);
            showErrorMessage('Failed to load dashboard statistics');
        });

    // Load upcoming visitors
    fetch('?ajax=upcoming_visitors')
        .then(response => response.json())
        .then(data => updateUpcomingVisitors(data))
        .catch(error => {
            console.error('Error loading upcoming visitors:', error);
            showErrorInWidget('upcomingVisitors', 'Failed to load upcoming visitors');
        });

    // Load live visitors
    fetch('?ajax=live_visitors')
        .then(response => response.json())
        .then(data => updateLiveVisitors(data))
        .catch(error => {
            console.error('Error loading live visitors:', error);
            showErrorInWidget('liveVisitors', 'Failed to load live visitors');
        });

    // Load activity feed
    fetch('?ajax=activity_feed')
        .then(response => response.json())
        .then(data => updateActivityFeed(data))
        .catch(error => {
            console.error('Error loading activity:', error);
            showErrorInWidget('activityFeed', 'Failed to load activity feed');
        });

    // Load visitor locations
    fetch('?ajax=visitor_locations')
        .then(response => response.json())
        .then(data => updateVisitorLocations(data))
        .catch(error => {
            console.error('Error loading locations:', error);
            showErrorInWidget('visitorLocations', 'Failed to load visitor locations');
        });

    hideRefreshIndicator();
}

/* ===================================
   UPDATE FUNCTIONS
   =================================== */

function updateStats(data) {
    const elements = {
        'visitorsToday': data.visitors_today,
        'approvedVisitors': data.approved_visitors,
        'pendingRequests': data.pending_requests,
        'checkedOut': data.checked_out,
        'currentlyInside': data.currently_inside
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            animateNumber(element, parseInt(element.textContent) || 0, elements[id]);
        }
    });
    
    // Update notification count
    const notificationCount = document.getElementById('notificationCount');
    if (notificationCount) {
        notificationCount.textContent = data.pending_requests;
        notificationCount.style.display = data.pending_requests > 0 ? 'block' : 'none';
    }
}

function updateCharts(data) {
    // Update status chart
    if (data.status_breakdown && statusChart) {
        const statusLabels = [];
        const statusData = [];
        const colors = ['#07AF8B', '#FFCA00', '#3B82F6', '#6B7280', '#EF4444'];
        
        data.status_breakdown.forEach((item, index) => {
            statusLabels.push(capitalizeFirst(item.status));
            statusData.push(item.count);
        });
        
        statusChart.data.labels = statusLabels;
        statusChart.data.datasets[0].data = statusData;
        statusChart.data.datasets[0].backgroundColor = colors.slice(0, statusLabels.length);
        statusChart.update('active');
    }

    // Update traffic chart
    if (data.hourly_traffic && trafficChart) {
        trafficChart.data.datasets[0].data = data.hourly_traffic;
        trafficChart.update('active');
    }
}

function updateUpcomingVisitors(visitors) {
    const container = document.getElementById('upcomingVisitors');
    if (!container) return;
    
    if (visitors.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-calendar-x" style="font-size: 48px; opacity: 0.5;"></i>
                <p class="mt-2 mb-0">No upcoming visitors</p>
            </div>
        `;
        return;
    }

    let html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-hover">';
    html += '<thead><tr><th>Name</th><th>Organization</th><th>Time</th><th>Host</th></tr></thead>';
    html += '<tbody>';
    
    visitors.forEach(visitor => {
        const timeStr = visitor.time_of_visit ? 
            new Date('2000-01-01 ' + visitor.time_of_visit).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 
            'TBD';
        
        html += `<tr>
            <td><strong>${escapeHtml(visitor.name)}</strong></td>
            <td>${escapeHtml(visitor.organization || 'N/A')}</td>
            <td><span class="badge bg-info">${timeStr}</span></td>
            <td>${escapeHtml(visitor.host_name || 'N/A')}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function updateLiveVisitors(visitors) {
    const container = document.getElementById('liveVisitors');
    if (!container) return;
    
    if (visitors.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-people" style="font-size: 48px; opacity: 0.5;"></i>
                <p class="mt-2 mb-0">No active visitors</p>
            </div>
        `;
        return;
    }

    let html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-hover">';
    html += '<thead><tr><th>Name</th><th>Status</th><th>Location</th><th>Time</th></tr></thead>';
    html += '<tbody>';
    
    visitors.forEach(visitor => {
        const statusBadge = visitor.status === 'checked_in' ? 
            '<span class="badge bg-success">Checked In</span>' : 
            '<span class="badge bg-warning">Approved</span>';
        
        const timeStr = visitor.check_in_time ? 
            new Date(visitor.check_in_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 
            'Not checked in';
        
        html += `<tr>
            <td><strong>${escapeHtml(visitor.name)}</strong></td>
            <td>${statusBadge}</td>
            <td>${escapeHtml(visitor.floor_of_visit || 'N/A')}</td>
            <td>${timeStr}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function updateActivityFeed(activities) {
    const container = document.getElementById('activityFeed');
    if (!container) return;
    
    if (activities.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-activity" style="font-size: 48px; opacity: 0.5;"></i>
                <p class="mt-2 mb-0">No recent activity</p>
            </div>
        `;
        return;
    }

    let html = '';
    activities.slice(0, 10).forEach(activity => {
        const timeAgo = getTimeAgo(new Date(activity.updated_at));
        const icon = getActivityIcon(activity.status);
        const statusText = getStatusText(activity.status);
        
        html += `<div class="activity-item">
            <div class="activity-icon">
                <i class="bi bi-${icon}"></i>
            </div>
            <div class="activity-content">
                <div class="activity-text">
                    <strong>${escapeHtml(activity.name)}</strong> from ${escapeHtml(activity.organization || 'Unknown')} was ${statusText}
                </div>
                <div class="activity-time">${timeAgo}</div>
            </div>
        </div>`;
    });
    
    container.innerHTML = html;
}

function updateVisitorLocations(locations) {
    const container = document.getElementById('visitorLocations');
    if (!container) return;
    
    // Group visitors by floor
    const floorGroups = {};
    locations.forEach(visitor => {
        const floor = visitor.floor_of_visit || 'Unknown';
        if (!floorGroups[floor]) {
            floorGroups[floor] = [];
        }
        floorGroups[floor].push(visitor);
    });

    if (Object.keys(floorGroups).length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-geo-alt" style="font-size: 48px; opacity: 0.5;"></i>
                <p class="mt-2 mb-0">No visitors currently inside</p>
            </div>
        `;
        return;
    }

    let html = '<div class="location-grid">';
    
    // Common floors
    const commonFloors = [
        'Ground Floor', 'Mezzanine', 'Floor 1', 'Floor 2', 'Floor 3', 
        'Floor 4', 'Floor 5', 'Floor 6', 'Floor 7', 'Floor 8', 'Floor 9'
    ];
    
    commonFloors.forEach(floor => {
        const count = floorGroups[floor] ? floorGroups[floor].length : 0;
        const isOccupied = count > 0 ? 'occupied' : '';
        
        html += `<div class="location-item ${isOccupied}" title="${count} visitor(s) on ${floor}">
            <div class="location-name">${floor}</div>
            <div class="location-count">${count}</div>
        </div>`;
    });
    
    // Add any additional floors not in common list
    Object.keys(floorGroups).forEach(floor => {
        if (!commonFloors.includes(floor)) {
            const count = floorGroups[floor].length;
            html += `<div class="location-item occupied" title="${count} visitor(s) on ${floor}">
                <div class="location-name">${floor}</div>
                <div class="location-count">${count}</div>
            </div>`;
        }
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/* ===================================
   HELPER FUNCTIONS
   =================================== */

function getActivityIcon(status) {
    const icons = {
        'approved': 'check-circle',
        'rejected': 'x-circle',
        'checked_in': 'box-arrow-in-right',
        'checked_out': 'box-arrow-right',
        'pending': 'clock',
        'denied': 'x-circle'
    };
    return icons[status] || 'person';
}

function getStatusText(status) {
    const statusTexts = {
        'approved': 'approved',
        'rejected': 'rejected',
        'checked_in': 'checked in',
        'checked_out': 'checked out',
        'pending': 'submitted for approval',
        'denied': 'denied'
    };
    return statusTexts[status] || 'updated';
}

function getTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays}d ago`;
}

function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
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
    const duration = 1000; // 1 second
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

/* ===================================
   AUTO-REFRESH FUNCTIONALITY
   =================================== */

function startAutoRefresh() {
    refreshTimer = setInterval(loadDashboardData, refreshInterval);
}

function stopAutoRefresh() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
}

function showRefreshIndicator() {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        indicator.classList.add('show');
    }
}

function hideRefreshIndicator() {
    setTimeout(() => {
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
    }, 1000);
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
   ERROR HANDLING
   =================================== */

function showErrorMessage(message) {
    console.error(message);
    // You can implement a toast notification system here
}

function showErrorInWidget(widgetId, message) {
    const widget = document.getElementById(widgetId);
    if (widget) {
        widget.innerHTML = `
            <div class="text-center text-danger py-4">
                <i class="bi bi-exclamation-triangle" style="font-size: 48px; opacity: 0.5;"></i>
                <p class="mt-2 mb-0">${message}</p>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadDashboardData()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    }
}

/* ===================================
   UTILITY FUNCTIONS
   =================================== */

function printBadge() {
    // You can customize this function based on your print badge requirements
    const printWindow = window.open('print_badge.php', '_blank', 'width=400,height=600');
    if (!printWindow) {
        alert('Please allow popups to print visitor badges');
    }
}

function manualRefresh() {
    stopAutoRefresh();
    loadDashboardData();
    startAutoRefresh();
}

/* ===================================
   EVENT LISTENERS
   =================================== */

// Handle visibility change to pause/resume refresh
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
        console.log('Dashboard auto-refresh paused (page hidden)');
    } else {
        startAutoRefresh();
        loadDashboardData(); // Refresh immediately when page becomes visible
        console.log('Dashboard auto-refresh resumed (page visible)');
    }
});

// Handle page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// Handle network status changes
window.addEventListener('online', function() {
    console.log('Network connection restored');
    loadDashboardData();
    startAutoRefresh();
    showNetworkStatus('online');
});

window.addEventListener('offline', function() {
    console.log('Network connection lost');
    stopAutoRefresh();
    showNetworkStatus('offline');
});

function showNetworkStatus(status) {
    const indicator = document.getElementById('refreshIndicator');
    if (indicator) {
        if (status === 'offline') {
            indicator.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
            indicator.classList.add('show');
            indicator.style.background = '#dc3545';
        } else {
            indicator.innerHTML = '<i class="bi bi-wifi"></i> Online';
            indicator.style.background = '#28a745';
            setTimeout(() => {
                indicator.classList.remove('show');
                indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refreshing...';
                indicator.style.background = '#07AF8B';
            }, 2000);
        }
    }
}

/* ===================================
   KEYBOARD SHORTCUTS
   =================================== */

document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + R for manual refresh
    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        manualRefresh();
    }
    
    // ESC to close sidebar on mobile
    if (event.key === 'Escape' && window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('active');
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
    if (statusChart) {
        statusChart.resize();
    }
    if (trafficChart) {
        trafficChart.resize();
    }
});

/* ===================================
   PERFORMANCE MONITORING
   =================================== */

// Monitor page load performance
window.addEventListener('load', function() {
    const loadTime = performance.now();
    console.log(`Dashboard loaded in ${Math.round(loadTime)}ms`);
});

// Monitor AJAX request performance
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const startTime = performance.now();
    return originalFetch.apply(this, args).then(response => {
        const endTime = performance.now();
        console.log(`API request to ${args[0]} took ${Math.round(endTime - startTime)}ms`);
        return response;
    });
};

/* ===================================
   EXPORT FUNCTIONS (for external use)
   =================================== */

// Make these functions available globally if needed
window.dashboardAPI = {
    refresh: manualRefresh,
    loadData: loadDashboardData,
    printBadge: printBadge,
    startAutoRefresh: startAutoRefresh,
    stopAutoRefresh: stopAutoRefresh
};

console.log('Admin Dashboard JS loaded successfully');