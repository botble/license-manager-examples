#!/usr/bin/env php
<?php

/**
 * License Manager API - Sample Application
 *
 * This sample demonstrates how to integrate with the License Manager API.
 *
 * REQUIREMENTS:
 * - PHP 7.4+ with cURL extension
 *
 * QUICK START:
 * 1. Edit the configuration below with your credentials
 * 2. Run: php sample-app.php
 *
 * API ENDPOINTS:
 * - GET  /api/external/connection-check                     - Test API connectivity
 * - POST /api/external/license/activate                     - Activate a license
 * - POST /api/external/license/verify                       - Verify license validity
 * - POST /api/external/license/deactivate                   - Deactivate a license
 * - POST /api/external/update/check                         - Check for product updates
 * - POST /api/external/update/{version}/download/{type}     - Download update file
 *
 * REQUIRED HEADERS:
 * - Content-Type: application/json
 * - X-API-KEY: {your-api-key}
 * - X-API-URL: {your-application-url}
 * - X-API-IP: {your-server-ip}
 * - X-API-LANGUAGE: {locale, e.g. "en"}
 *
 * TROUBLESHOOTING:
 * - Connection failed: Check API_URL and API_KEY
 * - Invalid license: Verify LICENSE_CODE and PRODUCT_ID
 * - Blocked license: License may be blocked or expired
 * - Max activations: License parallel uses limit reached
 */

// ============================================================================
// CONFIGURATION - Edit these values with your credentials
// ============================================================================

$config = [
    // Your License Manager server URL
    'api_url' => 'https://your-license-server.com',

    // Your External API Key (from API Settings page)
    'api_key' => 'your-api-key-here',

    // Product Reference ID (from Products page)
    'product_id' => 'ABC12345',

    // Your license code
    'license_code' => 'XXXX-XXXX-XXXX-XXXX',

    // Client name (buyer/customer name)
    'client_name' => 'John Doe',

    // Your application URL (sent in X-API-URL header)
    'app_url' => 'http://sample-app.local',
];

// ============================================================================
// DO NOT EDIT BELOW THIS LINE
// ============================================================================

$apiUrl = rtrim($config['api_url'], '/');
$apiKey = $config['api_key'];
$productId = $config['product_id'];
$licenseCode = $config['license_code'];
$clientName = $config['client_name'];
$appUrl = $config['app_url'];
$licenseFile = __DIR__ . '/.license';
$serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()) ?: '127.0.0.1';

define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('CYAN', "\033[36m");
define('RESET', "\033[0m");

function printHeader(): void
{
    echo "\n";
    echo CYAN . "========================================\n";
    echo "  License Manager API - Sample App\n";
    echo '========================================' . RESET . "\n\n";
}

function printMenu(): void
{
    echo "Choose an operation:\n";
    echo "  1. Check API Connection\n";
    echo "  2. Activate License\n";
    echo "  3. Verify License\n";
    echo "  4. Deactivate License\n";
    echo "  5. Check for Updates\n";
    echo "  6. Download Update\n";
    echo "  7. Exit\n\n";
    echo 'Enter choice (1-7): ';
}

function callApi(string $method, string $endpoint, ?array $data = null): array
{
    global $apiUrl, $apiKey, $appUrl, $serverIp;

    $curl = curl_init();
    $url = $apiUrl . $endpoint;

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey,
            'X-API-URL: ' . $appUrl,
            'X-API-IP: ' . $serverIp,
            'X-API-LANGUAGE: en',
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode($response, true);
        return [
            'error' => ($decoded['message'] ?? "HTTP $httpCode"),
            'data' => $decoded,
            'http_code' => $httpCode,
        ];
    }

    return [
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
    ];
}

function checkConnection(): void
{
    echo "\n" . YELLOW . 'Checking API connection...' . RESET . "\n";

    $result = callApi('GET', '/api/external/connection-check');

    if ($result['http_code'] === 200 && ($result['data']['is_active'] ?? false)) {
        echo GREEN . 'Connection successful!' . RESET . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'N/A') . "\n";
    } else {
        echo RED . 'Connection failed!' . RESET . "\n";
        echo '  HTTP Code: ' . $result['http_code'] . "\n";
        echo '  Error: ' . ($result['error'] ?? $result['data']['message'] ?? 'Unknown') . "\n";
    }
}

function activateLicense(): void
{
    global $productId, $licenseCode, $clientName, $licenseFile;

    echo "\n" . YELLOW . 'Activating license...' . RESET . "\n";

    $result = callApi('POST', '/api/external/license/activate', [
        'product_id' => $productId,
        'license_code' => $licenseCode,
        'client_name' => $clientName,
        'verify_type' => 'non_envato',
    ]);

    if ($result['http_code'] === 200 && ($result['data']['is_active'] ?? false)) {
        echo GREEN . 'License activated!' . RESET . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'N/A') . "\n";

        $licenseData = $result['data']['lic_response'] ?? $result['data']['data']['license_data'] ?? null;

        if ($licenseData) {
            file_put_contents($licenseFile, $licenseData);
            chmod($licenseFile, 0600);
            echo "  License data saved to: .license\n";
        }
    } else {
        echo RED . 'Activation failed!' . RESET . "\n";
        echo '  HTTP Code: ' . $result['http_code'] . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'Unknown error') . "\n";
    }
}

