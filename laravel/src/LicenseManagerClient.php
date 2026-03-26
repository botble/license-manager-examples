<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LicenseManagerClient
{
    private string $apiUrl;

    private string $apiKey;

    private string $productId;

    private int $timeout;

    private bool $verifySsl;

    private int $cacheTtl;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('license-manager.api_url', ''), '/');
        $this->apiKey = config('license-manager.api_key', '');
        $this->productId = config('license-manager.product_id', '');
        $this->timeout = config('license-manager.timeout', 30);
        $this->verifySsl = config('license-manager.verify_ssl', true);
        $this->cacheTtl = config('license-manager.cache_ttl', 3600);
    }

    /**
     * Check API connectivity.
     *
     * @return array{is_active: bool, message: string}
     */
    public function checkConnection(): array
    {
        return $this->request('GET', '/api/external/connection-check');
    }

    /**
     * Activate a license.
     *
     * On success, the encrypted license data is automatically stored
     * in the `license_manager.data` cache/file for later verification.
     *
     * @return array{is_active: bool, message: string, lic_response: ?string, data: ?array}
     */
    public function activateLicense(string $licenseCode, string $clientName, string $verifyType = 'non_envato'): array
    {
        $result = $this->request('POST', '/api/external/license/activate', [
            'product_id' => $this->productId,
            'license_code' => $licenseCode,
            'client_name' => $clientName,
            'verify_type' => $verifyType,
        ]);

        if (! empty($result['is_active'])) {
            $licenseData = $result['lic_response'] ?? $result['data']['license_data'] ?? null;

            if ($licenseData) {
                $this->storeLicenseData($licenseData);
            }
        }

        return $result;
    }

    /**
     * Verify the current license.
     *
     * Uses cached verification result if available.
     *
     * @return array{is_active: bool, message: string}
     */
    public function verifyLicense(?string $licenseData = null): array
    {
        $licenseData = $licenseData ?? $this->getLicenseData();

        if (empty($licenseData)) {
            return [
                'is_active' => false,
                'message' => 'No license data found. Activate a license first.',
            ];
        }

        $cacheKey = 'license_manager.verification.' . md5($licenseData);

        if ($this->cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->request('POST', '/api/external/license/verify', [
            'product_id' => $this->productId,
            'license_data' => $licenseData,
        ]);

        if ($this->cacheTtl > 0) {
            Cache::put($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Deactivate the current license.
     *
     * @return array{is_active: bool, message: string}
     */
    public function deactivateLicense(?string $licenseData = null): array
    {
        $licenseData = $licenseData ?? $this->getLicenseData();

        if (empty($licenseData)) {
            return [
                'is_active' => false,
                'message' => 'No license data found.',
            ];
        }

        $result = $this->request('POST', '/api/external/license/deactivate', [
            'product_id' => $this->productId,
            'license_data' => $licenseData,
        ]);

        if (! empty($result['is_active'])) {
            $this->removeLicenseData();
        }

        return $result;
    }

    /**
     * Check if a product update is available.
     *
     * @return array{is_active: bool, update_available: bool, version: ?string, changelog: ?string}
     */
    public function checkUpdate(string $currentVersion): array
    {
        return $this->request('POST', '/api/external/update/check', [
            'product_id' => $this->productId,
            'current_version' => $currentVersion,
        ]);
    }

    /**
     * Get the latest version info.
     *
     * @return array{data: ?array}
     */
    public function getLatestVersion(): array
    {
        return $this->request('POST', '/api/external/update/latest', [
            'product_id' => $this->productId,
        ]);
    }

    /**
     * Download an update file.
     *
     * Sends license_data in the POST body for authenticated downloads.
     * Returns the raw response for file saving.
     *
     * @return array{success: bool, path: ?string, message: string}
     */
    public function downloadUpdate(string $versionId, string $type = 'main', ?string $savePath = null): array
    {
        // Sanitize $type to prevent path traversal — only allow known safe values
        if (! in_array($type, ['main', 'sql'], true)) {
            return [
                'success' => false,
                'path' => null,
                'message' => 'Invalid update type.',
            ];
        }

        // Sanitize $versionId — allow only alphanumeric, dashes, underscores, and dots
        if (! preg_match('/^[a-zA-Z0-9_\-\.]+$/', $versionId)) {
            return [
                'success' => false,
                'path' => null,
                'message' => 'Invalid version ID.',
            ];
        }

        $licenseData = $this->getLicenseData();

        $body = [];

        if (! empty($licenseData)) {
            $body['license_data'] = $licenseData;
        }

        $url = $this->apiUrl . '/api/external/update/' . $versionId . '/download/' . $type;

        $pending = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-API-URL' => config('app.url'),
                'X-API-IP' => $this->getServerIp(),
                'X-API-LANGUAGE' => config('app.locale', 'en'),
            ]);

        if (! $this->verifySsl) {
            $pending = $pending->withoutVerifying();
        }

        try {
            $response = $pending->post($url, $body);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'path' => null,
                    'message' => 'Download failed with HTTP ' . $response->status(),
                ];
            }

            $savePath = $savePath ?? storage_path('app/updates/' . $type . '_' . $versionId . '.' . ($type === 'main' ? 'zip' : 'sql'));

            $dir = dirname($savePath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($savePath, $response->body(), LOCK_EX);
            chmod($savePath, 0600);

            return [
                'success' => true,
                'path' => $savePath,
                'message' => 'Update downloaded successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'path' => null,
                'message' => 'Download failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if the client is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiUrl) && ! empty($this->apiKey) && ! empty($this->productId);
    }

    /**
     * Check if a valid license exists (uses cache).
     */
    public function isLicensed(): bool
    {
        $result = $this->verifyLicense();

        return ! empty($result['is_active']);
    }

    /**
     * Store encrypted license data with restrictive permissions.
     */
    private function storeLicenseData(string $data): void
    {
        $path = storage_path('app/.license');
        file_put_contents($path, $data, LOCK_EX);
        chmod($path, 0600);
    }

    /**
     * Resolve the server IP in both web and CLI contexts.
     */
    private function getServerIp(): string
    {
        return $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname() ?: 'localhost') ?: '127.0.0.1';
    }

    /**
     * Retrieve stored license data.
     */
    private function getLicenseData(): ?string
    {
        $path = storage_path('app/.license');

        if (! file_exists($path)) {
            return null;
        }

        $data = file_get_contents($path);

        return $data ?: null;
    }

    /**
     * Remove stored license data.
     */
    private function removeLicenseData(): void
    {
        $path = storage_path('app/.license');
        $data = $this->getLicenseData();

        if (file_exists($path)) {
            unlink($path);
        }

        Cache::forget('license_manager.verification.' . md5($data ?? ''));
    }

    /**
     * Make an HTTP request to the License Manager API.
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'is_active' => false,
                'message' => 'License Manager is not configured. Check your .env file.',
            ];
        }

        $url = $this->apiUrl . $endpoint;

        $pending = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-API-URL' => config('app.url'),
                'X-API-IP' => $this->getServerIp(),
                'X-API-LANGUAGE' => config('app.locale', 'en'),
            ]);

        if (! $this->verifySsl) {
            $pending = $pending->withoutVerifying();
        }

        try {
            $response = $method === 'GET'
                ? $pending->get($url)
                : $pending->post($url, $data ?? []);

            if ($response->failed()) {
                return [
                    'is_active' => false,
                    'message' => 'API request failed with HTTP ' . $response->status(),
                ];
            }

            return $response->json() ?? [
                'is_active' => false,
                'message' => 'Empty response from server.',
            ];
        } catch (\Exception $e) {
            return [
                'is_active' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
            ];
        }
    }
}
