"""
Example LICENSE_MANAGER settings for your Django settings.py.

Copy this block into your project's settings.py and update the values.
"""

import os

LICENSE_MANAGER = {
    # License Manager server URL
    "SERVER_URL": os.environ.get("LICENSE_MANAGER_SERVER_URL", "https://license.example.com"),

    # External API key (created in License Manager admin panel)
    "API_KEY": os.environ.get("LICENSE_MANAGER_API_KEY", "your-api-key"),

    # Product Reference ID
    "PRODUCT_ID": os.environ.get("LICENSE_MANAGER_PRODUCT_ID", "ABC12345"),

    # Your application's public URL (sent as X-API-URL header)
    "APP_URL": os.environ.get("LICENSE_MANAGER_APP_URL", "https://your-app.com"),

    # Your server IP (sent as X-API-IP header)
    "APP_IP": os.environ.get("LICENSE_MANAGER_APP_IP", "127.0.0.1"),

    # HTTP request timeout in seconds
    "TIMEOUT": 30,

    # Enable SSL certificate verification
    "VERIFY_SSL": True,

    # Cache verification results for N seconds (0 to disable)
    "CACHE_TTL": 3600,

    # Directory to store the .license file (defaults to BASE_DIR)
    # "LICENSE_DIR": "/path/to/writable/directory",
}
