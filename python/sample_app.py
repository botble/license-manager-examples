#!/usr/bin/env python3
"""
License Manager API - Python Sample Application.

Usage: pip install requests && python sample_app.py
"""

from license_manager_client import LicenseManagerClient

# ── Configuration ────────────────────────────────────────────────────────
SERVER_URL = "https://your-license-server.com"
API_KEY = "your-api-key-here"
APP_URL = "https://my-python-app.local"
PRODUCT_ID = "ABC12345"
LICENSE_CODE = "XXXX-XXXX-XXXX-XXXX"
CLIENT_NAME = "John Doe"


def main():
    client = LicenseManagerClient(
        server_url=SERVER_URL,
        api_key=API_KEY,
        application_url=APP_URL,
    )

    print("========================================")
    print("  License Manager - Python Example")
    print("========================================\n")

    while True:
        print("1. Check Connection")
        print("2. Activate License")
        print("3. Verify License")
        print("4. Deactivate License")
        print("5. Check for Updates")
        print("6. Download Update")
        print("7. Exit\n")

        choice = input("Choice: ").strip()

        if choice == "1":
            result = client.check_connection()
            print_result("Connection", result)

        elif choice == "2":
            result = client.activate_license(PRODUCT_ID, LICENSE_CODE, CLIENT_NAME)
            print_result("Activation", result)
            if result.get("is_active"):
                print("  License data saved to disk.")

        elif choice == "3":
            result = client.verify_license(PRODUCT_ID)
            print_result("Verification", result)

        elif choice == "4":
            result = client.deactivate_license(PRODUCT_ID)
            print_result("Deactivation", result)

        elif choice == "5":
            version = input("Current version: ").strip() or "1.0.0"
            result = client.check_for_update(PRODUCT_ID, version)
            print_result("Update Check", result)
            if result.get("update_available"):
                print(f"  New version: {result.get('version')}")
                print(f"  Update ID:   {result.get('update_id')}")
                print(f"  Summary:     {result.get('summary')}")

        elif choice == "6":
            update_id = input("Update ID (from update check): ").strip()
            dl_type = input("Type (main/sql) [main]: ").strip() or "main"
            try:
                file_path = client.download_update(update_id, ".", dl_type)
                print(f"  [OK] Downloaded: {file_path}")
            except Exception as e:
                print(f"  [FAILED] Download: {e}")

        elif choice == "7":
            print("Goodbye!")
            break

        else:
            print("Invalid choice.")

        print()


def print_result(operation: str, result: dict):
    status = "OK" if result.get("is_active") else "FAILED"
    print(f"  [{status}] {operation}: {result.get('message', 'No message')}")


if __name__ == "__main__":
    main()
