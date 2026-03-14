#!/usr/bin/env php
<?php

/**
 * License Manager Internal API - Sample Application
 *
 * This sample demonstrates how to use the Internal API for server-to-server
 * integration with License Manager. Use this for backend systems that need
 * to manage products, licenses, and activations programmatically.
 *
 * REQUIREMENTS:
 * - PHP 7.4+ with cURL extension
 * - Internal API key (not external!)
 *
 * QUICK START:
 * 1. Edit the configuration below with your credentials
 * 2. Run: php sample-app-internal.php
 *
 * INTERNAL API ENDPOINTS:
 * - GET  /api/internal/connection-check              - Test connectivity
 * - GET  /api/internal/products                      - List all products
 * - GET  /api/internal/products/{id}                 - Get product details
 * - POST /api/internal/products                      - Create product
 * - PUT  /api/internal/products/{id}                 - Update product
 * - GET  /api/internal/product-licenses              - List all licenses
 * - POST /api/internal/product-licenses              - Create license
 * - GET  /api/internal/product-licenses/{id}         - Get license details
 * - PUT  /api/internal/product-licenses/{id}         - Update license
 * - POST /api/internal/blocked-product-licenses/{id} - Block license
 * - DELETE /api/internal/blocked-product-licenses/{id} - Unblock license
 *
 * REQUIRED HEADERS:
 * - Content-Type: application/json
 * - X-API-KEY: {your-internal-api-key}
 * - X-API-URL: {your-server-url}
 * - X-API-IP: {your-server-ip}
 * - X-API-LANGUAGE: {locale, e.g. "en"}
 *
 * NOTE: Internal API keys have elevated permissions. Keep them secure!
 */

// ============================================================================
// CONFIGURATION - Edit these values with your credentials
// ============================================================================

$config = [
    // Your License Manager server URL
    'api_url' => 'https://your-license-server.com',

    // Your INTERNAL API Key (from API Settings page - must be internal type!)
    'api_key' => 'your-internal-api-key-here',

    // Your backend server URL (for X-API-URL header)
    'server_url' => 'https://your-backend-server.com',

    // Your backend server IP (for X-API-IP header)
    'server_ip' => '127.0.0.1',
];

// ============================================================================
// DO NOT EDIT BELOW THIS LINE
// ============================================================================

$apiUrl = rtrim($config['api_url'], '/');
$apiKey = $config['api_key'];
$serverUrl = $config['server_url'];
$serverIp = $config['server_ip'];

define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('CYAN', "\033[36m");
define('BOLD', "\033[1m");
define('RESET', "\033[0m");

function printHeader(): void
{
    echo "\n";
    echo CYAN . "================================================\n";
    echo "  License Manager Internal API - Sample App\n";
    echo '================================================' . RESET . "\n\n";
}

function printMenu(): void
{
    echo BOLD . 'CONNECTION' . RESET . "\n";
    echo "  1. Check API Connection\n\n";

    echo BOLD . 'PRODUCTS' . RESET . "\n";
    echo "  2. List Products\n";
    echo "  3. Get Product Details\n";
    echo "  4. Create Product\n\n";

    echo BOLD . 'LICENSES' . RESET . "\n";
    echo "  5. List Licenses\n";
    echo "  6. Get License Details\n";
    echo "  7. Create License\n";
    echo "  8. Block/Unblock License\n\n";

    echo BOLD . 'OTHER' . RESET . "\n";
    echo "  0. Exit\n\n";

    echo 'Enter choice: ';
}

function callApi(string $method, string $endpoint, ?array $data = null): array
{
    global $apiUrl, $apiKey, $serverUrl, $serverIp;

    $curl = curl_init();
    $url = $apiUrl . $endpoint;

    $headers = [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey,
        'X-API-URL: ' . $serverUrl,
        'X-API-IP: ' . $serverIp,
        'X-API-LANGUAGE: en',
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    switch ($method) {
        case 'POST':
            curl_setopt($curl, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }

            break;
        case 'PUT':
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }

            break;
        case 'DELETE':
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

            break;
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }

    return [
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
    ];
}

function prompt(string $message, string $default = ''): string
{
    $defaultText = $default ? " [$default]" : '';
    echo $message . $defaultText . ': ';
    $input = trim(fgets(STDIN));

    return $input ?: $default;
}

