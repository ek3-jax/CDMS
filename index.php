<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cydian Data Management System - Contact Sync Dashboard">
    <title>Cydian Data Management System</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation - matches CAMS layout -->
    <nav class="main-nav">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <img src="images/cydianlogo.png" alt="Cydian Logo">
                <span class="nav-cdms-text">C.D.M.S.</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php" class="active">Contact Sync</a></li>
            </ul>
            <span class="nav-status">
                <span class="nav-status-dot" id="status-dot"></span>
                <span id="status-text">Ready</span>
            </span>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">

        <div class="page-header">
            <h1 class="page-title">Contact Sync: GoHighLevel &rarr; Close CRM</h1>
        </div>

        <!-- Alert Area -->
        <div id="alert-area"></div>

        <!-- Sync Flow Visualization -->
        <div class="sync-flow">
            <div class="sync-flow-node ghl">
                <span class="sync-flow-badge ghl">GoHighLevel</span>
                <span class="sync-flow-count" id="flow-ghl-count">0</span>
                <span class="sync-flow-label">Contacts</span>
            </div>
            <div class="sync-flow-arrow">&rarr;</div>
            <div class="sync-flow-node cdms-engine">
                <span class="sync-flow-badge cdms-engine">CDMS</span>
                <span class="sync-flow-count">Sync</span>
                <span class="sync-flow-label">Engine</span>
            </div>
            <div class="sync-flow-arrow">&rarr;</div>
            <div class="sync-flow-node close">
                <span class="sync-flow-badge close">Close CRM</span>
                <span class="sync-flow-count" id="flow-close-count">0</span>
                <span class="sync-flow-label">Created</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="button-row">
            <button type="button" id="btn-fetch" class="btn-primary btn-lg">
                Fetch GHL Contacts
            </button>
            <button type="button" id="btn-push" class="btn-success btn-lg" disabled>
                Push to Close CRM
            </button>
            <div class="button-row-spacer"></div>
            <div class="search-box">
                <input type="text" id="search-input" placeholder="Filter contacts..." aria-label="Filter contacts">
                <span class="search-box-icon">Search</span>
            </div>
        </div>

        <!-- Stats - matches CAMS stat-card pattern -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Fetched</div>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Created in Close</div>
                <div class="stat-value" id="stat-created">0</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Skipped (Duplicates)</div>
                <div class="stat-value" id="stat-skipped">0</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">Failed</div>
                <div class="stat-value" id="stat-failed">0</div>
            </div>
        </div>

        <!-- Contacts Table -->
        <div class="card hidden" id="contacts-card">
            <div class="section-header">
                <h2 class="section-title">GoHighLevel Contacts</h2>
                <span class="category-badge" style="background: #00bfa5;">GHL</span>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-checkbox">
                                <input type="checkbox" id="cb-select-all" aria-label="Select all contacts">
                            </th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Company</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody id="contacts-tbody">
                        <tr>
                            <td colspan="6" class="empty-state">
                                Click "Fetch GHL Contacts" to load contacts
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sync Results -->
        <div class="card hidden" id="results-card">
            <div class="section-header">
                <h2 class="section-title">Sync Progress</h2>
                <span class="category-badge" style="background: #3d5afe;">Close CRM</span>
            </div>

            <!-- Progress Bar -->
            <div class="progress-bar hidden" id="progress-wrap">
                <div class="progress-fill" id="progress-bar"></div>
            </div>

            <!-- Activity Log -->
            <div class="log-container" id="sync-log" role="log" aria-label="Sync activity log"></div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="site-footer">
        Cydian Data Management System (C.D.M.S.) &copy; <?php echo date('Y'); ?> &mdash; Contact Sync Module
    </footer>

    <script src="js/app.js"></script>
</body>
</html>
