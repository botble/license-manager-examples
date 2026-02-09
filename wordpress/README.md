# License Manager - WordPress Integration Example

A WordPress plugin demonstrating how to integrate with the [License Manager](https://docs.botble.com/license-manager) API for license activation, verification, deactivation, and automatic updates.

## Features

- License activation & deactivation from WordPress admin
- Periodic license verification (daily via WP-Cron)
- Automatic update checks from License Manager
- One-click update installation
- Admin notices for license status
- Secure credential storage using WordPress options API

## Installation

1. Copy the `wordpress` folder to `wp-content/plugins/license-manager-client/`
2. Activate the plugin in **Plugins > Installed Plugins**
3. Go to **Settings > License Manager** to configure

## Configuration

### Required Settings

| Setting | Description |
|---------|-------------|
| API URL | Your License Manager server URL (e.g., `https://license.example.com`) |
| API Key | External API key from **License Manager > API Settings** |
| Product ID | Product Reference ID from **License Manager > Products** |

### License Activation

1. Enter your **License Code** (e.g., `XXXX-XXXX-XXXX-XXXX`)
2. Enter the **Client Name** (buyer/customer name)
3. Click **Activate License**

## How It Works

### API Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/external/connection-check` | GET | Test API connectivity |
| `/api/external/license/activate` | POST | Activate a license |
| `/api/external/license/verify` | POST | Verify license validity |
| `/api/external/license/deactivate` | POST | Deactivate a license |
| `/api/external/update/check` | POST | Check for product updates |
| `/api/external/update/latest` | POST | Get latest version info |

### Required Headers

All API requests include these headers:

```
Content-Type: application/json
X-API-KEY: {your-api-key}
X-API-URL: {your-site-url}
X-API-IP: {your-server-ip}
X-API-LANGUAGE: {locale, e.g. "en"}
```

### License File

On successful activation, the server returns encrypted license data. This plugin stores it in the WordPress database (`lm_license_data` option) instead of a file, which is more reliable in WordPress environments.

### Auto-Updates

When a license is active, the plugin hooks into WordPress's update system (`pre_set_site_transient_update_plugins`) to check for new versions from your License Manager server. Updates appear alongside other plugin updates in **Dashboard > Updates**.

## Integration in Your Own Plugin/Theme

### Using the API Client Class

```php
// Initialize the client
$client = new LM_API_Client(
    'https://license.example.com',
    'your-api-key',
    'PRODUCT_ID'
);

// Activate
$result = $client->activate_license('XXXX-XXXX-XXXX', 'John Doe');
if ($result['is_active']) {
    // Save $result['lic_response'] as license data
}

// Verify
$result = $client->verify_license($license_data);

// Deactivate
$result = $client->deactivate_license($license_data);

// Check for updates
$result = $client->check_update('1.0.0');
```

### Theme Integration Example

```php
// In your theme's functions.php
require_once get_template_directory() . '/includes/class-lm-api-client.php';

$client = new LM_API_Client(
    get_option('my_theme_api_url'),
    get_option('my_theme_api_key'),
    'MY_PRODUCT_ID'
);

// Verify on theme activation
add_action('after_switch_theme', function () use ($client) {
    $license_data = get_option('my_theme_license_data');
    if ($license_data) {
        $result = $client->verify_license($license_data);
        if (!$result['is_active']) {
            update_option('my_theme_license_status', 'invalid');
        }
    }
});
```

## File Structure

```
wordpress/
├── license-manager-wp.php              # Main plugin file
├── includes/
│   ├── class-lm-api-client.php         # Reusable API client
│   ├── class-lm-admin.php              # Admin settings page
│   └── class-lm-updater.php            # WordPress auto-updater
├── assets/
│   ├── css/
│   │   └── admin.css                   # Admin page styles
│   └── js/
│       └── admin.js                    # Admin page scripts
└── README.md
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Connection failed | Check API URL and API Key are correct |
| Activation failed | Verify License Code and Product ID match |
| License blocked | Contact your license provider |
| Max activations reached | Deactivate on other sites first |
| Updates not showing | Ensure license is active and valid |

## Security Notes

- API keys are stored in the WordPress database (consider using `wp-config.php` constants for production)
- License data is encrypted by the server
- All API communication should use HTTPS
- The plugin uses WordPress nonces for form security

## Requirements

- WordPress 5.0+
- PHP 7.4+
- cURL extension enabled

## License

MIT
