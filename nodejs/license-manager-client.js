/**
 * License Manager API client for Node.js.
 *
 * Works with any Node.js app: Express, Fastify, NestJS, Electron, CLI scripts.
 * Zero dependencies - uses built-in fetch (Node 18+).
 *
 * @example
 * const { LicenseManagerClient } = require('./license-manager-client');
 * const client = new LicenseManagerClient({
 *   serverUrl: 'https://your-license-server.com',
 *   apiKey: 'your-api-key',
 *   applicationUrl: 'https://your-app.com',
 * });
 * const result = await client.activateLicense('PRODUCT_ID', 'LICENSE-CODE', 'Client');
 */

const fs = require('fs');
const path = require('path');

class LicenseManagerClient {
  /**
   * @param {Object} options
   * @param {string} options.serverUrl - License Manager server URL
   * @param {string} options.apiKey - External API key
   * @param {string} options.applicationUrl - Your app URL (X-API-URL header)
   * @param {string} [options.ipAddress='127.0.0.1'] - IP address (X-API-IP header)
   * @param {string} [options.language='en'] - Locale (X-API-LANGUAGE header)
   * @param {string} [options.licenseFilePath] - Path to store license data
   * @param {number} [options.timeout=30000] - Request timeout in ms
   */
  constructor(options) {
    if (!options.serverUrl) throw new Error('LicenseManagerClient: options.serverUrl is required');
    if (!options.apiKey) throw new Error('LicenseManagerClient: options.apiKey is required');
    if (!options.productId && options.productId !== undefined) throw new Error('LicenseManagerClient: options.productId must be a non-empty string');

    this.serverUrl = options.serverUrl.replace(/\/+$/, '');
    this.apiKey = options.apiKey;
    this.applicationUrl = options.applicationUrl;
    this.ipAddress = options.ipAddress || '127.0.0.1';
    this.language = options.language || 'en';
    this.timeout = options.timeout || 30000;
    this.licenseFilePath = options.licenseFilePath || path.join(process.cwd(), '.license');
  }

  // ── Connection ──────────────────────────────────────────────────────

  async checkConnection() {
    return this._get('/api/external/connection-check');
  }

  // ── License Operations ──────────────────────────────────────────────

  async activateLicense(productId, licenseCode, clientName) {
    const result = await this._post('/api/external/license/activate', {
      product_id: productId,
      license_code: licenseCode,
      client_name: clientName,
      verify_type: 'non_envato',
    });

    if (result.is_active) {
      const licenseData = result.lic_response || result.data?.license_data;
      if (licenseData) {
        fs.writeFileSync(this.licenseFilePath, licenseData, { encoding: 'utf8', mode: 0o600 });
      }
    }

    return result;
  }

  async verifyLicense(productId) {
    const licenseData = this._readLicenseData();
    if (!licenseData) {
      return { status: false, is_active: false, message: 'No license file found.' };
    }

    return this._post('/api/external/license/verify', {
      product_id: productId,
      license_data: licenseData,
    });
  }

  async deactivateLicense(productId) {
    const licenseData = this._readLicenseData();
    if (!licenseData) {
      return { status: false, is_active: false, message: 'No license file found.' };
    }

    const result = await this._post('/api/external/license/deactivate', {
      product_id: productId,
      license_data: licenseData,
    });

    if (result.is_active && fs.existsSync(this.licenseFilePath)) {
      fs.unlinkSync(this.licenseFilePath);
    }

    return result;
  }

  // ── Update Operations ───────────────────────────────────────────────

  async checkForUpdate(productId, currentVersion) {
    return this._post('/api/external/update/check', {
      product_id: productId,
      current_version: currentVersion,
    });
  }

  async getLatestVersion(productId) {
    return this._post('/api/external/update/latest', {
      product_id: productId,
    });
  }

  /**
   * Download an update file.
   * @param {string} updateId - Version ID from update check
   * @param {string} outputDir - Directory to save the file
   * @param {string} [type='main'] - File type: 'main' (zip) or 'sql'
   * @returns {Promise<string>} Saved file path
   */
  async downloadUpdate(updateId, outputDir, type = 'main') {
    const licenseData = this._readLicenseData();
    const body = licenseData ? { license_data: licenseData } : {};

    const encodedId = encodeURIComponent(updateId);
    const encodedType = encodeURIComponent(type);
    const url = `${this.serverUrl}/api/external/update/${encodedId}/download/${encodedType}`;

    const response = await fetch(url, {
      method: 'POST',
      headers: this._headers(),
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(300000),
    });

    if (!response.ok) {
      throw new Error(`Download failed with HTTP ${response.status}`);
    }

    const ext = type === 'sql' ? 'sql' : 'zip';
    const filePath = path.join(outputDir, `update_${updateId}.${ext}`);
    fs.mkdirSync(outputDir, { recursive: true });

    const buffer = Buffer.from(await response.arrayBuffer());
    fs.writeFileSync(filePath, buffer);

    return filePath;
  }

  // ── Helpers ──────────────────────────────────────────────────────────

  hasLicenseFile() {
    return fs.existsSync(this.licenseFilePath);
  }

  _readLicenseData() {
    if (!fs.existsSync(this.licenseFilePath)) return null;
    return fs.readFileSync(this.licenseFilePath, 'utf8').trim() || null;
  }

  _headers() {
    return {
      'Content-Type': 'application/json',
      'X-API-KEY': this.apiKey,
      'X-API-URL': this.applicationUrl,
      'X-API-IP': this.ipAddress,
      'X-API-LANGUAGE': this.language,
    };
  }

  async _get(apiPath) {
    try {
      const response = await fetch(`${this.serverUrl}${apiPath}`, {
        headers: this._headers(),
        signal: AbortSignal.timeout(this.timeout),
      });
      if (!response.ok) {
        let errorBody = { status: false, is_active: false, message: `HTTP ${response.status}` };
        try { errorBody = await response.json(); } catch (_) {}
        return errorBody;
      }
      return response.json();
    } catch (error) {
      return { status: false, is_active: false, message: error.message };
    }
  }

  async _post(apiPath, payload) {
    try {
      const response = await fetch(`${this.serverUrl}${apiPath}`, {
        method: 'POST',
        headers: this._headers(),
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(this.timeout),
      });
      if (!response.ok) {
        let errorBody = { status: false, is_active: false, message: `HTTP ${response.status}` };
        try { errorBody = await response.json(); } catch (_) {}
        return errorBody;
      }
      return response.json();
    } catch (error) {
      return { status: false, is_active: false, message: error.message };
    }
  }
}

module.exports = { LicenseManagerClient };
