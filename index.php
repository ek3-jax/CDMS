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
                <li><a href="#activity-sync-section">Activity Sync</a></li>
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
                <span class="category-badge badge-ghl">GHL</span>
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
                <span class="category-badge badge-close">Close CRM</span>
            </div>

            <!-- Progress Bar -->
            <div class="progress-bar hidden" id="progress-wrap">
                <div class="progress-fill" id="progress-bar"></div>
            </div>

            <!-- Activity Log -->
            <div class="log-container" id="sync-log" role="log" aria-label="Sync activity log"></div>
        </div>

        <!-- ========== Activity Sync Section ========== -->
        <div id="activity-sync-section" style="margin-top: 3rem;">
            <div class="page-header">
                <h1 class="page-title">Activity Sync: Close CRM &rarr; GoHighLevel</h1>
            </div>

            <!-- Alert Area for Activity Sync -->
            <div id="activity-alert-area"></div>

            <!-- Activity Sync Flow -->
            <div class="sync-flow">
                <div class="sync-flow-node close">
                    <span class="sync-flow-badge close">Close CRM</span>
                    <span class="sync-flow-count" id="flow-act-close-count">0</span>
                    <span class="sync-flow-label">Activities</span>
                </div>
                <div class="sync-flow-arrow">&rarr;</div>
                <div class="sync-flow-node cdms-engine">
                    <span class="sync-flow-badge cdms-engine">CDMS</span>
                    <span class="sync-flow-count">Sync</span>
                    <span class="sync-flow-label">Engine</span>
                </div>
                <div class="sync-flow-arrow">&rarr;</div>
                <div class="sync-flow-node ghl">
                    <span class="sync-flow-badge ghl">GoHighLevel</span>
                    <span class="sync-flow-count" id="flow-act-ghl-count">0</span>
                    <span class="sync-flow-label">Notes Created</span>
                </div>
            </div>

            <!-- Activity Controls -->
            <div class="button-row">
                <select id="activity-type-select" class="btn-primary" style="padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid #ccc;">
                    <option value="">All Activities</option>
                    <option value="note">Notes</option>
                    <option value="call">Calls</option>
                    <option value="email">Emails</option>
                    <option value="meeting">Meetings</option>
                </select>
                <input type="date" id="activity-date-after" class="btn-primary" style="padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid #ccc;" title="Only activities after this date">
                <button type="button" id="btn-fetch-activities" class="btn-primary btn-lg">
                    Fetch Close Activities
                </button>
                <button type="button" id="btn-sync-activities" class="btn-success btn-lg" disabled>
                    Sync to GHL as Notes
                </button>
            </div>

            <!-- Activity Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-label">Activities Fetched</div>
                    <div class="stat-value" id="stat-act-total">0</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Synced to GHL</div>
                    <div class="stat-value" id="stat-act-synced">0</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">No Match</div>
                    <div class="stat-value" id="stat-act-nomatch">0</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">Failed</div>
                    <div class="stat-value" id="stat-act-failed">0</div>
                </div>
            </div>

            <!-- Activities Table -->
            <div class="card hidden" id="activities-card">
                <div class="section-header">
                    <h2 class="section-title">Close CRM Activities</h2>
                    <span class="category-badge badge-close">Close</span>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="col-checkbox">
                                    <input type="checkbox" id="cb-select-all-act" aria-label="Select all activities">
                                </th>
                                <th>Type</th>
                                <th>Email</th>
                                <th>Summary</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="activities-tbody">
                            <tr>
                                <td colspan="5" class="empty-state">
                                    Click "Fetch Close Activities" to load activities
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Sync Results -->
            <div class="card hidden" id="activity-results-card">
                <div class="section-header">
                    <h2 class="section-title">Activity Sync Progress</h2>
                    <span class="category-badge badge-ghl">GHL</span>
                </div>
                <div class="progress-bar hidden" id="act-progress-wrap">
                    <div class="progress-fill" id="act-progress-bar"></div>
                </div>
                <div class="log-container" id="activity-sync-log" role="log" aria-label="Activity sync log"></div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="site-footer">
        Cydian Data Management System (C.D.M.S.) &copy; <?php echo date('Y'); ?> &mdash; Contact Sync Module
    </footer>

    <script src="js/app.js"></script>
</body>
</html>
