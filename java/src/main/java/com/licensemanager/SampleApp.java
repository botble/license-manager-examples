package com.licensemanager;

import java.util.Scanner;

import com.licensemanager.LicenseManagerClient.ActivationResponse;
import com.licensemanager.LicenseManagerClient.ApiResponse;
import com.licensemanager.LicenseManagerClient.Config;
import com.licensemanager.LicenseManagerClient.UpdateCheckResponse;

/**
 * Interactive CLI sample demonstrating License Manager API integration.
 *
 * <p>Usage: edit the configuration below, then run with Maven or your IDE.
 */
public class SampleApp {

    // ── Configuration ───────────────────────────────────────────────────
    static final String SERVER_URL = "https://your-license-server.com";
    static final String API_KEY = "your-api-key-here";
    static final String APP_URL = "https://my-java-app.local";
    static final String PRODUCT_ID = "ABC12345";
    static final String LICENSE_CODE = "XXXX-XXXX-XXXX-XXXX";
    static final String CLIENT_NAME = "John Doe";

    public static void main(String[] args) {
        var config = new Config(SERVER_URL, API_KEY, APP_URL);

        try (var client = new LicenseManagerClient(config);
             var scanner = new Scanner(System.in)) {

            System.out.println("========================================");
            System.out.println("  License Manager - Java Example");
            System.out.println("========================================\n");

            while (true) {
                System.out.println("1. Check Connection");
                System.out.println("2. Activate License");
                System.out.println("3. Verify License");
                System.out.println("4. Deactivate License");
                System.out.println("5. Check for Updates");
                System.out.println("6. Exit\n");
                System.out.print("Choice: ");

                String choice = scanner.nextLine().trim();

                try {
                    switch (choice) {
                        case "1" -> {
                            ApiResponse r = client.checkConnection();
                            printResult("Connection", r);
                        }
                        case "2" -> {
                            ActivationResponse r = client.activateLicense(PRODUCT_ID, LICENSE_CODE, CLIENT_NAME);
                            printResult("Activation", r);
                            if (r.isActive) System.out.println("  License data saved to disk.");
                        }
                        case "3" -> {
                            ApiResponse r = client.verifyLicense(PRODUCT_ID);
                            printResult("Verification", r);
                        }
                        case "4" -> {
                            ApiResponse r = client.deactivateLicense(PRODUCT_ID);
                            printResult("Deactivation", r);
                        }
                        case "5" -> {
                            System.out.print("Current version: ");
                            String version = scanner.nextLine().trim();
                            UpdateCheckResponse r = client.checkForUpdate(PRODUCT_ID, version);
                            printResult("Update Check", r);
                            if (r.updateAvailable) {
                                System.out.println("  New version: " + r.version);
                                System.out.println("  Update ID:   " + r.updateId);
                                System.out.println("  Summary:     " + r.summary);
                            }
                        }
                        case "6" -> {
                            System.out.println("Goodbye!");
                            return;
                        }
                        default -> System.out.println("Invalid choice.\n");
                    }
                } catch (Exception e) {
                    System.out.println("  [ERROR] " + e.getMessage());
                }

                System.out.println();
            }
        }
    }

    private static void printResult(String operation, ApiResponse result) {
        String status = result.isActive ? "OK" : "FAILED";
        System.out.println("  [" + status + "] " + operation + ": " + result.message);
    }
}
