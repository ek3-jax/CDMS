<?php
/**
 * CDMS - Cydian Data Management System
 * Configuration File
 *
 * Loads API credentials from the server config path (CAMS-style array format).
 * Falls back to local values if the server config is not available.
 */

// Attempt to load from server config path
$serverConfigPath = '/home2/jmutygmy/config/CDMS-Config.php';
if (file_exists($serverConfigPath)) {
    $config = require $serverConfigPath;
} else {
    // Local/development fallback - replace with your actual keys
    $config = [
        'GHL_api_key'      => 'YOUR_GHL_PRIVATE_INTEGRATION_TOKEN',
        'closeCRM_api_key'  => 'YOUR_CLOSE_API_KEY',
    ];
}

// Map config array values to constants
define('GHL_API_KEY',     $config['GHL_api_key']     ?? '');
define('GHL_LOCATION_ID', $config['GHL_location_id'] ?? '9bOYUaxH07bDEiymvTNd');
define('CLOSE_API_KEY',   $config['closeCRM_api_key'] ?? '');

// API Base URLs
define('GHL_BASE_URL',    'https://services.leadconnectorhq.com');
define('GHL_API_VERSION', '2021-07-28');
define('CLOSE_BASE_URL',  'https://api.close.com/api/v1');

// Sync settings
define('GHL_PAGE_LIMIT',  100);  // Max contacts per GHL API request
define('SYNC_BATCH_SIZE', 10);   // Contacts to push to Close per batch
