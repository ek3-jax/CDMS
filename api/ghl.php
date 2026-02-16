<?php
/**
 * CDMS - GoHighLevel API Client
 *
 * Handles fetching contacts from GoHighLevel using API v2.
 * Uses the Search Contacts endpoint (POST /contacts/search) as recommended
 * since GET /contacts/ is deprecated.
 */

require_once __DIR__ . '/../includes/config.php';

class GHLClient
{
    private string $apiKey;
    private string $locationId;
    private string $baseUrl;
    private string $apiVersion;

    public function __construct()
    {
        $this->apiKey     = GHL_API_KEY;
        $this->locationId = GHL_LOCATION_ID;
        $this->baseUrl    = GHL_BASE_URL;
        $this->apiVersion = GHL_API_VERSION;
    }

    /**
     * Fetch all contacts from GHL, handling pagination automatically.
     *
     * @return array{contacts: array, total: int, error: string|null}
     */
    public function fetchAllContacts(): array
    {
        $allContacts = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $result = $this->searchContacts($page);

            if ($result['error']) {
                return [
                    'contacts' => $allContacts,
                    'total'    => count($allContacts),
                    'error'    => $result['error'],
                ];
            }

            $contacts = $result['contacts'] ?? [];
            $allContacts = array_merge($allContacts, $contacts);

            // Check if there are more pages
            $total = $result['total'] ?? 0;
            if (count($contacts) < GHL_PAGE_LIMIT || count($allContacts) >= $total) {
                $hasMore = false;
            } else {
                $page++;
            }
        }

        return [
            'contacts' => $allContacts,
            'total'    => count($allContacts),
            'error'    => null,
        ];
    }

    /**
     * Search contacts with pagination.
     *
     * @param int    $page  Page number (1-based)
     * @param string $query Optional search query
     * @return array{contacts: array, total: int, error: string|null}
     */
    public function searchContacts(int $page = 1, string $query = ''): array
    {
        $url = $this->baseUrl . '/contacts/search';

        $body = [
            'locationId' => $this->locationId,
            'page'       => $page,
            'pageLimit'  => GHL_PAGE_LIMIT,
        ];

        if (!empty($query)) {
            $body['query'] = $query;
        }

        $response = $this->makeRequest('POST', $url, $body);

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
     * Make an HTTP request to the GHL API.
     *
     * @param string     $method  HTTP method
     * @param string     $url     Full URL
     * @param array|null $body    Request body (for POST/PUT)
     * @return array{data: array|null, error: string|null, httpCode: int}
     */
    private function makeRequest(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'Version: ' . $this->apiVersion,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

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
