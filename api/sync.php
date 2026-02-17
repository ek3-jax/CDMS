<?php
/**
 * CDMS - Sync Orchestrator
 *
 * AJAX endpoint that handles sync between GHL and Close CRM.
 * Supports these actions:
 *   - fetch:          Pull contacts from GoHighLevel
 *   - push:           Push contacts to Close CRM (with duplicate detection)
 *   - preview:        Fetch GHL contacts and return them for review before pushing
 *   - fetchActivities: Pull activities from Close CRM
 *   - syncActivities:  Push Close activities as notes to matching GHL contacts
 */

header('Content-Type: application/json');

require_once __DIR__ . '/ghl.php';
require_once __DIR__ . '/close.php';
require_once __DIR__ . '/../includes/logger.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

cdms_log('INFO', 'SYNC', "Action requested: {$action}");

try {
    switch ($action) {
        case 'fetch':
            handleFetch();
            break;

        case 'push':
            handlePush($input);
            break;

        case 'preview':
            handlePreview($input);
            break;

        case 'fetchActivities':
            handleFetchActivities($input);
            break;

        case 'syncActivities':
            handleSyncActivities($input);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    cdms_log('ERROR', 'SYNC', 'Uncaught exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile() . ':' . $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal error: ' . $e->getMessage(),
    ]);
}

/**
 * Fetch all contacts from GoHighLevel.
 */
function handleFetch(): void
{
    cdms_log('INFO', 'SYNC', 'Starting GHL fetch all contacts');
    $ghl    = new GHLClient();
    $result = $ghl->fetchAllContacts();

    if ($result['error']) {
        cdms_log('ERROR', 'SYNC', 'Fetch failed', ['error' => $result['error'], 'partial' => $result['total']]);
        http_response_code(502);
        echo json_encode([
            'error'    => $result['error'],
            'contacts' => $result['contacts'],
            'total'    => $result['total'],
        ]);
        return;
    }

    cdms_log('INFO', 'SYNC', "Fetch complete: {$result['total']} contacts");
    echo json_encode([
        'success'  => true,
        'contacts' => $result['contacts'],
        'total'    => $result['total'],
    ]);
}

/**
 * Push selected contacts to Close CRM.
 *
 * Expects $input['contacts'] to be an array of GHL contact objects.
 * Checks for duplicates in Close by email before creating.
 */
function handlePush(array $input): void
{
    $contacts = $input['contacts'] ?? [];

    if (empty($contacts)) {
        cdms_log('ERROR', 'SYNC', 'Push called with no contacts');
        http_response_code(400);
        echo json_encode(['error' => 'No contacts provided']);
        return;
    }

    cdms_log('INFO', 'SYNC', 'Starting push to Close', ['contactCount' => count($contacts)]);
    $close   = new CloseClient();
    $results = [
        'created'    => 0,
        'skipped'    => 0,
        'failed'     => 0,
        'details'    => [],
    ];

    foreach ($contacts as $ghlContact) {
        $contactName = trim(
            ($ghlContact['firstName'] ?? '') . ' ' . ($ghlContact['lastName'] ?? '')
        );
        if (empty($contactName)) {
            $contactName = $ghlContact['name'] ?? 'Unknown';
        }

        $email = $ghlContact['email'] ?? '';

        // Check for duplicate by email if email exists
        if (!empty($email)) {
            $search = $close->searchLeadByEmail($email);
            if (!$search['error'] && !empty($search['leads'])) {
                $results['skipped']++;
                $results['details'][] = [
                    'name'   => $contactName,
                    'email'  => $email,
                    'status' => 'skipped',
                    'reason' => 'Already exists in Close',
                ];
                continue;
            }
        }

        // Create the lead with contact in Close
        $createResult = $close->createLeadFromGHLContact($ghlContact);

        if ($createResult['error']) {
            $results['failed']++;
            $results['details'][] = [
                'name'   => $contactName,
                'email'  => $email,
                'status' => 'failed',
                'reason' => $createResult['error'],
            ];
        } else {
            $results['created']++;
            $results['details'][] = [
                'name'    => $contactName,
                'email'   => $email,
                'status'  => 'created',
                'leadId'  => $createResult['lead']['id'] ?? null,
            ];
        }

        // Rate limit: brief pause between requests
        usleep(200000); // 200ms
    }

    cdms_log('INFO', 'SYNC', 'Push complete', [
        'created' => $results['created'],
        'skipped' => $results['skipped'],
        'failed'  => $results['failed'],
    ]);

    echo json_encode([
        'success' => true,
        'results' => $results,
    ]);
}

/**
 * Preview: fetch contacts from GHL with optional search query.
 */
function handlePreview(array $input): void
{
    $page  = $input['page'] ?? 1;
    $query = $input['query'] ?? '';

    cdms_log('INFO', 'SYNC', 'Preview requested', ['page' => $page, 'query' => $query]);

    $ghl    = new GHLClient();
    $result = $ghl->searchContacts($page, $query);

    if ($result['error']) {
        cdms_log('ERROR', 'SYNC', 'Preview failed', ['error' => $result['error']]);
        http_response_code(502);
        echo json_encode(['error' => $result['error']]);
        return;
    }

    cdms_log('INFO', 'SYNC', "Preview complete: {$result['total']} contacts");

    echo json_encode([
        'success'  => true,
        'contacts' => $result['contacts'],
        'total'    => $result['total'],
        'page'     => $page,
    ]);
}

