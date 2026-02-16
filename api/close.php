<?php
/**
 * CDMS - Close CRM API Client
 *
 * Handles pushing contacts to Close CRM.
 * Uses the Close REST API v1 with Basic Auth (API key).
 *
 * In Close, contacts belong to Leads. When creating a contact without
 * a lead_id, Close automatically creates a new Lead named after the contact.
 * We use POST /api/v1/lead/ to create leads with embedded contacts.
 */

require_once __DIR__ . '/../includes/config.php';

class CloseClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = CLOSE_API_KEY;
        $this->baseUrl = CLOSE_BASE_URL;
    }

    /**
     * Create a lead in Close with an embedded contact.
     *
     * Maps a GHL contact to a Close lead + contact structure.
     *
     * @param array $ghlContact A contact object from GoHighLevel
     * @return array{lead: array|null, error: string|null}
     */
    public function createLeadFromGHLContact(array $ghlContact): array
    {
        $contactName = trim(
            ($ghlContact['firstName'] ?? '') . ' ' . ($ghlContact['lastName'] ?? '')
        );
        if (empty($contactName)) {
            $contactName = $ghlContact['name'] ?? 'Unknown Contact';
        }

        $companyName = $ghlContact['companyName'] ?? $contactName;

        // Build contact emails array
        $emails = [];
        if (!empty($ghlContact['email'])) {
            $emails[] = [
                'type'  => 'office',
                'email' => $ghlContact['email'],
            ];
        }

        // Build contact phones array
        $phones = [];
        if (!empty($ghlContact['phone'])) {
            $phones[] = [
                'type'  => 'office',
                'phone' => $ghlContact['phone'],
            ];
        }

        // Build contact URLs array
        $urls = [];
        if (!empty($ghlContact['website'])) {
            $urls[] = [
                'type' => 'url',
                'url'  => $ghlContact['website'],
            ];
        }

        // Build the contact object
        $contact = [
            'name'   => $contactName,
            'emails' => $emails,
            'phones' => $phones,
            'urls'   => $urls,
        ];

        if (!empty($ghlContact['title'])) {
            $contact['title'] = $ghlContact['title'];
        }

        // Build address array
        $addresses = [];
        $address = [];
        if (!empty($ghlContact['address1']))   $address['address_1'] = $ghlContact['address1'];
        if (!empty($ghlContact['city']))        $address['city']      = $ghlContact['city'];
        if (!empty($ghlContact['state']))       $address['state']     = $ghlContact['state'];
        if (!empty($ghlContact['postalCode']))  $address['zipcode']   = $ghlContact['postalCode'];
        if (!empty($ghlContact['country']))     $address['country']   = $ghlContact['country'];
        if (!empty($address)) {
            $addresses[] = $address;
        }

        // Build the lead payload
        $leadData = [
            'name'      => $companyName,
            'contacts'  => [$contact],
        ];

        if (!empty($addresses)) {
            $leadData['addresses'] = $addresses;
        }

        // Add source tracking
        $leadData['description'] = 'Synced from GoHighLevel via CDMS';

        if (!empty($ghlContact['source'])) {
            $leadData['description'] .= ' | Source: ' . $ghlContact['source'];
        }

        return $this->createLead($leadData);
    }

    /**
     * Create a lead in Close CRM.
     *
     * @param array $leadData Lead payload
     * @return array{lead: array|null, error: string|null}
     */
    public function createLead(array $leadData): array
    {
        $url = $this->baseUrl . '/lead/';

        $response = $this->makeRequest('POST', $url, $leadData);

        if ($response['error']) {
            return ['lead' => null, 'error' => $response['error']];
        }

        return ['lead' => $response['data'], 'error' => null];
    }

    /**
     * Search for existing leads by email to avoid duplicates.
     *
     * @param string $email Email to search for
     * @return array{leads: array, error: string|null}
     */
    public function searchLeadByEmail(string $email): array
    {
        $query = urlencode('email:' . $email);
        $url   = $this->baseUrl . '/lead/?query=' . $query;

        $response = $this->makeRequest('GET', $url);

        if ($response['error']) {
            return ['leads' => [], 'error' => $response['error']];
        }

        return [
            'leads' => $response['data']['data'] ?? [],
            'error' => null,
        ];
    }

    /**
     * Make an HTTP request to the Close API.
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
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $this->apiKey . ':',  // Basic Auth: API key as username, empty password
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
            $errorMsg = "Close API error (HTTP {$httpCode})";
            $decoded  = json_decode($responseBody, true);
            if (isset($decoded['error'])) {
                $errorMsg .= ': ' . $decoded['error'];
            } elseif ($responseBody) {
                $errorMsg .= ': ' . substr($responseBody, 0, 200);
            }
            return ['data' => null, 'error' => $errorMsg, 'httpCode' => $httpCode];
        }

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['data' => null, 'error' => 'Failed to parse Close response JSON', 'httpCode' => $httpCode];
        }

        return ['data' => $data, 'error' => null, 'httpCode' => $httpCode];
    }
}
