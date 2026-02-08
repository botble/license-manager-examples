# License Manager - PHP Examples

Standalone PHP scripts demonstrating License Manager API integration using cURL.

## Files

| File | Description |
|------|-------------|
| `sample-app.php` | External API demo - license activation, verification, deactivation |
| `sample-app-internal.php` | Internal API demo - product/license management (server-to-server) |

## Requirements

- PHP 7.4+
- cURL extension

## Usage

### External API (Client Application)

```bash
# Edit configuration in the file first, then run:
php sample-app.php
```

**Configuration:**
```php
$config = [
    'api_url'      => 'https://your-license-server.com',
    'api_key'      => 'your-external-api-key',
    'product_id'   => 'ABC12345',
    'license_code' => 'XXXX-XXXX-XXXX-XXXX',
    'client_name'  => 'John Doe',
];
```

**Operations:**
1. Check API Connection
2. Activate License
3. Verify License
4. Deactivate License

### Internal API (Server Management)

```bash
# Edit configuration in the file first, then run:
php sample-app-internal.php
```

**Configuration:**
```php
$config = [
    'api_url'    => 'https://your-license-server.com',
    'api_key'    => 'your-internal-api-key',
    'server_url' => 'https://your-backend-server.com',
    'server_ip'  => '127.0.0.1',
];
```

**Operations:**
1. Check API Connection
2. List/Get/Create Products
3. List/Get/Create Licenses
4. Block/Unblock Licenses
