# License Manager - Django Integration Example

A drop-in Django app with API client, middleware, management commands, and views for integrating with the License Manager API.

## Structure

```
django/
├── settings_example.py                    # Example Django settings
├── README.md
└── license_manager/
    ├── __init__.py
    ├── client.py                          # API client (uses Django settings & cache)
    ├── middleware.py                       # License verification middleware & decorator
    ├── views.py                           # Example JSON API views
    ├── urls.py                            # URL patterns
    └── management/
        └── commands/
            ├── license_activate.py        # ./manage.py license_activate
            ├── license_verify.py          # ./manage.py license_verify
            ├── license_deactivate.py      # ./manage.py license_deactivate
            └── license_status.py          # ./manage.py license_status
```

## Requirements

- Python 3.9+
- Django 4.2+
- `pip install requests`

## Installation

1. Copy the `license_manager/` directory into your Django project
2. Add to `INSTALLED_APPS` in `settings.py`:

```python
INSTALLED_APPS = [
    # ...
    'license_manager',
]
```

3. Add configuration to `settings.py` (see `settings_example.py`):

```python
LICENSE_MANAGER = {
    "SERVER_URL": os.environ.get("LICENSE_MANAGER_SERVER_URL", "https://license.example.com"),
    "API_KEY": os.environ.get("LICENSE_MANAGER_API_KEY", ""),
    "PRODUCT_ID": os.environ.get("LICENSE_MANAGER_PRODUCT_ID", ""),
    "APP_URL": os.environ.get("LICENSE_MANAGER_APP_URL", "https://your-app.com"),
}
```

4. (Optional) Include the URL routes in `urls.py`:

```python
from django.urls import include, path

urlpatterns = [
    # ...
    path('license/', include('license_manager.urls')),
]
```

## Usage

### Management Commands

```bash
# Activate a license
./manage.py license_activate XXXX-XXXX-XXXX-XXXX "John Doe"

# Activate with Envato verification
./manage.py license_activate PURCHASE-CODE "John Doe" --envato

# Verify current license
./manage.py license_verify

# Show connection & license status
./manage.py license_status

# Deactivate license
./manage.py license_deactivate
```

### Service Client

```python
from license_manager.client import LicenseManagerClient

client = LicenseManagerClient()

# Test connection
result = client.check_connection()

# Activate
result = client.activate_license("XXXX-XXXX-XXXX", "John Doe")
if result.get("is_active"):
    print("Licensed!")

# Verify (uses cached result when available)
result = client.verify_license()

# Quick boolean check
if client.is_licensed():
    print("License is valid")

# Check for updates
update = client.check_for_update("1.0.0")
if update.get("update_available"):
    print(f"New version: {update['version']}")

# Download update
path = client.download_update(update["update_id"], "./updates")
```

### Middleware (Protect All Routes)

Add to `MIDDLEWARE` in `settings.py` to require a valid license for every request:

```python
MIDDLEWARE = [
    # ...
    'license_manager.middleware.VerifyLicenseMiddleware',
]
```

### Decorator (Protect Individual Views)

```python
from license_manager.middleware import license_required

@license_required
def premium_feature(request):
    return JsonResponse({"message": "Premium content"})
```

### Views / API Endpoints

When URL routes are included, these endpoints are available:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/license/connection/` | GET | Test API connectivity |
| `/license/activate/` | POST | Activate a license (`license_code`, `client_name`) |
| `/license/verify/` | GET | Verify current license |
| `/license/deactivate/` | POST | Deactivate current license |
| `/license/update-check/` | POST | Check for updates (`current_version`) |
| `/license/latest-version/` | GET | Get latest version info |

## Configuration

All settings go in the `LICENSE_MANAGER` dict in `settings.py`:

| Key | Environment Variable | Description |
|-----|---------------------|-------------|
| `SERVER_URL` | `LICENSE_MANAGER_SERVER_URL` | License Manager server URL |
| `API_KEY` | `LICENSE_MANAGER_API_KEY` | External API key |
| `PRODUCT_ID` | `LICENSE_MANAGER_PRODUCT_ID` | Product Reference ID |
| `APP_URL` | `LICENSE_MANAGER_APP_URL` | Your application URL |
| `APP_IP` | `LICENSE_MANAGER_APP_IP` | Your server IP (default: `127.0.0.1`) |
| `TIMEOUT` | — | HTTP timeout in seconds (default: `30`) |
| `VERIFY_SSL` | — | SSL verification (default: `true`) |
| `CACHE_TTL` | — | Verification cache in seconds (default: `3600`) |
| `LICENSE_DIR` | — | Directory for `.license` file (default: `BASE_DIR`) |

## License

MIT
