# License Manager - Python Example

Integration example using the `requests` library.

## Structure

```
python/
├── license_manager_client.py   # Reusable HTTP client
├── sample_app.py               # Interactive CLI demo
└── README.md
```

## Requirements

- Python 3.9+
- `pip install requests`

## Quick Start

```bash
# Edit sample_app.py with your credentials, then:
pip install requests
python sample_app.py
```

## Using the Client Library

```python
from license_manager_client import LicenseManagerClient

client = LicenseManagerClient(
    server_url="https://your-license-server.com",
    api_key="your-api-key",
    application_url="https://your-app.com",
)

# Activate
result = client.activate_license("PRODUCT_ID", "LICENSE-CODE", "Client Name")
if result.get("is_active"):
    print("Licensed!")

# Verify (uses saved license file)
result = client.verify_license("PRODUCT_ID")

# Check for updates
update = client.check_for_update("PRODUCT_ID", "1.0.0")
if update.get("update_available"):
    print(f"New version: {update['version']}")

# Download update
path = client.download_update(update["update_id"], "./updates")
```

## Integration Tips

- **Django**: Create a service class or use in a management command
- **Flask/FastAPI**: Register as a dependency and inject into route handlers
- **Desktop (Tkinter/PyQt)**: Call from a background thread to avoid blocking the UI

## License

MIT
