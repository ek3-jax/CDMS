<?php
/**
 * CDMS - GoHighLevel API Client
 *
 * Handles fetching contacts from GoHighLevel using API v2.
 * Uses GET /contacts/ with query parameters.
 */

require_once __DIR__ . '/../includes/config.php';

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
    }

    /**
     * Fetch all contacts from GHL, handling pagination automatically.
     *
     * @return array{contacts: array, total: int, error: string|null}
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

            // Use the last contact for cursor-based pagination
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
     *
     * @param int         $page          Unused (kept for interface compat), pagination is cursor-based
     * @param string      $query         Optional search query
     * @param string|null $startAfterId  Contact ID to start after (cursor)
     * @param string|null $startAfter    Timestamp to start after (cursor)
     * @return array{contacts: array, total: int, error: string|null}
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

        $response = $this->makeRequest($url);

        if ($response['error']) {
            return [
                'contacts' => [],
                'total'    => 0,
                'error'    => $response['error'],
            ];
        }

        $data = $response['data'];

        return [
            'contacts' => $data['contacts'] ?? [],
            'total'    => $data['total'] ?? 0,
            'error'    => null,
        ];
    }

    /**
     * Fetch a single contact by ID.
     *
     * @param string $contactId
     * @return array{contact: array|null, error: string|null}
     */
    public function getContact(string $contactId): array
    {
        $url = $this->baseUrl . '/contacts/' . urlencode($contactId);

        $response = $this->makeRequest($url);

        if ($response['error']) {
            return ['contact' => null, 'error' => $response['error']];
        }

        return [
            'contact' => $response['data']['contact'] ?? $response['data'],
            'error'   => null,
        ];
    }

    /**
     * Make a GET request to the GHL API.
     *
     * @param string $url Full URL
     * @return array{data: array|null, error: string|null, httpCode: int}
     */
    private function makeRequest(string $url): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
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
            return ['data' => null, 'error' => $errorMsg, 'httpCode' => $httpCode];
        }

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['data' => null, 'error' => 'Failed to parse GHL response JSON', 'httpCode' => $httpCode];
        }

        return ['data' => $data, 'error' => null, 'httpCode' => $httpCode];
    }
}
