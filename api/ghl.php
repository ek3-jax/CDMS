<?php
/**
 * CDMS - GoHighLevel API Client
 *
 * Handles fetching contacts and creating notes via GHL API v2.
 * Uses GET /contacts/ for listing and POST for search/notes.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logger.php';

class GHLClient
{
    private string $apiKey;
    private string $locationId;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey     = GHL_API_KEY;
        $this->locationId = GHL_LOCATION_ID;
        $this->baseUrl    = GHL_BASE_URL;

        cdms_log('DEBUG', 'GHL', 'Client initialized', [
            'locationId' => $this->locationId,
            'baseUrl'    => $this->baseUrl,
            'hasApiKey'  => !empty($this->apiKey) ? 'yes' : 'NO - MISSING',
        ]);
    }

    /**
     * Fetch all contacts from GHL, handling pagination automatically.
     */
    public function fetchAllContacts(): array
    {
        $allContacts = [];
        $startAfterId = null;
        $startAfter = null;

        while (true) {
            $result = $this->searchContacts(1, '', $startAfterId, $startAfter);

            if ($result['error']) {
                return [
                    'contacts' => $allContacts,
                    'total'    => count($allContacts),
                    'error'    => $result['error'],
                ];
            }

            $contacts = $result['contacts'] ?? [];
            if (empty($contacts)) {
                break;
            }

            $allContacts = array_merge($allContacts, $contacts);

            $total = $result['total'] ?? 0;
            if (count($contacts) < GHL_PAGE_LIMIT || count($allContacts) >= $total) {
                break;
            }

            $lastContact = end($contacts);
            $startAfterId = $lastContact['id'] ?? null;
            $startAfter = $lastContact['dateAdded'] ?? null;

            if (!$startAfterId) {
                break;
            }
        }

        return [
            'contacts' => $allContacts,
            'total'    => count($allContacts),
            'error'    => null,
        ];
    }

    /**
     * Get contacts with optional search query and cursor pagination.
     */
    public function searchContacts(int $page = 1, string $query = '', ?string $startAfterId = null, ?string $startAfter = null): array
    {
        $params = [
            'locationId' => $this->locationId,
            'limit'      => GHL_PAGE_LIMIT,
        ];

        if (!empty($query)) {
            $params['query'] = $query;
        }
        if ($startAfterId !== null) {
            $params['startAfterId'] = $startAfterId;
        }
        if ($startAfter !== null) {
            $params['startAfter'] = $startAfter;
        }

        $url = $this->baseUrl . '/contacts/?' . http_build_query($params);

        cdms_log('INFO', 'GHL', 'Fetching contacts', ['url' => $url]);

        $response = $this->makeRequest('GET', $url);

        if ($response['error']) {
            cdms_log('ERROR', 'GHL', 'Failed to fetch contacts', [
                'error'    => $response['error'],
                'httpCode' => $response['httpCode'] ?? 0,
            ]);
            return [
                'contacts' => [],
                'total'    => 0,
                'error'    => $response['error'],
            ];
        }

        $data = $response['data'];
        $count = count($data['contacts'] ?? []);
        $total = $data['total'] ?? 0;
        cdms_log('INFO', 'GHL', "Fetched {$count} contacts (total: {$total})");

        return [
            'contacts' => $data['contacts'] ?? [],
            'total'    => $total,
            'error'    => null,
        ];
    }

    /**
     * Fetch a single contact by ID.
     */
    public function getContact(string $contactId): array
    {
        $url = $this->baseUrl . '/contacts/' . urlencode($contactId);

        $response = $this->makeRequest('GET', $url);

        if ($response['error']) {
            return ['contact' => null, 'error' => $response['error']];
        }

        return [
            'contact' => $response['data']['contact'] ?? $response['data'],
            'error'   => null,
        ];
    }

    /**
     * Look up a GHL contact by email address.
     *
     * @param string $email
     * @return array{contactId: string|null, contact: array|null, error: string|null}
     */
    public function lookupContactByEmail(string $email): array
    {
        $params = [
            'locationId' => $this->locationId,
            'query'      => $email,
            'limit'      => 1,
        ];

        $url = $this->baseUrl . '/contacts/?' . http_build_query($params);

        cdms_log('DEBUG', 'GHL', 'Looking up contact by email', ['email' => $email]);

        $response = $this->makeRequest('GET', $url);

        if ($response['error']) {
            cdms_log('ERROR', 'GHL', 'Email lookup failed', ['email' => $email, 'error' => $response['error']]);
            return ['contactId' => null, 'contact' => null, 'error' => $response['error']];
        }

        $contacts = $response['data']['contacts'] ?? [];

        // Find exact email match
        foreach ($contacts as $contact) {
            $contactEmail = $contact['email'] ?? '';
            if (strcasecmp($contactEmail, $email) === 0) {
                cdms_log('DEBUG', 'GHL', 'Contact found', ['email' => $email, 'contactId' => $contact['id']]);
                return ['contactId' => $contact['id'], 'contact' => $contact, 'error' => null];
            }
        }

        cdms_log('DEBUG', 'GHL', 'No matching contact found', ['email' => $email]);
        return ['contactId' => null, 'contact' => null, 'error' => null];
    }

    /**
     * Create a note on a GHL contact.
     *
     * @param string $contactId GHL contact ID
     * @param string $body      Note text content
     * @return array{note: array|null, error: string|null}
     */
    public function createNote(string $contactId, string $body): array
    {
        $url = $this->baseUrl . '/contacts/' . urlencode($contactId) . '/notes';

        cdms_log('INFO', 'GHL', 'Creating note on contact', ['contactId' => $contactId]);

        $response = $this->makeRequest('POST', $url, ['body' => $body]);

        if ($response['error']) {
            cdms_log('ERROR', 'GHL', 'Failed to create note', [
                'contactId' => $contactId,
                'error'     => $response['error'],
            ]);
            return ['note' => null, 'error' => $response['error']];
        }

        cdms_log('INFO', 'GHL', 'Note created', ['contactId' => $contactId]);
        return ['note' => $response['data'], 'error' => null];
    }

    /**
     * Fetch all tags for the location.
     *
     * @return array{tags: array, error: string|null}
     */
    public function getTags(): array
    {
        $url = $this->baseUrl . '/locations/' . urlencode($this->locationId) . '/tags';

        cdms_log('INFO', 'GHL', 'Fetching tags for location');

        $response = $this->makeRequest('GET', $url);

        if ($response['error']) {
            cdms_log('ERROR', 'GHL', 'Failed to fetch tags', ['error' => $response['error']]);
            return ['tags' => [], 'error' => $response['error']];
        }

        $tags = $response['data']['tags'] ?? [];
        cdms_log('INFO', 'GHL', 'Fetched ' . count($tags) . ' tags');

        return ['tags' => $tags, 'error' => null];
    }

    /**
     * Search contacts using POST /contacts/search with advanced filtering.
     *
     * Supports filtering by tags and smart list ID.
     * Automatically paginates through all results.
     * Excludes contacts without an email address.
     *
     * @param string $tag         Tag name to filter by (optional)
     * @param string $smartListId Smart list ID to filter by (optional)
     * @return array{contacts: array, total: int, error: string|null}
     */
    public function searchContactsAdvanced(string $tag = '', string $smartListId = ''): array
    {
        $allContacts = [];
        $searchAfter = null;

        while (true) {
            $body = [
                'locationId' => $this->locationId,
                'pageLimit'  => GHL_PAGE_LIMIT,
            ];

            if (!empty($smartListId)) {
                $body['smartListId'] = $smartListId;
            }

            if (!empty($tag)) {
                $body['filterGroups'] = [
                    [
                        'filters' => [
                            [
                                'field'    => 'tags',
                                'operator' => 'contains',
                                'value'    => $tag,
                            ],
                        ],
                    ],
                ];
            }

            if ($searchAfter !== null) {
                $body['searchAfter'] = $searchAfter;
            }

            $url = $this->baseUrl . '/contacts/search';

            cdms_log('INFO', 'GHL', 'Searching contacts (advanced)', [
                'tag'         => $tag ?: '(none)',
                'smartListId' => $smartListId ?: '(none)',
                'page'        => count($allContacts),
            ]);

            $response = $this->makeRequest('POST', $url, $body);

            if ($response['error']) {
                cdms_log('ERROR', 'GHL', 'Advanced search failed', ['error' => $response['error']]);
                return [
                    'contacts' => $allContacts,
                    'total'    => count($allContacts),
                    'error'    => $response['error'],
                ];
            }

            $data = $response['data'];
            $contacts = $data['contacts'] ?? [];

            if (empty($contacts)) {
                break;
            }

            // Filter out contacts without email
            foreach ($contacts as $contact) {
                if (!empty($contact['email'])) {
                    $allContacts[] = $contact;
                }
            }

            $total = $data['total'] ?? 0;

            // Check for next page cursor
            $meta = $data['meta'] ?? [];
            $nextPageUrl = $meta['nextPageUrl'] ?? '';
            $searchAfter = $meta['searchAfter'] ?? null;

            if (empty($searchAfter) && empty($nextPageUrl)) {
                break;
            }

            // Safety cap
            if (count($allContacts) >= 5000) {
                cdms_log('INFO', 'GHL', 'Contact search capped at 5000');
                break;
            }
        }

        cdms_log('INFO', 'GHL', 'Advanced search complete', ['total' => count($allContacts)]);

        return [
            'contacts' => $allContacts,
            'total'    => count($allContacts),
            'error'    => null,
        ];
    }

    /**
     * Make an HTTP request to the GHL API.
     *
     * @param string     $method HTTP method (GET or POST)
     * @param string     $url    Full URL
     * @param array|null $body   Request body for POST
     * @return array{data: array|null, error: string|null, httpCode: int}
     */
    private function makeRequest(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Version: 2021-07-28',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        if ($method === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        cdms_log('DEBUG', 'GHL', "Response received", [
            'httpCode'     => $httpCode,
            'responseSize' => strlen($responseBody ?: ''),
        ]);

        if ($curlError) {
            cdms_log('ERROR', 'GHL', 'cURL error', ['error' => $curlError, 'url' => $url]);
            return ['data' => null, 'error' => 'cURL error: ' . $curlError, 'httpCode' => 0];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = "GHL API error (HTTP {$httpCode})";
            $decoded  = json_decode($responseBody, true);
            if (isset($decoded['message'])) {
                $errorMsg .= ': ' . $decoded['message'];
            } elseif ($responseBody) {
                $errorMsg .= ': ' . substr($responseBody, 0, 200);
            }
            cdms_log('ERROR', 'GHL', $errorMsg, [
                'httpCode'    => $httpCode,
                'responseBody' => substr($responseBody ?: '', 0, 500),
            ]);
            return ['data' => null, 'error' => $errorMsg, 'httpCode' => $httpCode];
        }

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['data' => null, 'error' => 'Failed to parse GHL response JSON', 'httpCode' => $httpCode];
        }

        return ['data' => $data, 'error' => null, 'httpCode' => $httpCode];
    }
}
