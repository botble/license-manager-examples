# License Manager - Integration Examples

Code examples demonstrating how to integrate with the [License Manager](https://docs.botble.com/license-manager) API across different platforms and frameworks.

## Examples

| Example | Description |
|---------|-------------|
| [PHP](./php/) | Standalone PHP scripts using cURL - good for any PHP application |
| [WordPress](./wordpress/) | WordPress plugin with admin UI, auto-updates, and WP-Cron verification |
| [Laravel](./laravel/) | Laravel package with service provider, Artisan commands, and middleware |
| [Django](./django/) | Django app with management commands, middleware, and API views (Python 3.9+, Django 4.2+) |
| [.NET / C#](./dotnet/) | Console/desktop app, ASP.NET Core API, and Blazor Server examples (.NET 8+) |
| [Java](./java/) | Maven project with reusable HttpClient and interactive CLI (Java 17+) |
| [Ruby on Rails](./rails/) | Client library, Rails controller, and Rack middleware for license gating |
| [Python](./python/) | Client using `requests` library with interactive CLI (Python 3.9+) |
| [Node.js](./nodejs/) | Zero-dependency client (built-in fetch), CLI demo, and Express.js server (Node 18+) |

## API Overview

### External API (Client-facing)

Used by client applications to manage their own licenses.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/external/connection-check` | GET | Test API connectivity |
| `/api/external/license/activate` | POST | Activate a license |
| `/api/external/license/verify` | POST | Verify license validity |
| `/api/external/license/deactivate` | POST | Deactivate a license |
| `/api/external/update/check` | POST | Check for product updates |
| `/api/external/update/latest` | POST | Get latest version info |
| `/api/external/update/{version}/download/{type}` | POST | Download update files |

### Internal API (Server-to-server)

Used by backend systems to manage products and licenses programmatically.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/internal/connection-check` | GET | Test connectivity |
| `/api/internal/products` | GET/POST | List or create products |
| `/api/internal/products/{id}` | GET/PUT | Get or update a product |
| `/api/internal/product-licenses` | GET/POST | List or create licenses |
| `/api/internal/product-licenses/{id}` | GET/PUT | Get or update a license |
| `/api/internal/blocked-product-licenses/{id}` | POST/DELETE | Block or unblock a license |

### Required Headers

All API requests must include:

```
Content-Type: application/json
X-API-KEY: {your-api-key}
X-API-URL: {your-application-url}
X-API-IP: {your-server-ip}
X-API-LANGUAGE: {locale, e.g. "en"}
```

## Quick Start

1. Set up a License Manager server
2. Create an API key (External for client apps, Internal for server management)
3. Create a product and generate license codes
4. Choose an example that matches your platform
5. Configure the credentials and start integrating

## License

MIT
