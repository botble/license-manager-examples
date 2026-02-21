/**
 * License Manager - Express.js integration example.
 *
 * Usage:
 *   npm install express
 *   node express-example.js
 */

const express = require('express');
const { LicenseManagerClient } = require('./license-manager-client');

const app = express();
app.use(express.json());

const client = new LicenseManagerClient({
  serverUrl: process.env.LM_SERVER_URL || 'https://your-license-server.com',
  apiKey: process.env.LM_API_KEY || 'your-api-key',
  applicationUrl: process.env.LM_APP_URL || 'https://your-app.com',
});

// ── License Verification Middleware (optional) ──────────────────────────
// Uncomment to verify license on every request with in-memory cache.
//
// let cachedResult = null;
// let cachedAt = 0;
// const CACHE_TTL = 300000; // 5 minutes
//
// app.use(async (req, res, next) => {
//   if (cachedResult && Date.now() - cachedAt < CACHE_TTL) {
//     return cachedResult.is_active ? next() : res.status(403).json({ error: 'Invalid license' });
//   }
//   cachedResult = await client.verifyLicense(process.env.LM_PRODUCT_ID);
//   cachedAt = Date.now();
//   cachedResult.is_active ? next() : res.status(403).json({ error: 'Invalid license' });
// });

// ── Endpoints ───────────────────────────────────────────────────────────

app.get('/license/connection', async (req, res) => {
  const result = await client.checkConnection();
  res.json(result);
});

app.post('/license/activate', async (req, res) => {
  const { product_id, license_code, client_name } = req.body;
  const result = await client.activateLicense(product_id, license_code, client_name);
  res.status(result.is_active ? 200 : 400).json(result);
});

app.get('/license/verify/:productId', async (req, res) => {
  const result = await client.verifyLicense(req.params.productId);
  res.status(result.is_active ? 200 : 403).json(result);
});

app.post('/license/deactivate/:productId', async (req, res) => {
  const result = await client.deactivateLicense(req.params.productId);
  res.json(result);
});

app.post('/license/update-check', async (req, res) => {
  const { product_id, current_version } = req.body;
  const result = await client.checkForUpdate(product_id, current_version);
  res.json(result);
});

app.get('/license/latest/:productId', async (req, res) => {
  const result = await client.getLatestVersion(req.params.productId);
  res.json(result);
});

app.post('/license/update-download', async (req, res) => {
  const { update_id, type } = req.body;
  try {
    const filePath = await client.downloadUpdate(update_id, './updates', type || 'main');
    res.json({ success: true, path: filePath });
  } catch (e) {
    res.status(400).json({ success: false, message: e.message });
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on http://localhost:${PORT}`));
