# License Manager - Laravel Integration Example

A Laravel service class and Artisan commands demonstrating how to integrate with the License Manager API in a Laravel application.

## Files

| File | Description |
|------|-------------|
| `config/license-manager.php` | Configuration file |
| `src/LicenseManagerClient.php` | API client service class |
| `src/LicenseManagerServiceProvider.php` | Service provider for binding and config |
| `src/Commands/LicenseActivateCommand.php` | Artisan command to activate a license |
| `src/Commands/LicenseVerifyCommand.php` | Artisan command to verify a license |
| `src/Commands/LicenseDeactivateCommand.php` | Artisan command to deactivate a license |
| `src/Commands/LicenseStatusCommand.php` | Artisan command to show license status |
| `src/Middleware/VerifyLicense.php` | HTTP middleware to check license validity |

## Installation

1. Copy the files into your Laravel application
2. Register the service provider in `bootstrap/providers.php`:

```php
return [
    // ...
    App\Providers\LicenseManagerServiceProvider::class,
];
```

3. Publish the configuration:

```bash
php artisan vendor:publish --tag=license-manager-config
```

4. Add credentials to your `.env` file:

```env
LICENSE_MANAGER_API_URL=https://license.example.com
LICENSE_MANAGER_API_KEY=your-external-api-key
LICENSE_MANAGER_PRODUCT_ID=ABC12345
```

## Usage

### Artisan Commands

```bash
# Activate a license
php artisan license:activate XXXX-XXXX-XXXX-XXXX "John Doe"

# Verify current license
php artisan license:verify

# Check license status
php artisan license:status

# Deactivate license
php artisan license:deactivate
```

### Service Class

```php
use App\Services\LicenseManagerClient;

// Via dependency injection
public function __construct(private LicenseManagerClient $license) {}

public function check(): array
{
    // Test connection
    $result = $this->license->checkConnection();

    // Activate
    $result = $this->license->activateLicense('XXXX-XXXX-XXXX', 'John Doe');

    // Verify
    $result = $this->license->verifyLicense();

    // Deactivate
    $result = $this->license->deactivateLicense();

    // Check for updates
    $result = $this->license->checkUpdate('1.0.0');
}
```

### Middleware

Protect routes that require a valid license:

```php
// In routes/web.php or routes/api.php
Route::middleware('verify-license')->group(function () {
    Route::get('/premium-feature', [PremiumController::class, 'index']);
});
```

### Facade (Optional)

```php
use Illuminate\Support\Facades\App;

$client = App::make(LicenseManagerClient::class);
$result = $client->checkConnection();
```

## Configuration

All settings are in `config/license-manager.php` and can be overridden via `.env`:

| Key | Env Variable | Description |
|-----|-------------|-------------|
| `api_url` | `LICENSE_MANAGER_API_URL` | License Manager server URL |
| `api_key` | `LICENSE_MANAGER_API_KEY` | External API key |
| `product_id` | `LICENSE_MANAGER_PRODUCT_ID` | Product Reference ID |
| `timeout` | `LICENSE_MANAGER_TIMEOUT` | HTTP timeout in seconds (default: 30) |
| `verify_ssl` | `LICENSE_MANAGER_VERIFY_SSL` | SSL verification (default: true) |
| `cache_ttl` | `LICENSE_MANAGER_CACHE_TTL` | Verification cache duration in seconds (default: 3600) |
