# License Manager - Node.js Example

Integration example using built-in `fetch` (Node 18+). Zero dependencies for the client.

## Structure

```
nodejs/
├── license-manager-client.js   # Reusable HTTP client (zero deps)
├── sample-app.js               # Interactive CLI demo
├── express-example.js          # Express.js API server
└── README.md
```

## Requirements

- Node.js 18+ (uses built-in `fetch`)
- Express.js (only for the API server example)

## Quick Start

### CLI Demo

```bash
# Edit sample-app.js with your credentials, then:
node sample-app.js
```

### Express API Server

```bash
npm install express
# Set environment variables or edit express-example.js
LM_SERVER_URL=https://your-server.com LM_API_KEY=your-key LM_APP_URL=https://your-app.com node express-example.js
```

Endpoints:

| Method | URL | Description |
|--------|-----|-------------|
| GET | `/license/connection` | Test server connectivity |
| POST | `/license/activate` | Activate a license |
| GET | `/license/verify/:productId` | Verify current license |
| POST | `/license/deactivate/:productId` | Deactivate license |
| POST | `/license/update-check` | Check for updates |
| GET | `/license/latest/:productId` | Get latest version |
| POST | `/license/update-download` | Download update file |

## Using the Client Library

```javascript
const { LicenseManagerClient } = require('./license-manager-client');

const client = new LicenseManagerClient({
  serverUrl: 'https://your-license-server.com',
  apiKey: 'your-api-key',
  applicationUrl: 'https://your-app.com',
});

// Activate
const result = await client.activateLicense('PRODUCT_ID', 'LICENSE-CODE', 'Client Name');
if (result.is_active) console.log('Licensed!');

// Verify (uses saved license file)
const verify = await client.verifyLicense('PRODUCT_ID');

// Check for updates
const update = await client.checkForUpdate('PRODUCT_ID', '1.0.0');
if (update.update_available) console.log(`New version: ${update.version}`);

// Download update
const filePath = await client.downloadUpdate(update.update_id, './updates');
```

## Integration Tips

- **Express/Fastify/NestJS**: Register client as middleware or service
- **Electron**: Use in the main process; communicate with renderer via IPC
- **Serverless (Lambda)**: Create client per invocation or use a warm instance

## License

MIT
