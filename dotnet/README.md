# License Manager - .NET / C# Examples

Integration examples for .NET applications: console/desktop, ASP.NET Core web API, and Blazor Server.

## Structure

```
dotnet/
├── src/
│   └── LicenseManagerClient.cs    # Shared HTTP client (reusable in any .NET app)
├── examples/
│   ├── ConsoleApp/                # Console/desktop app (WPF, WinForms, MAUI)
│   ├── AspNetCoreApi/             # ASP.NET Core minimal API
│   └── BlazorServer/              # Blazor Server with interactive UI
└── README.md
```

## Requirements

- .NET 8.0 SDK or later
- A running License Manager server with an External API key

## Quick Start

### Console / Desktop App

```bash
cd examples/ConsoleApp
# Edit Program.cs with your credentials
dotnet run
```

Works for any desktop scenario: console apps, WPF, WinForms, or .NET MAUI. Use `LicenseManagerClient` directly.

### ASP.NET Core Web API

```bash
cd examples/AspNetCoreApi
# Edit appsettings.json with your credentials
dotnet run
```

Endpoints:

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/license/connection` | Test server connectivity |
| POST | `/license/activate` | Activate a license |
| GET | `/license/verify/{productId}` | Verify current license |
| POST | `/license/deactivate/{productId}` | Deactivate license |
| POST | `/license/update-check` | Check for updates |
| GET | `/license/latest/{productId}` | Get latest version |

### Blazor Server

```bash
cd examples/BlazorServer
# Edit appsettings.json with your credentials
dotnet run
```

Opens an interactive dashboard at `https://localhost:5001` with forms for activation, verification, and update checking.

## Using the Client Library

The shared `LicenseManagerClient` class in `src/` can be used in any .NET project:

```csharp
using LicenseManager;

var options = new LicenseManagerOptions
{
    ServerUrl = "https://your-license-server.com",
    ApiKey = "your-api-key",
    ApplicationUrl = "https://your-app.com",
};

using var client = new LicenseManagerClient(options);

// Activate
var result = await client.ActivateLicenseAsync("PRODUCT_ID", "LICENSE-CODE", "Client Name");
if (result.IsActive)
    Console.WriteLine("Licensed!");

// Verify (uses saved license file)
var verify = await client.VerifyLicenseAsync("PRODUCT_ID");

// Check for updates
var update = await client.CheckForUpdateAsync("PRODUCT_ID", "1.0.0");
if (update.UpdateAvailable)
    Console.WriteLine($"New version: {update.Version}");
```

## Configuration

All examples read from `appsettings.json` (web) or inline constants (console):

| Setting | Description |
|---------|-------------|
| `ServerUrl` | License Manager server URL |
| `ApiKey` | External API key from admin panel |
| `ApplicationUrl` | Your app's URL (sent as `X-API-URL` header) |
| `IpAddress` | Optional IP (default: `127.0.0.1`) |
| `Language` | Optional locale (default: `en`) |
| `LicenseFilePath` | Optional path for license data file |

## API Headers

All requests include these headers automatically:

```
Content-Type: application/json
X-API-KEY: {ApiKey}
X-API-URL: {ApplicationUrl}
X-API-IP: {IpAddress}
X-API-LANGUAGE: {Language}
```

## License

MIT