/**
 * Fetch activities from Close CRM for display.
 */
function handleFetchActivities(array $input): void
{
    $type      = $input['activityType'] ?? '';
    $dateAfter = $input['dateAfter'] ?? '';

    cdms_log('INFO', 'SYNC', 'Fetching Close activities', ['type' => $type ?: 'all', 'dateAfter' => $dateAfter]);

    $close = new CloseClient();
    $allActivities = [];
    $skip = 0;
    $limit = 100;

    // Paginate through all activities
    while (true) {
        $result = $close->fetchActivities($type, $skip, $limit, $dateAfter);

        if ($result['error']) {
            cdms_log('ERROR', 'SYNC', 'Activity fetch failed', ['error' => $result['error']]);
            http_response_code(502);
            echo json_encode(['error' => $result['error']]);
            return;
        }

        $allActivities = array_merge($allActivities, $result['activities']);

        if (!$result['hasMore'] || count($result['activities']) === 0) {
            break;
        }

        $skip += $limit;

        // Safety cap
        if ($skip > 5000) {
            cdms_log('INFO', 'SYNC', 'Activity fetch capped at 5000');
            break;
        }
    }

    // Resolve contact emails for activities that have a contact_id
    $contactEmailCache = [];
    foreach ($allActivities as &$activity) {
        $contactId = $activity['contact_id'] ?? '';
        if (empty($contactId)) {
            $activity['_email'] = '';
            continue;
        }

        if (isset($contactEmailCache[$contactId])) {
            $activity['_email'] = $contactEmailCache[$contactId];
            continue;
        }

        // For email activities, extract from sender/to fields
        $actType = $activity['_type'] ?? '';
        if ($actType === 'Email') {
            $email = extractEmailAddress($activity['sender'] ?? '');
            if (empty($email)) {
                // Try the to field
                $toList = $activity['to'] ?? [];
                if (!empty($toList) && is_array($toList)) {
                    $email = extractEmailAddress($toList[0] ?? '');
                }
            }
            if (!empty($email)) {
                $contactEmailCache[$contactId] = $email;
                $activity['_email'] = $email;
                continue;
            }
        }

        // Fetch contact from Close to get email
        $contactResult = $close->getContact($contactId);
        $email = '';
        if (!$contactResult['error'] && $contactResult['contact']) {
            $emails = $contactResult['contact']['emails'] ?? [];
            if (!empty($emails)) {
                $email = $emails[0]['email'] ?? '';
            }
        }
        $contactEmailCache[$contactId] = $email;
        $activity['_email'] = $email;

        usleep(100000); // 100ms rate limit
    }
    unset($activity);

    cdms_log('INFO', 'SYNC', 'Activity fetch complete', ['count' => count($allActivities)]);

    echo json_encode([
        'success'    => true,
        'activities' => $allActivities,
        'total'      => count($allActivities),
    ]);
}

/**
 * Sync Close CRM activities to GHL as notes, matched by email.
 */
function handleSyncActivities(array $input): void
{
    $activities = $input['activities'] ?? [];

    if (empty($activities)) {
        cdms_log('ERROR', 'SYNC', 'syncActivities called with no activities');
        http_response_code(400);
        echo json_encode(['error' => 'No activities provided']);
        return;
    }

    cdms_log('INFO', 'SYNC', 'Starting activity sync to GHL', ['count' => count($activities)]);

    $ghl = new GHLClient();
    $results = [
        'synced'    => 0,
        'noMatch'   => 0,
        'failed'    => 0,
        'details'   => [],
    ];

    // Cache GHL contact lookups by email
    $ghlContactCache = [];

    foreach ($activities as $activity) {
        $email = $activity['_email'] ?? '';
        $actType = $activity['_type'] ?? 'Activity';

        if (empty($email)) {
            $results['noMatch']++;
            $results['details'][] = [
                'type'   => $actType,
                'email'  => '',
                'status' => 'noMatch',
                'reason' => 'No email associated with activity',
            ];
            continue;
        }

        // Look up GHL contact by email (cached)
        if (!isset($ghlContactCache[$email])) {
            $lookup = $ghl->lookupContactByEmail($email);
            $ghlContactCache[$email] = $lookup;
            usleep(100000); // 100ms rate limit
        }

        $ghlLookup = $ghlContactCache[$email];

        if ($ghlLookup['error'] || empty($ghlLookup['contactId'])) {
            $results['noMatch']++;
            $results['details'][] = [
                'type'   => $actType,
                'email'  => $email,
                'status' => 'noMatch',
                'reason' => $ghlLookup['error'] ?: 'No matching contact in GHL',
            ];
            continue;
        }

        // Build note body from activity
        $noteBody = formatActivityAsNote($activity);

        // Create note on the GHL contact
        $noteResult = $ghl->createNote($ghlLookup['contactId'], $noteBody);

        if ($noteResult['error']) {
            $results['failed']++;
            $results['details'][] = [
                'type'   => $actType,
                'email'  => $email,
                'status' => 'failed',
                'reason' => $noteResult['error'],
            ];
        } else {
            $results['synced']++;
            $results['details'][] = [
                'type'   => $actType,
                'email'  => $email,
                'status' => 'synced',
            ];
        }

        usleep(200000); // 200ms rate limit
    }

    cdms_log('INFO', 'SYNC', 'Activity sync complete', [
        'synced'  => $results['synced'],
        'noMatch' => $results['noMatch'],
        'failed'  => $results['failed'],
    ]);

    echo json_encode([
        'success' => true,
        'results' => $results,
    ]);
}

