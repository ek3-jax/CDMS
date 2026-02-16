<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cydian Data Management System - Contact Sync Dashboard">
    <title>CDMS - Cydian Data Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="cdms-wrapper">

        <!-- Header -->
        <header class="cdms-header">
            <a href="index.php" class="cdms-header__brand">
                <div class="cdms-header__logo">CD</div>
                <div>
                    <div class="cdms-header__title">CDMS</div>
                    <div class="cdms-header__subtitle">Cydian Data Management System</div>
                </div>
            </a>
            <nav class="cdms-header__nav">
                <span class="cdms-header__status">
                    <span class="cdms-header__dot" id="status-dot"></span>
                    <span id="status-text">Ready</span>
                </span>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="cdms-main">

            <h1 class="cdms-section-title">Contact Sync: GoHighLevel &rarr; Close CRM</h1>

            <!-- Alert Area -->
            <div id="alert-area"></div>

            <!-- Sync Flow Visualization -->
            <div class="cdms-flow">
                <div class="cdms-flow__node cdms-flow__node--ghl">
                    <span class="cdms-card__badge cdms-card__badge--ghl">GoHighLevel</span>
                    <span class="cdms-flow__count" id="flow-ghl-count">0</span>
                    <span class="cdms-flow__label">Contacts</span>
                </div>
                <div class="cdms-flow__arrow">&rarr;</div>
                <div class="cdms-flow__node">
                    <span class="cdms-card__badge">CDMS</span>
                    <span class="cdms-flow__count">Sync</span>
                    <span class="cdms-flow__label">Engine</span>
                </div>
                <div class="cdms-flow__arrow">&rarr;</div>
                <div class="cdms-flow__node cdms-flow__node--close">
                    <span class="cdms-card__badge cdms-card__badge--close">Close CRM</span>
                    <span class="cdms-flow__count" id="flow-close-count">0</span>
                    <span class="cdms-flow__label">Created</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="cdms-actions">
                <button type="button" id="btn-fetch" class="cdms-btn cdms-btn--primary cdms-btn--lg">
                    Fetch GHL Contacts
                </button>
                <button type="button" id="btn-push" class="cdms-btn cdms-btn--success cdms-btn--lg" disabled>
                    Push to Close CRM
                </button>
                <div class="cdms-actions__spacer"></div>
                <div class="cdms-search">
                    <input type="text" id="search-input" class="cdms-search__input"
                           placeholder="Filter contacts..." aria-label="Filter contacts">
                    <span class="cdms-search__btn" aria-hidden="true">&#128269;</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="cdms-stats">
                <div class="cdms-stat">
                    <div class="cdms-stat__value" id="stat-total">0</div>
                    <div class="cdms-stat__label">Total Fetched</div>
                </div>
                <div class="cdms-stat">
                    <div class="cdms-stat__value cdms-stat__value--success" id="stat-created">0</div>
                    <div class="cdms-stat__label">Created in Close</div>
                </div>
                <div class="cdms-stat">
                    <div class="cdms-stat__value cdms-stat__value--warning" id="stat-skipped">0</div>
                    <div class="cdms-stat__label">Skipped (Duplicates)</div>
                </div>
                <div class="cdms-stat">
                    <div class="cdms-stat__value cdms-stat__value--danger" id="stat-failed">0</div>
                    <div class="cdms-stat__label">Failed</div>
                </div>
            </div>

            <!-- Contacts Table -->
            <div class="cdms-card" id="contacts-card" style="display: none;">
                <div class="cdms-card__header">
                    <span class="cdms-card__title">GoHighLevel Contacts</span>
                    <span class="cdms-card__badge cdms-card__badge--ghl">GHL</span>
                </div>
                <div class="cdms-table-wrap">
                    <table class="cdms-table">
                        <thead>
                            <tr>
                                <th class="cdms-table__checkbox">
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
                                <td colspan="6" class="cdms-empty">
                                    <div class="cdms-empty__text">
                                        Click "Fetch GHL Contacts" to load contacts
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sync Results -->
            <div class="cdms-card" id="results-card" style="display: none;">
                <div class="cdms-card__header">
                    <span class="cdms-card__title">Sync Progress</span>
                    <span class="cdms-card__badge cdms-card__badge--close">Close CRM</span>
                </div>

                <!-- Progress Bar -->
                <div class="cdms-progress" id="progress-wrap" style="display: none;">
                    <div class="cdms-progress__bar" id="progress-bar"></div>
                </div>

                <!-- Activity Log -->
                <div class="cdms-log" id="sync-log" role="log" aria-label="Sync activity log"></div>
            </div>

        </main>

        <!-- Footer -->
        <footer class="cdms-footer">
            Cydian Data Management System (CDMS) &copy; <?php echo date('Y'); ?> &mdash; Contact Sync Module
        </footer>

    </div>

    <script src="js/app.js"></script>
</body>
</html>
