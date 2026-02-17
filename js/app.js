/**
 * CDMS - Cydian Data Management System
 * Frontend Application Controller
 *
 * Handles two sync workflows (on separate pages):
 *   1. Contact Sync (index.php): GHL -> Close
 *   2. Activity Sync (activities.php): Close -> GHL
 */

(function () {
    'use strict';

    // --- State ---
    var state = {
        contacts: [],
        selectedIds: {},
        syncing: false,
        fetching: false,
        activities: [],
        selectedActIds: {},
        fetchingActivities: false,
        syncingActivities: false
    };

    // --- DOM References ---
    var els = {};

    // --- Initialize ---
    function init() {
        // Detect which page we're on
        var isContactPage = !!document.getElementById('btn-fetch');
        var isActivityPage = !!document.getElementById('btn-fetch-activities');

        if (isContactPage) {
            initContactSync();
        }

        if (isActivityPage) {
            initActivitySync();
        }
    }

    function initContactSync() {
        els.fetchBtn      = document.getElementById('btn-fetch');
        els.pushBtn       = document.getElementById('btn-push');
        els.selectAllCb   = document.getElementById('cb-select-all');
        els.contactsTable = document.getElementById('contacts-tbody');
        els.contactsCard  = document.getElementById('contacts-card');
        els.resultsCard   = document.getElementById('results-card');
        els.logContainer  = document.getElementById('sync-log');
        els.progressBar   = document.getElementById('progress-bar');
        els.progressWrap  = document.getElementById('progress-wrap');
        els.statTotal     = document.getElementById('stat-total');
        els.statCreated   = document.getElementById('stat-created');
        els.statSkipped   = document.getElementById('stat-skipped');
        els.statFailed    = document.getElementById('stat-failed');
        els.flowGhlCount  = document.getElementById('flow-ghl-count');
        els.flowCloseCount = document.getElementById('flow-close-count');
        els.alertArea     = document.getElementById('alert-area');
        els.searchInput   = document.getElementById('search-input');
        els.filterTag     = document.getElementById('filter-tag');
        els.filterSmartList = document.getElementById('filter-smartlist');

        els.fetchBtn.addEventListener('click', handleFetch);
        els.pushBtn.addEventListener('click', handlePush);
        els.selectAllCb.addEventListener('change', handleSelectAll);

        if (els.searchInput) {
            els.searchInput.addEventListener('input', handleSearch);
        }

        // Load available tags into the dropdown
        loadTags();
    }

    function loadTags() {
        apiCall('fetchTags', {}, function (status, response) {
            if (response.error || !response.tags) return;

            var tags = response.tags;
            for (var i = 0; i < tags.length; i++) {
                var tagName = tags[i].name || tags[i];
                var opt = document.createElement('option');
                opt.value = tagName;
                opt.textContent = tagName;
                els.filterTag.appendChild(opt);
            }
        });
    }

    function initActivitySync() {
        els.fetchActBtn      = document.getElementById('btn-fetch-activities');
        els.syncActBtn       = document.getElementById('btn-sync-activities');
        els.actTypeSelect    = document.getElementById('activity-type-select');
        els.actDateAfter     = document.getElementById('activity-date-after');
        els.selectAllActCb   = document.getElementById('cb-select-all-act');
        els.activitiesTable  = document.getElementById('activities-tbody');
        els.activitiesCard   = document.getElementById('activities-card');
        els.actResultsCard   = document.getElementById('activity-results-card');
        els.actLogContainer  = document.getElementById('activity-sync-log');
        els.actProgressBar   = document.getElementById('act-progress-bar');
        els.actProgressWrap  = document.getElementById('act-progress-wrap');
        els.statActTotal     = document.getElementById('stat-act-total');
        els.statActSynced    = document.getElementById('stat-act-synced');
        els.statActNoMatch   = document.getElementById('stat-act-nomatch');
        els.statActFailed    = document.getElementById('stat-act-failed');
        els.flowActCloseCount = document.getElementById('flow-act-close-count');
        els.flowActGhlCount  = document.getElementById('flow-act-ghl-count');
        els.actAlertArea     = document.getElementById('activity-alert-area');

        els.fetchActBtn.addEventListener('click', handleFetchActivities);
        els.syncActBtn.addEventListener('click', handleSyncActivities);
        els.selectAllActCb.addEventListener('change', handleSelectAllActivities);
    }

    // --- API Helper ---
    function apiCall(action, data, callback) {
        var payload = Object.assign({ action: action }, data || {});

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/sync.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                response = { error: 'Invalid server response' };
            }

            callback(xhr.status, response);
        };

        xhr.send(JSON.stringify(payload));
    }

    // ==========================================================
    // Contact Sync: GHL -> Close CRM
    // ==========================================================

    function handleFetch() {
        if (state.fetching) return;

        state.fetching = true;
        state.contacts = [];
        state.selectedIds = {};

        els.fetchBtn.disabled = true;
        els.pushBtn.disabled = true;
        els.fetchBtn.innerHTML = '<span class="spinner"></span> Fetching...';

        clearAlert();
        clearLog();
        hideElement(els.resultsCard);

        var fetchData = {};
        var tagVal = els.filterTag ? els.filterTag.value : '';
        var smartListVal = els.filterSmartList ? els.filterSmartList.value.trim() : '';

        if (tagVal) {
            fetchData.tag = tagVal;
            log('info', 'Filtering by tag: ' + tagVal);
        }
        if (smartListVal) {
            fetchData.smartListId = smartListVal;
            log('info', 'Filtering by Smart List: ' + smartListVal);
        }

        log('info', 'Connecting to GoHighLevel API...');

        apiCall('fetch', fetchData, function (status, response) {
            state.fetching = false;
            els.fetchBtn.disabled = false;
            els.fetchBtn.innerHTML = 'Fetch GHL Contacts';

            if (response.error) {
                log('error', 'Error: ' + response.error);
                showAlert('danger', 'Failed to fetch contacts: ' + response.error);
                return;
            }

            state.contacts = response.contacts || [];
            log('success', 'Fetched ' + state.contacts.length + ' contacts from GoHighLevel');

            els.flowGhlCount.textContent = state.contacts.length;
            els.statTotal.textContent = state.contacts.length;

            renderContactsTable(state.contacts);
            showElement(els.contactsCard);

            if (state.contacts.length > 0) {
                els.pushBtn.disabled = false;
                showAlert('info', 'Select contacts to sync, then click "Push to Close CRM".');
            } else {
                showAlert('warning', 'No contacts found in GoHighLevel.');
            }
        });
    }

    function handlePush() {
        var selected = getSelectedContacts();

        if (selected.length === 0) {
            showAlert('warning', 'Please select at least one contact to push.');
            return;
        }

        if (state.syncing) return;
        state.syncing = true;

        els.pushBtn.disabled = true;
        els.fetchBtn.disabled = true;
        els.pushBtn.innerHTML = '<span class="spinner"></span> Syncing...';

        showElement(els.resultsCard);
        showElement(els.progressWrap);
        setProgress(10);

        log('info', 'Pushing ' + selected.length + ' contacts to Close CRM...');

        apiCall('push', { contacts: selected }, function (status, response) {
            state.syncing = false;
            els.pushBtn.disabled = false;
            els.fetchBtn.disabled = false;
            els.pushBtn.innerHTML = 'Push to Close CRM';

            if (response.error) {
                log('error', 'Sync error: ' + response.error);
                showAlert('danger', 'Sync failed: ' + response.error);
                setProgress(100);
                return;
            }

            var results = response.results || {};
            var details = results.details || [];

            els.statCreated.textContent = results.created || 0;
            els.statSkipped.textContent = results.skipped || 0;
            els.statFailed.textContent  = results.failed || 0;
            els.flowCloseCount.textContent = results.created || 0;

            for (var i = 0; i < details.length; i++) {
                var d = details[i];
                var label = d.name + (d.email ? ' (' + d.email + ')' : '');

                if (d.status === 'created') {
                    log('success', 'Created: ' + label);
                } else if (d.status === 'skipped') {
                    log('warning', 'Skipped: ' + label + ' - ' + d.reason);
                } else {
                    log('error', 'Failed: ' + label + ' - ' + d.reason);
                }
            }

            setProgress(100);

            var msg = 'Sync complete. Created: ' + (results.created || 0) +
                      ', Skipped: ' + (results.skipped || 0) +
                      ', Failed: ' + (results.failed || 0);
            log('info', msg);

            if (results.failed > 0) {
                showAlert('warning', msg);
            } else {
                showAlert('success', msg);
            }
        });
    }

    function renderContactsTable(contacts) {
        var html = '';

        if (contacts.length === 0) {
            html = '<tr><td colspan="6" class="empty-state">' +
                   'No contacts to display</td></tr>';
            els.contactsTable.innerHTML = html;
            return;
        }

        for (var i = 0; i < contacts.length; i++) {
            var c = contacts[i];
            var id = c.id || i;
            var name = formatName(c);
            var email = escapeHtml(c.email || '-');
            var phone = escapeHtml(c.phone || '-');
            var company = escapeHtml(c.companyName || '-');
            var source = escapeHtml(c.source || '-');

            html += '<tr data-contact-id="' + escapeHtml(String(id)) + '">' +
                    '<td class="col-checkbox">' +
                    '<input type="checkbox" class="contact-cb" data-idx="' + i + '" checked>' +
                    '</td>' +
                    '<td>' + escapeHtml(name) + '</td>' +
                    '<td>' + email + '</td>' +
                    '<td>' + phone + '</td>' +
                    '<td>' + company + '</td>' +
                    '<td>' + source + '</td>' +
                    '</tr>';

            state.selectedIds[i] = true;
        }

        els.contactsTable.innerHTML = html;
        els.selectAllCb.checked = true;

        var checkboxes = els.contactsTable.querySelectorAll('.contact-cb');
        for (var j = 0; j < checkboxes.length; j++) {
            checkboxes[j].addEventListener('change', handleCheckboxChange);
        }
    }

    function handleSelectAll() {
        var checked = els.selectAllCb.checked;
        var checkboxes = els.contactsTable.querySelectorAll('.contact-cb');

        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = checked;
            var idx = parseInt(checkboxes[i].getAttribute('data-idx'), 10);
            if (checked) {
                state.selectedIds[idx] = true;
            } else {
                delete state.selectedIds[idx];
            }
        }

        updatePushButtonLabel();
    }

    function handleCheckboxChange(e) {
        var idx = parseInt(e.target.getAttribute('data-idx'), 10);
        if (e.target.checked) {
            state.selectedIds[idx] = true;
        } else {
            delete state.selectedIds[idx];
        }

        var checkboxes = els.contactsTable.querySelectorAll('.contact-cb');
        var allChecked = true;
        for (var i = 0; i < checkboxes.length; i++) {
            if (!checkboxes[i].checked) {
                allChecked = false;
                break;
            }
        }
        els.selectAllCb.checked = allChecked;

        updatePushButtonLabel();
    }

    function getSelectedContacts() {
        var selected = [];
        var keys = Object.keys(state.selectedIds);
        for (var i = 0; i < keys.length; i++) {
            var idx = parseInt(keys[i], 10);
            if (state.contacts[idx]) {
                selected.push(state.contacts[idx]);
            }
        }
        return selected;
    }

    function updatePushButtonLabel() {
        var count = Object.keys(state.selectedIds).length;
        if (!state.syncing) {
            els.pushBtn.innerHTML = 'Push to Close CRM' + (count > 0 ? ' (' + count + ')' : '');
        }
    }

    function handleSearch() {
        var query = els.searchInput.value.toLowerCase().trim();

        if (!query) {
            renderContactsTable(state.contacts);
            return;
        }

        var filtered = [];
        for (var i = 0; i < state.contacts.length; i++) {
            var c = state.contacts[i];
            var searchStr = (
                (c.firstName || '') + ' ' +
                (c.lastName || '') + ' ' +
                (c.name || '') + ' ' +
                (c.email || '') + ' ' +
                (c.phone || '') + ' ' +
                (c.companyName || '')
            ).toLowerCase();

            if (searchStr.indexOf(query) !== -1) {
                filtered.push(c);
            }
        }

        renderContactsTable(filtered);
    }

    // --- Contact Sync Logging/Alerts/Progress ---
    function log(type, message) {
        if (!els.logContainer) return;
        var now = new Date();
        var time = padZero(now.getHours()) + ':' + padZero(now.getMinutes()) + ':' + padZero(now.getSeconds());
        var entry = document.createElement('div');
        entry.className = 'log-entry ' + type;
        entry.innerHTML = '<span class="log-time">[' + time + ']</span> ' + escapeHtml(message);
        els.logContainer.appendChild(entry);
        els.logContainer.scrollTop = els.logContainer.scrollHeight;
    }

    function clearLog() {
        if (els.logContainer) els.logContainer.innerHTML = '';
    }

    function showAlert(type, message) {
        if (!els.alertArea) return;
        els.alertArea.innerHTML = '<div class="alert ' + type + '">' + escapeHtml(message) + '</div>';
    }

    function clearAlert() {
        if (els.alertArea) els.alertArea.innerHTML = '';
    }

    function setProgress(pct) {
        if (els.progressBar) {
            els.progressBar.style.width = pct + '%';
            if (pct >= 100) els.progressBar.textContent = 'Complete';
        }
    }

    // ==========================================================
    // Activity Sync: Close CRM -> GoHighLevel
    // ==========================================================

    function handleFetchActivities() {
        if (state.fetchingActivities) return;

        state.fetchingActivities = true;
        state.activities = [];
        state.selectedActIds = {};

        els.fetchActBtn.disabled = true;
        els.syncActBtn.disabled = true;
        els.fetchActBtn.innerHTML = '<span class="spinner"></span> Fetching...';

        clearActAlert();
        clearActLog();
        hideElement(els.actResultsCard);

        var type = els.actTypeSelect.value;
        var dateAfter = els.actDateAfter.value || '';

        actLog('info', 'Connecting to Close CRM API...');

        apiCall('fetchActivities', { activityType: type, dateAfter: dateAfter }, function (status, response) {
            state.fetchingActivities = false;
            els.fetchActBtn.disabled = false;
            els.fetchActBtn.innerHTML = 'Fetch Close Activities';

            if (response.error) {
                actLog('error', 'Error: ' + response.error);
                showActAlert('danger', 'Failed to fetch activities: ' + response.error);
                return;
            }

            state.activities = response.activities || [];
            actLog('success', 'Fetched ' + state.activities.length + ' activities from Close CRM');

            els.flowActCloseCount.textContent = state.activities.length;
            els.statActTotal.textContent = state.activities.length;

            renderActivitiesTable(state.activities);
            showElement(els.activitiesCard);

            if (state.activities.length > 0) {
                els.syncActBtn.disabled = false;
                showActAlert('info', 'Select activities to sync, then click "Sync to GHL as Notes".');
            } else {
                showActAlert('warning', 'No activities found in Close CRM.');
            }
        });
    }

    function handleSyncActivities() {
        var selected = getSelectedActivities();

        if (selected.length === 0) {
            showActAlert('warning', 'Please select at least one activity to sync.');
            return;
        }

        if (state.syncingActivities) return;
        state.syncingActivities = true;

        els.syncActBtn.disabled = true;
        els.fetchActBtn.disabled = true;
        els.syncActBtn.innerHTML = '<span class="spinner"></span> Syncing...';

        showElement(els.actResultsCard);
        showElement(els.actProgressWrap);
        setActProgress(10);

        actLog('info', 'Syncing ' + selected.length + ' activities to GoHighLevel...');

        apiCall('syncActivities', { activities: selected }, function (status, response) {
            state.syncingActivities = false;
            els.syncActBtn.disabled = false;
            els.fetchActBtn.disabled = false;
            els.syncActBtn.innerHTML = 'Sync to GHL as Notes';

            if (response.error) {
                actLog('error', 'Sync error: ' + response.error);
                showActAlert('danger', 'Sync failed: ' + response.error);
                setActProgress(100);
                return;
            }

            var results = response.results || {};
            var details = results.details || [];

            els.statActSynced.textContent = results.synced || 0;
            els.statActNoMatch.textContent = results.noMatch || 0;
            els.statActFailed.textContent = results.failed || 0;
            els.flowActGhlCount.textContent = results.synced || 0;

            for (var i = 0; i < details.length; i++) {
                var d = details[i];
                var label = d.type + (d.email ? ' (' + d.email + ')' : '');

                if (d.status === 'synced') {
                    actLog('success', 'Synced: ' + label);
                } else if (d.status === 'noMatch') {
                    actLog('warning', 'No match: ' + label + ' - ' + d.reason);
                } else {
                    actLog('error', 'Failed: ' + label + ' - ' + d.reason);
                }
            }

            setActProgress(100);

            var msg = 'Activity sync complete. Synced: ' + (results.synced || 0) +
                      ', No Match: ' + (results.noMatch || 0) +
                      ', Failed: ' + (results.failed || 0);
            actLog('info', msg);

            if (results.failed > 0) {
                showActAlert('warning', msg);
            } else {
                showActAlert('success', msg);
            }
        });
    }

    function renderActivitiesTable(activities) {
        var html = '';

        if (activities.length === 0) {
            html = '<tr><td colspan="5" class="empty-state">No activities to display</td></tr>';
            els.activitiesTable.innerHTML = html;
            return;
        }

        for (var i = 0; i < activities.length; i++) {
            var a = activities[i];
            var type = escapeHtml(a._type || 'Unknown');
            var email = escapeHtml(a._email || '-');
            var summary = escapeHtml(getActivitySummary(a));
            var date = escapeHtml((a.date_created || '').substring(0, 10));

            html += '<tr>' +
                    '<td class="col-checkbox">' +
                    '<input type="checkbox" class="activity-cb" data-idx="' + i + '" checked>' +
                    '</td>' +
                    '<td>' + type + '</td>' +
                    '<td>' + email + '</td>' +
                    '<td>' + summary + '</td>' +
                    '<td>' + date + '</td>' +
                    '</tr>';

            state.selectedActIds[i] = true;
        }

        els.activitiesTable.innerHTML = html;
        els.selectAllActCb.checked = true;

        var checkboxes = els.activitiesTable.querySelectorAll('.activity-cb');
        for (var j = 0; j < checkboxes.length; j++) {
            checkboxes[j].addEventListener('change', handleActCheckboxChange);
        }
    }

    function getActivitySummary(activity) {
        var type = activity._type || '';
        switch (type) {
            case 'Note':
                return (activity.note || '').substring(0, 80);
            case 'Call':
                return (activity.direction || '') + ' call, ' + (activity.duration || 0) + 's';
            case 'Email':
                return activity.subject || '(no subject)';
            case 'Meeting':
                return activity.title || '(untitled meeting)';
            default:
                return type;
        }
    }

    function handleSelectAllActivities() {
        var checked = els.selectAllActCb.checked;
        var checkboxes = els.activitiesTable.querySelectorAll('.activity-cb');

        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = checked;
            var idx = parseInt(checkboxes[i].getAttribute('data-idx'), 10);
            if (checked) {
                state.selectedActIds[idx] = true;
            } else {
                delete state.selectedActIds[idx];
            }
        }
        updateSyncActButtonLabel();
    }

    function handleActCheckboxChange(e) {
        var idx = parseInt(e.target.getAttribute('data-idx'), 10);
        if (e.target.checked) {
            state.selectedActIds[idx] = true;
        } else {
            delete state.selectedActIds[idx];
        }

        var checkboxes = els.activitiesTable.querySelectorAll('.activity-cb');
        var allChecked = true;
        for (var i = 0; i < checkboxes.length; i++) {
            if (!checkboxes[i].checked) { allChecked = false; break; }
        }
        els.selectAllActCb.checked = allChecked;
        updateSyncActButtonLabel();
    }

    function getSelectedActivities() {
        var selected = [];
        var keys = Object.keys(state.selectedActIds);
        for (var i = 0; i < keys.length; i++) {
            var idx = parseInt(keys[i], 10);
            if (state.activities[idx]) {
                selected.push(state.activities[idx]);
            }
        }
        return selected;
    }

    function updateSyncActButtonLabel() {
        var count = Object.keys(state.selectedActIds).length;
        if (!state.syncingActivities) {
            els.syncActBtn.innerHTML = 'Sync to GHL as Notes' + (count > 0 ? ' (' + count + ')' : '');
        }
    }

    // --- Activity Log/Alert/Progress helpers ---
    function actLog(type, message) {
        if (!els.actLogContainer) return;
        var now = new Date();
        var time = padZero(now.getHours()) + ':' + padZero(now.getMinutes()) + ':' + padZero(now.getSeconds());
        var entry = document.createElement('div');
        entry.className = 'log-entry ' + type;
        entry.innerHTML = '<span class="log-time">[' + time + ']</span> ' + escapeHtml(message);
        els.actLogContainer.appendChild(entry);
        els.actLogContainer.scrollTop = els.actLogContainer.scrollHeight;
    }

    function clearActLog() {
        if (els.actLogContainer) els.actLogContainer.innerHTML = '';
    }

    function showActAlert(type, message) {
        if (!els.actAlertArea) return;
        els.actAlertArea.innerHTML = '<div class="alert ' + type + '">' + escapeHtml(message) + '</div>';
    }

    function clearActAlert() {
        if (els.actAlertArea) els.actAlertArea.innerHTML = '';
    }

    function setActProgress(pct) {
        if (els.actProgressBar) {
            els.actProgressBar.style.width = pct + '%';
            if (pct >= 100) els.actProgressBar.textContent = 'Complete';
        }
    }

    // ==========================================================
    // Shared Utilities
    // ==========================================================

    function formatName(contact) {
        var name = ((contact.firstName || '') + ' ' + (contact.lastName || '')).trim();
        return name || contact.name || 'Unknown';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function padZero(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function showElement(el) {
        if (el) el.classList.remove('hidden');
    }

    function hideElement(el) {
        if (el) el.classList.add('hidden');
    }

    // --- Boot ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
