/**
 * CDMS - Cydian Data Management System
 * Frontend Application Controller
 *
 * Handles the GHL -> Close contact sync workflow:
 *   1. Fetch contacts from GoHighLevel
 *   2. Display them for review and selection
 *   3. Push selected contacts to Close CRM
 *   4. Show sync results
 */

(function () {
    'use strict';

    // --- State ---
    var state = {
        contacts: [],
        selectedIds: {},
        syncing: false,
        fetching: false
    };

    // --- DOM References ---
    var els = {};

    // --- Initialize ---
    function init() {
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

        // Bind events
        els.fetchBtn.addEventListener('click', handleFetch);
        els.pushBtn.addEventListener('click', handlePush);
        els.selectAllCb.addEventListener('change', handleSelectAll);

        if (els.searchInput) {
            els.searchInput.addEventListener('input', handleSearch);
        }
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

    // --- Fetch Contacts from GHL ---
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

        log('info', 'Connecting to GoHighLevel API...');

        apiCall('fetch', {}, function (status, response) {
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

    // --- Push Contacts to Close ---
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

            // Log each result
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

    // --- Render Contacts Table ---
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

            // Default: select all
            state.selectedIds[i] = true;
        }

        els.contactsTable.innerHTML = html;
        els.selectAllCb.checked = true;

        // Bind individual checkbox events
        var checkboxes = els.contactsTable.querySelectorAll('.contact-cb');
        for (var j = 0; j < checkboxes.length; j++) {
            checkboxes[j].addEventListener('change', handleCheckboxChange);
        }
    }

    // --- Checkbox Handlers ---
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

        // Update select-all state
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

    // --- Client-Side Search Filter ---
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

    // --- Logging (matches CAMS log-entry pattern) ---
    function log(type, message) {
        if (!els.logContainer) return;

        var now = new Date();
        var time = padZero(now.getHours()) + ':' +
                   padZero(now.getMinutes()) + ':' +
                   padZero(now.getSeconds());

        var entry = document.createElement('div');
        entry.className = 'log-entry ' + type;
        entry.innerHTML = '<span class="log-time">[' + time + ']</span> ' +
                          escapeHtml(message);

        els.logContainer.appendChild(entry);
        els.logContainer.scrollTop = els.logContainer.scrollHeight;
    }

    function clearLog() {
        if (els.logContainer) {
            els.logContainer.innerHTML = '';
        }
    }

    // --- Alerts (matches CAMS alert pattern) ---
    function showAlert(type, message) {
        if (!els.alertArea) return;
        els.alertArea.innerHTML = '<div class="alert ' + type + '">' +
                                  escapeHtml(message) + '</div>';
    }

    function clearAlert() {
        if (els.alertArea) {
            els.alertArea.innerHTML = '';
        }
    }

    // --- Progress ---
    function setProgress(pct) {
        if (els.progressBar) {
            els.progressBar.style.width = pct + '%';
            if (pct >= 100) {
                els.progressBar.textContent = 'Complete';
            }
        }
    }

    // --- Utility ---
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
