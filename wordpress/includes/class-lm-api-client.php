<?php
/**
 * License Manager API Client
 *
 * Reusable client for communicating with the License Manager External API.
 * Can be used independently in any WordPress plugin or theme.
 *
 * Usage:
 *   $client = new LM_API_Client('https://license.example.com', 'api-key', 'PRODUCT_ID');
 *   $result = $client->activate_license('XXXX-XXXX-XXXX', 'John Doe');
 */

if (! defined('ABSPATH')) {
    exit;
}

class LM_API_Client
{
    private string $api_url;

    private string $api_key;

    private string $product_id;

    private int $timeout;

    public function __construct(string $api_url, string $api_key, string $product_id, int $timeout = 30)
    {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
        $this->product_id = $product_id;
        $this->timeout = $timeout;
    }

    /**
     * Check API connectivity.
     *
     * @return array{is_active: bool, message: string}
     */
    public function check_connection(): array
    {
        return $this->request('GET', '/api/external/connection-check');
    }

    /**
     * Activate a license.
     *
     * @param string $license_code License code (e.g., XXXX-XXXX-XXXX-XXXX)
     * @param string $client_name  Buyer/customer name
     * @param string $verify_type  "non_envato" or "envato"
     * @return array{is_active: bool, message: string, lic_response: ?string, data: ?array}
     */
    public function activate_license(string $license_code, string $client_name, string $verify_type = 'non_envato'): array
    {
        return $this->request('POST', '/api/external/license/activate', [
            'product_id' => $this->product_id,
            'license_code' => $license_code,
            'client_name' => $client_name,
            'verify_type' => $verify_type,
        ]);
    }

    /**
     * Verify a license using saved license data.
     *
     * @param string $license_data Encrypted license data from activation
     * @return array{is_active: bool, message: string}
     */
    public function verify_license(string $license_data): array
    {
        return $this->request('POST', '/api/external/license/verify', [
            'product_id' => $this->product_id,
            'license_data' => $license_data,
        ]);
    }

    /**
     * Deactivate a license.
     *
     * @param string $license_data Encrypted license data from activation
     * @return array{is_active: bool, message: string}
     */
    public function deactivate_license(string $license_data): array
    {
        return $this->request('POST', '/api/external/license/deactivate', [
            'product_id' => $this->product_id,
            'license_data' => $license_data,
        ]);
    }

    /**
     * Check if a product update is available.
     *
     * @param string $current_version Current installed version (e.g., "1.0.0")
     * @return array{is_active: bool, update_available: bool, version: ?string, changelog: ?string}
     */
    public function check_update(string $current_version): array
    {
        return $this->request('POST', '/api/external/update/check', [
            'product_id' => $this->product_id,
            'current_version' => $current_version,
        ]);
    }

    /**
     * Get the latest version info.
     *
     * @return array{data: ?array}
     */
    public function get_latest_version(): array
    {
        return $this->request('POST', '/api/external/update/latest', [
            'product_id' => $this->product_id,
        ]);
    }

    /**
     * Get the download URL for an update.
     *
     * @param string $version_id Version ID (vid) from update check
     * @param string $type       "main" or "sql"
     * @return string
     */
    public function get_update_download_url(string $version_id, string $type = 'main'): string
    {
        return $this->api_url . '/api/external/update/' . $version_id . '/download/' . $type;
    }

    /**
     * Get the product ID.
     *
     * @return string
     */
    public function get_product_id(): string
    {
        return $this->product_id;
    }

    /**
     * Check if the client is configured with required credentials.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return ! empty($this->api_url) && ! empty($this->api_key) && ! empty($this->product_id);
    }

    /**
     * Make an API request using WordPress HTTP API.
     *
     * @param string     $method   HTTP method (GET, POST)
     * @param string     $endpoint API endpoint path
     * @param array|null $data     Request body data
     * @return array Decoded response
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->api_url . $endpoint;

        $headers = [
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->api_key,
            'X-URL' => home_url(),
            'X-IP' => $this->get_server_ip(),
        ];

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
            'sslverify' => true,
        ];

        if ($data !== null && $method === 'POST') {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'is_active' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'is_active' => false,
                'message' => 'Invalid response from server.',
            ];
        }

        return $decoded;
    }

    /**
     * Get the server IP address.
     *
     * @return string
     */
    private function get_server_ip(): string
    {
        if (! empty($_SERVER['SERVER_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR']));
        }

        $hostname = parse_url(home_url(), PHP_URL_HOST);

        if ($hostname) {
            $ip = gethostbyname($hostname);

            if ($ip !== $hostname) {
                return $ip;
            }
        }

        return '127.0.0.1';
    }
}