function verifyLicense(): void
{
    global $productId, $licenseFile;

    echo "\n" . YELLOW . 'Verifying license...' . RESET . "\n";

    if (! file_exists($licenseFile)) {
        echo RED . 'No license file found. Activate a license first.' . RESET . "\n";

        return;
    }

    $licenseData = file_get_contents($licenseFile);

    $result = callApi('POST', '/api/external/license/verify', [
        'product_id' => $productId,
        'license_data' => $licenseData,
    ]);

    if ($result['http_code'] === 200 && ($result['data']['is_active'] ?? false)) {
        echo GREEN . 'License is valid!' . RESET . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'N/A') . "\n";
    } else {
        echo RED . 'License is invalid!' . RESET . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'Unknown error') . "\n";
    }
}

function deactivateLicense(): void
{
    global $productId, $licenseFile;

    echo "\n" . YELLOW . 'Deactivating license...' . RESET . "\n";

    if (! file_exists($licenseFile)) {
        echo RED . 'No license file found. Activate a license first.' . RESET . "\n";

        return;
    }

    $licenseData = file_get_contents($licenseFile);

    $result = callApi('POST', '/api/external/license/deactivate', [
        'product_id' => $productId,
        'license_data' => $licenseData,
    ]);

    if ($result['http_code'] === 200 && ($result['data']['is_active'] ?? false)) {
        echo GREEN . 'License deactivated!' . RESET . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'N/A') . "\n";

        @unlink($licenseFile);
        echo "  License file removed.\n";
    } else {
        echo RED . 'Deactivation failed!' . RESET . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'Unknown error') . "\n";
    }
}

function checkForUpdate(): void
{
    global $productId;

    echo "\n" . YELLOW . 'Checking for updates...' . RESET . "\n";

    echo 'Enter current version (e.g. 1.0.0): ';
    $currentVersion = trim(fgets(STDIN));

    if (! $currentVersion) {
        echo RED . 'Version is required.' . RESET . "\n";

        return;
    }

    $result = callApi('POST', '/api/external/update/check', [
        'product_id' => $productId,
        'current_version' => $currentVersion,
    ]);

    if ($result['http_code'] === 200) {
        if ($result['data']['update_available'] ?? false) {
            echo GREEN . 'Update available!' . RESET . "\n";
            echo '  Version: ' . ($result['data']['version'] ?? 'N/A') . "\n";
            echo '  Update ID: ' . ($result['data']['update_id'] ?? 'N/A') . "\n";
        } else {
            echo CYAN . 'Already up to date.' . RESET . "\n";
        }

        echo '  ' . json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo RED . 'Update check failed!' . RESET . "\n";
        echo '  HTTP Code: ' . $result['http_code'] . "\n";
        echo '  Message: ' . ($result['data']['message'] ?? 'Unknown error') . "\n";
    }
}

function downloadUpdate(): void
{
    global $apiUrl, $apiKey, $appUrl, $serverIp, $licenseFile;

    echo "\n" . YELLOW . 'Downloading update...' . RESET . "\n";

    echo 'Enter Version ID (from update check): ';
    $versionId = trim(fgets(STDIN));

    if (! $versionId) {
        echo RED . 'Version ID is required.' . RESET . "\n";

        return;
    }

    echo 'Enter type (main/sql) [main]: ';
    $type = trim(fgets(STDIN)) ?: 'main';

    $data = [];

    if (file_exists($licenseFile)) {
        $data['license_data'] = file_get_contents($licenseFile);
    }

    $url = $apiUrl . '/api/external/update/' . urlencode($versionId) . '/download/' . urlencode($type);

    $extension = $type === 'main' ? 'zip' : 'sql';
    $filename = "update_{$versionId}.{$extension}";
    $outputPath = __DIR__ . '/' . $filename;

    $fp = fopen($outputPath, 'wb');
    if (! $fp) {
        echo RED . "Download failed: unable to open output file." . RESET . "\n";

        return;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey,
            'X-API-URL: ' . $appUrl,
            'X-API-IP: ' . $serverIp,
            'X-API-LANGUAGE: en',
        ],
        CURLOPT_FILE => $fp,
        CURLOPT_HEADER => false,
    ]);

    curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($fp);

    if ($error) {
        @unlink($outputPath);
        echo RED . "Download failed: $error" . RESET . "\n";

        return;
    }

    if ($httpCode !== 200) {
        @unlink($outputPath);

        if ($httpCode === 401) {
            echo RED . 'Unauthorized! License validation failed.' . RESET . "\n";
        } elseif ($httpCode === 404) {
            echo RED . 'Not found! Version ID or file type is invalid.' . RESET . "\n";
        } else {
            echo RED . "Download failed (HTTP $httpCode)" . RESET . "\n";
        }

        return;
    }

    chmod($outputPath, 0600);

    echo GREEN . 'Update downloaded!' . RESET . "\n";
    echo "  Saved to: $filename\n";
    echo '  Size: ' . number_format(filesize($outputPath)) . " bytes\n";
}

printHeader();

while (true) {
    printMenu();
    $choice = trim(fgets(STDIN));

    switch ($choice) {
        case '1':
            checkConnection();

            break;
        case '2':
            activateLicense();

            break;
        case '3':
            verifyLicense();

            break;
        case '4':
            deactivateLicense();

            break;
        case '5':
            checkForUpdate();

            break;
        case '6':
            downloadUpdate();

            break;
        case '7':
            echo "\nGoodbye!\n";
            exit(0);
        default:
            echo RED . 'Invalid choice. Please enter 1-7.' . RESET . "\n";
    }

    echo "\n";
}
