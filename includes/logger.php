<?php
/**
 * CDMS - Logger
 *
 * Writes timestamped entries to logs/cdms.log.
 * Auto-creates the logs directory and masks sensitive values.
 */

define('CDMS_LOG_DIR', __DIR__ . '/../logs');
define('CDMS_LOG_FILE', CDMS_LOG_DIR . '/cdms.log');

/**
 * Write a log entry.
 *
 * @param string $level   INFO, ERROR, DEBUG
 * @param string $source  e.g. GHL, CLOSE, SYNC
 * @param string $message Human-readable message
 * @param array  $context Optional key-value data to include
 */
function cdms_log(string $level, string $source, string $message, array $context = []): void
{
    if (!is_dir(CDMS_LOG_DIR)) {
        mkdir(CDMS_LOG_DIR, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] [{$level}] [{$source}] {$message}";

    if (!empty($context)) {
        $safe = array_map(function ($v) {
            if (is_string($v) && strlen($v) > 20) {
                // Mask anything that looks like a key/token
                if (preg_match('/^(pit-|api_|sk_|key_)/i', $v)) {
                    return substr($v, 0, 8) . '***' . substr($v, -4);
                }
            }
            return $v;
        }, $context);
        $entry .= ' | ' . json_encode($safe, JSON_UNESCAPED_SLASHES);
    }

    $entry .= PHP_EOL;

    file_put_contents(CDMS_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}
