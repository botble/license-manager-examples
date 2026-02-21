#!/usr/bin/env node

/**
 * License Manager API - Node.js Sample Application.
 *
 * Usage: node sample-app.js
 * Requires: Node.js 18+ (uses built-in fetch)
 */

const readline = require('readline');
const { LicenseManagerClient } = require('./license-manager-client');

// ── Configuration ───────────────────────────────────────────────────────
const SERVER_URL = 'https://your-license-server.com';
const API_KEY = 'your-api-key-here';
const APP_URL = 'https://my-node-app.local';
const PRODUCT_ID = 'ABC12345';
const LICENSE_CODE = 'XXXX-XXXX-XXXX-XXXX';
const CLIENT_NAME = 'John Doe';

const client = new LicenseManagerClient({
  serverUrl: SERVER_URL,
  apiKey: API_KEY,
  applicationUrl: APP_URL,
});

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
const ask = (q) => new Promise((resolve) => rl.question(q, resolve));

function printResult(operation, result) {
  const status = result.is_active ? 'OK' : 'FAILED';
  console.log(`  [${status}] ${operation}: ${result.message || 'No message'}`);
}

async function main() {
  console.log('========================================');
  console.log('  License Manager - Node.js Example');
  console.log('========================================\n');

  while (true) {
    console.log('1. Check Connection');
    console.log('2. Activate License');
    console.log('3. Verify License');
    console.log('4. Deactivate License');
    console.log('5. Check for Updates');
    console.log('6. Download Update');
    console.log('7. Exit\n');

    const choice = (await ask('Choice: ')).trim();

    switch (choice) {
      case '1': {
        const result = await client.checkConnection();
        printResult('Connection', result);
        break;
      }
      case '2': {
        const result = await client.activateLicense(PRODUCT_ID, LICENSE_CODE, CLIENT_NAME);
        printResult('Activation', result);
        if (result.is_active) console.log('  License data saved to disk.');
        break;
      }
      case '3': {
        const result = await client.verifyLicense(PRODUCT_ID);
        printResult('Verification', result);
        break;
      }
      case '4': {
        const result = await client.deactivateLicense(PRODUCT_ID);
        printResult('Deactivation', result);
        break;
      }
      case '5': {
        const version = (await ask('Current version: ')).trim() || '1.0.0';
        const result = await client.checkForUpdate(PRODUCT_ID, version);
        printResult('Update Check', result);
        if (result.update_available) {
          console.log(`  New version: ${result.version}`);
          console.log(`  Update ID:   ${result.update_id}`);
          console.log(`  Summary:     ${result.summary}`);
        }
        break;
      }
      case '6': {
        const updateId = (await ask('Update ID (from update check): ')).trim();
        const dlType = (await ask('Type (main/sql) [main]: ')).trim() || 'main';
        try {
          const filePath = await client.downloadUpdate(updateId, '.', dlType);
          console.log(`  [OK] Downloaded: ${filePath}`);
        } catch (e) {
          console.log(`  [FAILED] Download: ${e.message}`);
        }
        break;
      }
      case '7':
        console.log('Goodbye!');
        rl.close();
        return;
      default:
        console.log('Invalid choice.');
    }

    console.log();
  }
}

main().catch(console.error);
