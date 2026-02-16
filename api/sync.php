<?php
/**
 * CDMS - Sync Orchestrator
 *
 * AJAX endpoint that handles the GHL -> Close contact sync process.
 * Supports three actions:
 *   - fetch:   Pull contacts from GoHighLevel
 *   - push:    Push contacts to Close CRM (with duplicate detection)
 *   - preview: Fetch GHL contacts and return them for review before pushing
 */

header('Content-Type: application/json');

require_once __DIR__ . '/ghl.php';
require_once __DIR__ . '/close.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

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

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use: fetch, push, or preview']);
            break;
    }
} catch (Throwable $e) {
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
    $ghl    = new GHLClient();
    $result = $ghl->fetchAllContacts();

    if ($result['error']) {
        http_response_code(502);
        echo json_encode([
            'error'    => $result['error'],
            'contacts' => $result['contacts'],
            'total'    => $result['total'],
        ]);
        return;
    }

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
        http_response_code(400);
        echo json_encode(['error' => 'No contacts provided']);
        return;
    }

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

    $ghl    = new GHLClient();
    $result = $ghl->searchContacts($page, $query);

    if ($result['error']) {
        http_response_code(502);
        echo json_encode(['error' => $result['error']]);
        return;
    }

    echo json_encode([
        'success'  => true,
        'contacts' => $result['contacts'],
        'total'    => $result['total'],
        'page'     => $page,
    ]);
}
