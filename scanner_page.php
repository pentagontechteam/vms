<?php
// scanner_page.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>QR Code Scanner - AATC Visitor Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <!-- QR Scanner Library -->
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

  <style>
    :root {
      --primary-green: #07AF8B;
      --primary-dark: #059669;
      --secondary-blue: #3B82F6;
      --surface-white: #FFFFFF;
      --surface-light: #F8FAFC;
      --surface-card: #FFFFFF;
      --text-primary: #1E293B;
      --text-secondary: #64748B;
      --text-muted: #94A3B8;
      --border-light: #E2E8F0;
      --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.08);
      --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.12);
      --radius-sm: 12px;
      --radius-md: 16px;
      --radius-lg: 24px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      color: var(--text-primary);
      line-height: 1.6;
    }

    .app-container {
      max-width: 900px; /* Increased for desktop side-by-side */
      margin: 0 auto;
      padding: 20px 16px;
      min-height: 100vh;
      position: relative;
    }

    /* Responsive Grid Container */
    .main-content {
      display: grid;
      gap: 24px;
      grid-template-columns: 1fr; /* Mobile: single column */
    }

    /* Desktop: Side-by-side layout */
    @media (min-width: 768px) {
      .app-container {
        padding: 40px 24px;
      }
      
      .main-content {
        grid-template-columns: 1fr 1fr; /* Desktop: two columns */
        gap: 32px;
        align-items: start;
      }
      
      .header-section {
        grid-column: 1 / -1; /* Header spans full width */
        margin-bottom: 40px;
      }
      
      /* Ensure equal height cards on desktop */
      .card {
        height: fit-content;
      }
      
      /* Counter takes priority positioning on desktop */
      .counter-card {
        order: 1;
      }
      
      .scanner-card {
        order: 2;
      }
    }

    /* Large desktop optimization */
    @media (min-width: 1200px) {
      .app-container {
        max-width: 1100px;
        padding: 60px 40px;
      }
      
      .main-content {
        gap: 40px;
      }
    }

    /* Header Section */
    .header-section {
      text-align: center;
      margin-bottom: 32px;
      padding-top: 20px;
    }

    .logo {
      width: 72px;
      height: 72px;
      margin: 0 auto 16px;
      background: var(--surface-white);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: var(--shadow-soft);
    }

    .logo img {
      width: 48px;
      height: 48px;
      object-fit: contain;
    }

    .app-title {
      font-size: 28px;
      font-weight: 800;
      color: var(--primary-green);
      margin: 0 0 8px 0;
      letter-spacing: -0.02em;
    }

    .app-subtitle {
      font-size: 16px;
      color: var(--text-secondary);
      margin: 0;
      font-weight: 500;
    }

    /* Card System */
    .card {
      background: var(--surface-card);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-soft);
      border: 1px solid var(--border-light);
      margin-bottom: 20px;
      overflow: hidden;
    }

    .card-header {
      padding: 20px 20px 0;
      border-bottom: none;
      background: transparent;
    }

    .card-body {
      padding: 20px;
    }

    .card-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-primary);
      margin: 0 0 4px 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .card-subtitle {
      font-size: 14px;
      color: var(--text-muted);
      margin: 0;
    }

    /* Counter Section - Hero element with responsive spacing */
    .counter-card {
      background: linear-gradient(135deg, var(--surface-white) 0%, #FAFBFC 100%);
      border: 2px solid var(--border-light);
      margin-bottom: 0; /* Remove margin, handled by grid gap */
    }

    /* Scanner Section - Responsive height */
    .scanner-card {
      margin-bottom: 0; /* Remove margin, handled by grid gap */
    }

    /* Desktop enhancements */
    @media (min-width: 768px) {
      .counter-card {
        border: 3px solid var(--primary-green);
        box-shadow: 0 8px 32px rgba(7, 175, 139, 0.15);
      }
      
      .count-badge {
        width: 140px;
        height: 140px;
        font-size: 42px;
      }
      
      .card-title {
        font-size: 20px;
      }
      
      .card-subtitle {
        font-size: 15px;
      }
      
      /* Enhanced button sizing for desktop */
      .btn-lg {
        height: 60px;
        font-size: 20px;
      }
      
      .individual-controls {
        gap: 16px;
      }
    }

    /* Scanner Section - Now secondary */
    .scanner-card {
      margin-bottom: 24px;
    }

    .manual-search {
      margin-bottom: 24px;
    }

    .manual-search .form-control {
      height: 52px;
      border-radius: var(--radius-sm);
      border: 2px solid var(--border-light);
      padding: 0 16px;
      font-size: 16px;
      transition: all 0.2s ease;
      background: var(--surface-light);
      margin-bottom: 12px;
    }

    .manual-search .form-control:focus {
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(7, 175, 139, 0.1);
      background: var(--surface-white);
    }

    #reader {
      border-radius: var(--radius-sm);
      overflow: hidden;
      margin-bottom: 16px;
      box-shadow: var(--shadow-soft);
    }

    #result {
      padding: 16px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, #ECFDF5 0%, #F0FDF4 100%);
      color: var(--text-primary);
      font-size: 15px;
      font-weight: 500;
      border: 1px solid #BBF7D0;
      margin-top: 16px;
    }

    .visitor-info {
      background: var(--surface-light);
      border-radius: var(--radius-sm);
      padding: 20px;
      margin-top: 16px;
      border: 1px solid var(--border-light);
    }

    .counter-display {
      text-align: center;
      margin-bottom: 24px;
    }

    .count-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 120px;
      height: 120px;
      background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-dark) 100%);
      color: white;
      border-radius: 50%;
      font-size: 36px;
      font-weight: 800;
      margin-bottom: 12px;
      box-shadow: var(--shadow-medium);
      position: relative;
    }

    .count-badge::before {
      content: '';
      position: absolute;
      inset: -2px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-green), var(--secondary-blue));
      z-index: -1;
      opacity: 0.2;
    }

    .count-label {
      font-size: 16px;
      color: var(--text-secondary);
      font-weight: 600;
      margin: 0;
    }

    .usage-insights {
      background: linear-gradient(135deg, #EFF6FF 0%, #F0F9FF 100%);
      border: 1px solid #BFDBFE;
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      margin-top: 16px;
    }

    .usage-insights small {
      color: var(--secondary-blue);
      font-weight: 600;
      font-size: 14px;
    }

    /* Offline Indicator */
    .offline-indicator {
      background: linear-gradient(135deg, #FEF3C7 0%, #FEF9C3 100%);
      border: 1px solid #F3E665;
      border-radius: var(--radius-sm);
      padding: 12px 16px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 14px;
      font-weight: 600;
      color: #92400E;
    }

    /* Input Controls */
    .bulk-entry {
      margin-bottom: 24px;
    }

    .bulk-entry .input-group {
      display: flex;
      gap: 8px;
      align-items: stretch;
    }

    .bulk-entry input {
      flex: 1;
      height: 48px;
      border-radius: var(--radius-sm);
      border: 2px solid var(--border-light);
      padding: 0 16px;
      font-size: 16px;
      font-weight: 600;
      text-align: center;
      background: var(--surface-light);
      transition: all 0.2s ease;
    }

    .bulk-entry input:focus {
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(7, 175, 139, 0.1);
      background: var(--surface-white);
      outline: none;
    }

    .bulk-entry small {
      display: block;
      text-align: center;
      margin-top: 8px;
      color: var(--text-muted);
      font-size: 13px;
    }

    /* Buttons */
    .btn {
      border: none;
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: 16px;
      transition: all 0.2s ease;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      position: relative;
      overflow: hidden;
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .btn-success, .btn-primary {
      background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-dark) 100%);
      color: white;
      height: 48px;
      box-shadow: 0 4px 12px rgba(7, 175, 139, 0.3);
      border: none;
    }

    .btn-success:hover:not(:disabled), .btn-primary:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(7, 175, 139, 0.4);
    }

    .btn-outline-danger {
      background: var(--surface-white);
      color: #DC2626;
      border: 2px solid #FCA5A5;
      height: 48px;
    }

    .btn-outline-danger:hover:not(:disabled) {
      background: #FEF2F2;
      border-color: #DC2626;
      color: #DC2626;
      transform: translateY(-1px);
    }

    .btn-outline-warning {
      background: var(--surface-white);
      color: #D97706;
      border: 2px solid #FDE68A;
      height: 36px;
      font-size: 14px;
    }

    .btn-lg {
      height: 56px;
      font-size: 18px;
      font-weight: 700;
      border-radius: var(--radius-md);
    }

    .btn-sm {
      height: 36px;
      font-size: 14px;
      padding: 0 16px;
    }

    /* Individual Controls */
    .individual-controls {
      display: flex;
      gap: 12px;
      margin-top: 20px;
    }

    .individual-controls .btn {
      flex: 1;
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 24px;
    }

    /* Footer */
    .footer {
      text-align: center;
      padding: 32px 16px 24px;
      color: var(--text-muted);
      font-size: 14px;
      margin-top: auto;
    }

    /* Responsive adjustments */
    @media (max-width: 767px) {
      .app-container {
        max-width: 420px;
        padding: 20px 16px;
      }
      
      .header-section {
        margin-bottom: 32px;
        padding-top: 20px;
      }
      
      .count-badge {
        width: 120px;
        height: 120px;
        font-size: 36px;
      }
      
      /* Mobile: Counter first for optimal UX */
      .counter-card {
        order: 1;
      }
      
      .scanner-card {
        order: 2;
      }
    }

    @media (max-height: 700px) {
      .app-container {
        padding: 16px 16px;
      }
      
      .header-section {
        margin-bottom: 24px;
        padding-top: 10px;
      }
      
      .count-badge {
        width: 100px;
        height: 100px;
        font-size: 32px;
      }
    }

    /* Bootstrap overrides */
    .alert {
      border: none;
      border-radius: var(--radius-sm);
    }

    .alert-warning {
      background: linear-gradient(135deg, #FEF3C7 0%, #FEF9C3 100%);
      border: 1px solid #F3E665;
      color: #92400E;
    }

    .alert-info {
      background: linear-gradient(135deg, #EFF6FF 0%, #F0F9FF 100%);
      border: 1px solid #BFDBFE;
      color: var(--secondary-blue);
    }
  </style>
</head>
<body>
  <div class="app-container">
    <!-- Header Section -->
    <div class="header-section">
      <div class="logo">
        <img src="assets/logo-green-yellow.png" alt="AATC Logo">
      </div>
      <h1 class="app-title">Gate Check-In Dashboard</h1>
      <p class="app-subtitle">Abuja-AATC Visitor Management System</p>
    </div>

    <!-- Manual Counter Card - NOW FIRST -->
    <div class="card counter-card mb-4">
      <div class="card-header">
        <h2 class="card-title">
          <i class="bi bi-building"></i>
          Premises Entry Counter
        </h2>
        <p class="card-subtitle">Track all people entering the premises (hotel, office, visitors)</p>
      </div>
      <div class="card-body">
        <!-- Offline Status Indicator -->
        <div id="offlineIndicator" class="offline-indicator" style="display: none;">
          <span>
            <i class="bi bi-wifi-off"></i> 
            <span id="offlineText">Offline - 0 pending</span>
          </span>
          <button id="syncBtn" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-arrow-clockwise"></i>
            Sync
          </button>
        </div>

        <!-- Counter Display -->
        <div class="counter-display">
          <div class="count-badge" id="todayCount">0</div>
          <p class="count-label">Total Premises Entries Today</p>
          
          <!-- Usage Analytics -->
          <div class="usage-insights" id="usageInsights" style="display: none;">
            <small>
              <i class="bi bi-graph-up"></i> 
              <span id="insightText">Loading insights...</span>
            </small>
          </div>
          
          <!-- Breakdown Display -->
          <div class="premises-breakdown mt-3" id="premisesBreakdown" style="display: none;">
            <div class="row text-center">
              <div class="col-4">
                <div class="small text-muted">Office Visitors</div>
                <div class="fw-bold text-primary" id="officeCount">0</div>
              </div>
              <div class="col-4">
                <div class="small text-muted">Hotel/Other</div>
                <div class="fw-bold text-secondary" id="hotelOtherCount">0</div>
              </div>
              <div class="col-4">
                <div class="small text-muted">Total</div>
                <div class="fw-bold text-success" id="totalBreakdown">0</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bulk Entry Section -->
        <div class="bulk-entry">
          <div class="input-group">
            <input type="number" id="bulkCount" placeholder="5" min="1" max="50">
            <button id="bulkAddBtn" class="btn btn-primary">
              <i class="bi bi-people-fill"></i>
              Add Multiple
            </button>
          </div>
          <small>For groups entering premises (max 50 at once)</small>
        </div>

        <!-- Individual Counter Buttons -->
        <div class="individual-controls">
          <button id="incrementBtn" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-circle"></i>
            +1 Entry
          </button>
          
          <button id="decrementBtn" class="btn btn-outline-danger">
            <i class="bi bi-dash-circle"></i>
            -1
          </button>
        </div>
      </div>
    </div>

    <!-- Scanner Card - NOW SECOND -->
    <div class="card scanner-card">
      <div class="card-header">
        <h2 class="card-title">
          <i class="bi bi-qr-code-scan"></i>
          QR Code Scanner
        </h2>
        <p class="card-subtitle">Scan visitor QR codes or enter manually</p>
      </div>
      <div class="card-body">
        <div class="manual-search">
          <input type="text" id="manualCode" class="form-control" placeholder="Enter visitor code">
          <button id="manualSearchBtn" class="btn btn-primary w-100">
            <i class="bi bi-search"></i>
            Search Code
          </button>
        </div>

        <div id="reader"></div>
        <div id="result">Waiting for scan...</div>
        <div id="visitorInfo" class="visitor-info" style="display: none;"></div>
        
        <div class="action-buttons">
          <button id="checkinBtn" class="btn btn-primary btn-lg w-100" style="display: none;">
            <i class="bi bi-check-circle"></i>
            Visitor Checked-In
          </button> 
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      &copy; <?php echo date("Y"); ?> Abuja-AATC Visitor Management System
    </div>
  </div>

  <script>
    let currentVisitorData = null;
    let currentCount = 0;
    let isOnline = navigator.onLine;
    let offlineQueue = [];
    let syncInProgress = false;
    let hourlyData = {};
    let dailyStats = { total: 0, average: 0, peak_hour: null };

    // Analytics functions
    function updateHourlyData(count = 1) {
      const currentHour = new Date().getHours();
      const today = new Date().toDateString();
      
      if (!hourlyData[today]) {
        hourlyData[today] = {};
      }
      
      if (!hourlyData[today][currentHour]) {
        hourlyData[today][currentHour] = 0;
      }
      
      hourlyData[today][currentHour] += count;
      
      // Save to localStorage
      localStorage.setItem('visitorHourlyData', JSON.stringify(hourlyData));
      
      updateUsageInsights();
    }

    function updateUsageInsights() {
      const insightDiv = document.getElementById("usageInsights");
      const insightText = document.getElementById("insightText");
      const currentHour = new Date().getHours();
      const today = new Date().toDateString();
      
      // Calculate current hour rate
      const currentHourCount = hourlyData[today]?.[currentHour] || 0;
      
      // Find peak hour for today
      let peakHour = null;
      let peakCount = 0;
      
      if (hourlyData[today]) {
        for (const [hour, count] of Object.entries(hourlyData[today])) {
          if (count > peakCount) {
            peakCount = count;
            peakHour = parseInt(hour);
          }
        }
      }
      
      // Generate insights
      let insight = "";
      
      if (currentHourCount > 0) {
        insight = `Current hour: ${currentHourCount} visitors`;
        
        if (peakHour !== null && peakHour !== currentHour) {
          const peakTime = peakHour < 12 ? `${peakHour}AM` : 
                         peakHour === 12 ? '12PM' : `${peakHour-12}PM`;
          insight += ` | Peak: ${peakTime} (${peakCount})`;
        }
        
        // Rate suggestion
        if (currentHourCount >= 10) {
          insight += " 📈 High traffic";
        } else if (currentHourCount >= 5) {
          insight += " 📊 Moderate traffic";
        }
      } else {
        if (peakHour !== null) {
          const peakTime = peakHour < 12 ? `${peakHour}AM` : 
                         peakHour === 12 ? '12PM' : `${peakHour-12}PM`;
          insight = `Today's peak: ${peakTime} (${peakCount} visitors)`;
        } else {
          insight = "Tracking usage patterns...";
        }
      }
      
      insightText.textContent = insight;
      insightDiv.style.display = insight ? "block" : "none";
    }

    function loadAnalyticsData() {
      const saved = localStorage.getItem('visitorHourlyData');
      if (saved) {
        hourlyData = JSON.parse(saved);
        updateUsageInsights();
      }
    }

    // Offline queue management
    function addToOfflineQueue(action, data = {}) {
      const queueItem = {
        id: Date.now(),
        action: action,
        data: data,
        timestamp: new Date().toISOString()
      };
      
      offlineQueue.push(queueItem);
      updateOfflineIndicator();
      
      // Save to localStorage for persistence
      localStorage.setItem('visitorCounterQueue', JSON.stringify(offlineQueue));
    }

    function updateOfflineIndicator() {
      const indicator = document.getElementById("offlineIndicator");
      const text = document.getElementById("offlineText");
      
      if (!isOnline && offlineQueue.length > 0) {
        indicator.style.display = "flex";
        text.textContent = `Offline - ${offlineQueue.length} pending`;
      } else if (offlineQueue.length > 0 && isOnline) {
        indicator.style.display = "flex";
        indicator.className = "offline-indicator";
        indicator.style.background = "linear-gradient(135deg, #EFF6FF 0%, #F0F9FF 100%)";
        indicator.style.border = "1px solid #BFDBFE";
        indicator.style.color = "#1E40AF";
        text.textContent = `${offlineQueue.length} pending sync`;
      } else {
        indicator.style.display = "none";
      }
    }

    function processOfflineQueue() {
      if (syncInProgress || offlineQueue.length === 0 || !isOnline) return;
      
      syncInProgress = true;
      const syncBtn = document.getElementById("syncBtn");
      syncBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
      syncBtn.disabled = true;
      
      // Process queue items one by one
      processQueueItem(0);
    }

    function processQueueItem(index) {
      if (index >= offlineQueue.length) {
        // All items processed
        offlineQueue = [];
        localStorage.removeItem('visitorCounterQueue');
        syncInProgress = false;
        updateOfflineIndicator();
        loadTodayCount(); // Refresh count
        
        const syncBtn = document.getElementById("syncBtn");
        syncBtn.innerHTML = '<i class="bi bi-check-circle"></i> Synced';
        setTimeout(() => {
          syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Sync';
          syncBtn.disabled = false;
        }, 2000);
        return;
      }
      
      const item = offlineQueue[index];
      let body = `action=${item.action}`;
      
      if (item.action === 'bulk_add') {
        body += `&count=${item.data.count}`;
      }
      
      fetch("manual_counter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: body
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove processed item and continue
          processQueueItem(index + 1);
        } else {
          throw new Error(data.message);
        }
      })
      .catch(error => {
        console.error("Sync error:", error);
        syncInProgress = false;
        const syncBtn = document.getElementById("syncBtn");
        syncBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Retry';
        syncBtn.disabled = false;
      });
    }

    // Network status monitoring
    function handleOnlineStatus() {
      isOnline = navigator.onLine;
      updateOfflineIndicator();
      
      if (isOnline && offlineQueue.length > 0) {
        // Auto-sync when coming back online
        setTimeout(processOfflineQueue, 1000);
      }
    }

    // Load offline queue from localStorage on page load
    function loadOfflineQueue() {
      const saved = localStorage.getItem('visitorCounterQueue');
      if (saved) {
        offlineQueue = JSON.parse(saved);
        updateOfflineIndicator();
      }
    }

    // Manual counter functionality
    function loadTodayCount() {
      fetch("manual_counter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=get_count"
      })
      .then(response => response.json())
      .then(data => {
        console.log("Count response:", data);
        if (data.success) {
          currentCount = data.total_count;
          document.getElementById("todayCount").textContent = currentCount;
          
          // Show breakdown if available
          if (data.office_visitors !== undefined) {
            document.getElementById("officeCount").textContent = data.office_visitors;
            document.getElementById("hotelOtherCount").textContent = data.hotel_other_traffic;
            document.getElementById("totalBreakdown").textContent = data.total_count;
            document.getElementById("premisesBreakdown").style.display = "block";
          }
        } else {
          console.error("Failed to load count:", data.message);
        }
      })
      .catch(error => {
        console.error("Error loading count:", error);
        document.getElementById("todayCount").textContent = "Error";
      });
    }

    // Increment counter (with offline support and analytics)
    function incrementCounter() {
      const btn = document.getElementById("incrementBtn");
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
      
      // Update UI immediately for instant feedback
      currentCount++;
      document.getElementById("todayCount").textContent = currentCount;
      updateHourlyData(1);
      
      if (!isOnline) {
        // Add to offline queue
        addToOfflineQueue('increment');
        
        btn.innerHTML = '<i class="bi bi-wifi-off"></i> Queued';
        setTimeout(() => {
          btn.innerHTML = '<i class="bi bi-plus-circle"></i> +1 Visitor';
          btn.disabled = false;
        }, 1000);
        return;
      }
      
      fetch("manual_counter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=increment"
      })
      .then(response => response.json())
      .then(data => {
        console.log("Increment response:", data);
        if (data.success) {
          // Update with server count (in case of discrepancy)
          currentCount = data.total_count;
          document.getElementById("todayCount").textContent = currentCount;
          
          btn.innerHTML = '<i class="bi bi-check-circle"></i> Added!';
          
          setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-plus-circle"></i> +1 Visitor';
            btn.disabled = false;
          }, 1000);
        } else {
          // Revert optimistic update
          currentCount--;
          document.getElementById("todayCount").textContent = currentCount;
          
          alert("Error: " + data.message);
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-plus-circle"></i> +1 Visitor';
        }
      })
      .catch(error => {
        console.error("Error:", error);
        
        // Add to offline queue on network error
        addToOfflineQueue('increment');
        
        btn.innerHTML = '<i class="bi bi-wifi-off"></i> Queued';
        setTimeout(() => {
          btn.innerHTML = '<i class="bi bi-plus-circle"></i> +1 Visitor';
          btn.disabled = false;
        }, 1000);
      });
    }

    // Bulk add counter (with offline support and analytics)
    function bulkAddVisitors() {
      const bulkInput = document.getElementById("bulkCount");
      const count = parseInt(bulkInput.value);
      
      if (!count || count < 1 || count > 50) {
        alert("Please enter a number between 1 and 50");
        return;
      }
      
      const btn = document.getElementById("bulkAddBtn");
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Adding ${count}...`;
      
      // Update UI immediately for instant feedback
      currentCount += count;
      document.getElementById("todayCount").textContent = currentCount;
      updateHourlyData(count);
      
      if (!isOnline) {
        // Add to offline queue
        addToOfflineQueue('bulk_add', { count: count });
        
        btn.innerHTML = `<i class="bi bi-wifi-off"></i> Queued ${count}`;
        bulkInput.value = "";
        
        setTimeout(() => {
          btn.innerHTML = '<i class="bi bi-people-fill"></i> Add Multiple';
          btn.disabled = false;
        }, 1500);
        return;
      }
      
      fetch("manual_counter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=bulk_add&count=${count}`
      })
      .then(response => response.json())
      .then(data => {
        console.log("Bulk add response:", data);
        if (data.success) {
          // Update with server count
          currentCount = data.total_count;
          document.getElementById("todayCount").textContent = currentCount;
          
          btn.innerHTML = `<i class="bi bi-check-circle"></i> Added ${count}!`;
          bulkInput.value = "";
          
          setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-people-fill"></i> Add Multiple';
            btn.disabled = false;
          }, 2000);
        } else {
          // Revert optimistic update
          currentCount -= count;
          document.getElementById("todayCount").textContent = currentCount;
          
          alert("Error: " + data.message);
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-people-fill"></i> Add Multiple';
        }
      })
      .catch(error => {
        console.error("Error:", error);
        
        // Add to offline queue on network error
        addToOfflineQueue('bulk_add', { count: count });
        
        btn.innerHTML = `<i class="bi bi-wifi-off"></i> Queued ${count}`;
        bulkInput.value = "";
        
        setTimeout(() => {
          btn.innerHTML = '<i class="bi bi-people-fill"></i> Add Multiple';
          btn.disabled = false;
        }, 1500);
      });
    }

    // Decrement counter
    function decrementCounter() {
      if (currentCount <= 0) {
        alert("Count cannot go below zero");
        return;
      }
      
      if (!confirm("Remove 1 from visitor count?")) return;
      
      fetch("manual_counter.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=decrement"
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          currentCount = data.total_count;
          document.getElementById("todayCount").textContent = currentCount;
        }
      })
      .catch(error => console.error("Error:", error));
    }

    // Manual search functionality
    function handleManualSearch() {
      const code = document.getElementById("manualCode").value.trim();

      if (!code) {
        alert("Please enter a valid code.");
        return;
      }

      document.getElementById("result").innerHTML = "Searching...";

      fetch("search_code.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "code=" + encodeURIComponent(code)
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === "FOUND") {
          const visitor = data.visitor;
          currentVisitorData = visitor;
          displayVisitorInfo(visitor);
          document.getElementById("checkinBtn").style.display = "block";
        } else {
          document.getElementById("result").innerHTML = data.message;
          document.getElementById("visitorInfo").style.display = "none";
          document.getElementById("checkinBtn").style.display = "none";
        }
      })
      .catch(err => {
        console.error(err);
        document.getElementById("result").innerHTML = "Search error!";
      });
    }

    // QR Scanner functionality
    function onScanSuccess(decodedText, decodedResult) {
      html5QrcodeScanner.clear().then(() => {
        console.log("QR Scanner stopped");
      }).catch(err => {
        console.error("Failed to stop scanner", err);
      });

      document.getElementById("result").innerHTML = "Processing QR code...";
      
      fetch("verify_qr.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "qr_data=" + encodeURIComponent(decodedText),
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === "FOUND") {
          currentVisitorData = data;
          displayVisitorInfo(data);
          document.getElementById("checkinBtn").style.display = "block";
        } else {
          document.getElementById("result").innerHTML = data.message;
          document.getElementById("visitorInfo").style.display = "none";
          document.getElementById("checkinBtn").style.display = "none";
        }
      })
      .catch(error => {
        console.error("Error:", error);
        document.getElementById("result").innerHTML = "Processing error!";
      });
    }

    function displayVisitorInfo(data) {
      const visitorInfoDiv = document.getElementById("visitorInfo");
      visitorInfoDiv.style.display = "block";
      visitorInfoDiv.innerHTML = `
        <h5>Visitor Information</h5>
        <p><strong>Name:</strong> ${data.visitor_name}</p>
        <p><strong>Company:</strong> ${data.company}</p>
        <p><strong>Host:</strong> ${data.host_name}</p>
        <p><strong>Purpose:</strong> ${data.purpose}</p>
      `;
      document.getElementById("result").innerHTML = "Visitor verified successfully!";
    }

    function checkInVisitor() {
      if (!currentVisitorData) return;
      
      fetch("checkin_visitor.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          visitor_id: currentVisitorData.visitor_id,
          qr_data: currentVisitorData.qr_data
        }),
      })
      .then(response => response.json())
      .then(data => {
        alert(data.message);
        if (data.success) {
          document.getElementById("checkinBtn").style.display = "none";
          loadTodayCount(); // Refresh count after QR check-in
        }
      })
      .catch(error => {
        console.error("Error:", error);
        alert("Already Checked-in");
      });
    }

    // Initialize QR Scanner
    const html5QrcodeScanner = new Html5QrcodeScanner("reader", {
      fps: 10,
      qrbox: 250
    });

    // Event listeners
    document.addEventListener("DOMContentLoaded", function() {
      // Load offline queue, analytics data, and initial count
      loadOfflineQueue();
      loadAnalyticsData();
      loadTodayCount();
      
      // Initialize QR scanner
      html5QrcodeScanner.render(onScanSuccess);
      
      // Network status listeners
      window.addEventListener('online', handleOnlineStatus);
      window.addEventListener('offline', handleOnlineStatus);
      
      // Add event listeners
      document.getElementById("incrementBtn").addEventListener("click", incrementCounter);
      document.getElementById("decrementBtn").addEventListener("click", decrementCounter);
      document.getElementById("bulkAddBtn").addEventListener("click", bulkAddVisitors);
      document.getElementById("syncBtn").addEventListener("click", processOfflineQueue);
      document.getElementById("manualSearchBtn").addEventListener("click", handleManualSearch);
      document.getElementById("checkinBtn").addEventListener("click", checkInVisitor);
      
      // Allow Enter key on bulk input
      document.getElementById("bulkCount").addEventListener("keypress", function(e) {
        if (e.key === "Enter") {
          bulkAddVisitors();
        }
      });
      
      // Auto-sync every 30 seconds when online
      setInterval(() => {
        if (isOnline && offlineQueue.length > 0 && !syncInProgress) {
          processOfflineQueue();
        }
      }, 30000);
      
      // Update insights every 5 minutes
      setInterval(updateUsageInsights, 300000);
    });
  </script>
</body>
</html>