/**
 * Format a Close activity into a readable note for GHL.
 */
function formatActivityAsNote(array $activity): string
{
    $type = $activity['_type'] ?? 'Activity';
    $date = $activity['date_created'] ?? $activity['activity_at'] ?? '';
    $lines = ["=== Close CRM {$type} ===", "Date: {$date}", ""];

    switch ($type) {
        case 'Note':
            $lines[] = $activity['note'] ?? $activity['note_html'] ?? '';
            break;

        case 'Call':
            $dir = ucfirst($activity['direction'] ?? 'unknown');
            $dur = $activity['duration'] ?? 0;
            $mins = floor($dur / 60);
            $secs = $dur % 60;
            $durationStr = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
            $status = $activity['status'] ?? '';

            $lines[] = "Direction: {$dir}";
            $lines[] = "Duration: {$durationStr} ({$dur} seconds)";
            $lines[] = "Status: {$status}";

            if (!empty($activity['phone'])) {
                $lines[] = "Phone: {$activity['phone']}";
            }
            if (!empty($activity['disposition'])) {
                $lines[] = "Disposition: {$activity['disposition']}";
            }
            if (!empty($activity['source'])) {
                $lines[] = "Source: {$activity['source']}";
            }

            // Call notes
            if (!empty($activity['note'])) {
                $lines[] = "";
                $lines[] = "--- Call Notes ---";
                $lines[] = $activity['note'];
            }

            // Recording link
            if (!empty($activity['recording_url'])) {
                $lines[] = "";
                $lines[] = "--- Call Recording ---";
                $lines[] = $activity['recording_url'];
            }

            // Voicemail
            if (!empty($activity['voicemail_url'])) {
                $lines[] = "";
                $lines[] = "--- Voicemail ---";
                $lines[] = $activity['voicemail_url'];
            }
            break;

        case 'Email':
            $dir = ucfirst($activity['direction'] ?? '');
            $lines[] = "Direction: {$dir}";
            $lines[] = "Subject: " . ($activity['subject'] ?? '(no subject)');
            $lines[] = "From: " . ($activity['sender'] ?? '');

            $to = $activity['to'] ?? [];
            if (!empty($to) && is_array($to)) {
                $lines[] = "To: " . implode(', ', $to);
            }

            if (!empty($activity['body_text'])) {
                $lines[] = "";
                $lines[] = "--- Email Body ---";
                $lines[] = $activity['body_text'];
            } elseif (!empty($activity['body_preview'])) {
                $lines[] = "";
                $lines[] = "--- Preview ---";
                $lines[] = $activity['body_preview'];
            }

            // Email attachments
            $attachments = $activity['attachments'] ?? [];
            if (!empty($attachments)) {
                $lines[] = "";
                $lines[] = "--- Attachments ---";
                foreach ($attachments as $att) {
                    $name = $att['filename'] ?? 'unnamed';
                    $url = $att['url'] ?? '';
                    $lines[] = $url ? "{$name}: {$url}" : $name;
                }
            }
            break;

        case 'Meeting':
            $lines[] = "Title: " . ($activity['title'] ?? '(untitled)');
            if (!empty($activity['starts_at'])) {
                $lines[] = "Starts: {$activity['starts_at']}";
            }
            if (!empty($activity['ends_at'])) {
                $lines[] = "Ends: {$activity['ends_at']}";
            }

            $attendees = $activity['attendees'] ?? [];
            if (!empty($attendees)) {
                $names = [];
                foreach ($attendees as $a) {
                    $names[] = $a['name'] ?? $a['email'] ?? 'unknown';
                }
                $lines[] = "Attendees: " . implode(', ', $names);
            }

            if (!empty($activity['user_note_html'])) {
                $lines[] = "";
                $lines[] = "--- Meeting Notes ---";
                $lines[] = strip_tags($activity['user_note_html']);
            }
            break;

        default:
            if (!empty($activity['note'])) {
                $lines[] = $activity['note'];
            }
            break;
    }

    return implode("\n", array_filter($lines, function ($line) {
        return $line !== null;
    }));
}

/**
 * Extract an email address from a string like '"John Doe" <john@example.com>'.
 */
function extractEmailAddress(string $raw): string
{
    if (preg_match('/<([^>]+)>/', $raw, $m)) {
        return $m[1];
    }
    return filter_var(trim($raw), FILTER_VALIDATE_EMAIL) ? trim($raw) : '';
}