function printResult(array $result): void
{
    if ($result['http_code'] >= 200 && $result['http_code'] < 300) {
        echo GREEN . "Success (HTTP {$result['http_code']})" . RESET . "\n";
    } else {
        echo RED . "Failed (HTTP {$result['http_code']})" . RESET . "\n";
    }

    if (isset($result['data'])) {
        echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

function checkConnection(): void
{
    echo "\n" . YELLOW . 'Checking API connection...' . RESET . "\n";
    $result = callApi('GET', '/api/internal/connection-check');
    printResult($result);
}

function listProducts(): void
{
    echo "\n" . YELLOW . 'Fetching products...' . RESET . "\n";
    $result = callApi('GET', '/api/internal/products');
    printResult($result);
}

function getProduct(): void
{
    echo "\n";
    $productId = prompt('Enter Product ID or Unique ID');

    if (! $productId) {
        echo RED . 'Product ID is required.' . RESET . "\n";

        return;
    }

    echo YELLOW . 'Fetching product details...' . RESET . "\n";
    $result = callApi('GET', '/api/internal/products/' . $productId);
    printResult($result);
}

function createProduct(): void
{
    echo "\n" . BOLD . 'Create New Product' . RESET . "\n";

    $name = prompt('Product name');
    $envatoId = prompt('Envato Item ID (optional)');

    if (! $name) {
        echo RED . 'Product name is required.' . RESET . "\n";

        return;
    }

    $data = [
        'name' => $name,
        'status' => 'published',
    ];

    if ($envatoId) {
        $data['envato_id'] = $envatoId;
    }

    echo YELLOW . 'Creating product...' . RESET . "\n";
    $result = callApi('POST', '/api/internal/products', $data);
    printResult($result);
}

function listLicenses(): void
{
    echo "\n" . YELLOW . 'Fetching licenses...' . RESET . "\n";
    $result = callApi('GET', '/api/internal/product-licenses');
    printResult($result);
}

function getLicense(): void
{
    echo "\n";
    $licenseId = prompt('Enter License ID');

    if (! $licenseId) {
        echo RED . 'License ID is required.' . RESET . "\n";

        return;
    }

    echo YELLOW . 'Fetching license details...' . RESET . "\n";
    $result = callApi('GET', '/api/internal/product-licenses/' . $licenseId);
    printResult($result);
}

function createLicense(): void
{
    echo "\n" . BOLD . 'Create New License' . RESET . "\n";

    $productId = prompt('Product ID or Unique ID');
    $licenseCode = prompt('License code (leave empty for auto-generate)');
    $client = prompt('Client name (optional)');
    $email = prompt('Client email (optional)');
    $parallelUses = prompt('Parallel uses limit', '1');

    if (! $productId) {
        echo RED . 'Product ID is required.' . RESET . "\n";

        return;
    }

    $data = [
        'product_id' => $productId,
        'parallel_uses' => (int) $parallelUses,
    ];

    if ($licenseCode) {
        $data['license_code'] = $licenseCode;
    }
    if ($client) {
        $data['client'] = $client;
    }
    if ($email) {
        $data['client_email'] = $email;
    }

    echo YELLOW . 'Creating license...' . RESET . "\n";
    $result = callApi('POST', '/api/internal/product-licenses', $data);
    printResult($result);
}

function toggleBlockLicense(): void
{
    echo "\n" . BOLD . 'Block/Unblock License' . RESET . "\n";

    $licenseId = prompt('Enter License ID');
    $action = prompt('Action (block/unblock)', 'block');

    if (! $licenseId) {
        echo RED . 'License ID is required.' . RESET . "\n";

        return;
    }

    if ($action === 'block') {
        echo YELLOW . 'Blocking license...' . RESET . "\n";
        $result = callApi('POST', '/api/internal/blocked-product-licenses/' . $licenseId);
    } else {
        echo YELLOW . 'Unblocking license...' . RESET . "\n";
        $result = callApi('DELETE', '/api/internal/blocked-product-licenses/' . $licenseId);
    }

    printResult($result);
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
            listProducts();

            break;
        case '3':
            getProduct();

            break;
        case '4':
            createProduct();

            break;
        case '5':
            listLicenses();

            break;
        case '6':
            getLicense();

            break;
        case '7':
            createLicense();

            break;
        case '8':
            toggleBlockLicense();

            break;
        case '0':
            echo "\nGoodbye!\n";
            exit(0);
        default:
            echo RED . 'Invalid choice.' . RESET . "\n";
    }

    echo "\n";
}
