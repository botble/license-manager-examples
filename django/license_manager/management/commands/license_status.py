"""Django management command: show license status."""

from django.core.management.base import BaseCommand

from license_manager.client import LicenseManagerClient


class Command(BaseCommand):
    help = "Show the current license status and connection info"

    def handle(self, *args, **options):
        client = LicenseManagerClient()

        if not client.is_configured():
            self.stderr.write(self.style.ERROR("License Manager is not configured. Check LICENSE_MANAGER in settings."))
            return

        # Connection check
        self.stdout.write("Checking connection...")
        conn = client.check_connection()
        if conn.get("is_active"):
            self.stdout.write(self.style.SUCCESS(f"  Server: Connected ({conn.get('message', 'OK')})"))
        else:
            self.stderr.write(self.style.ERROR(f"  Server: Unreachable ({conn.get('message', 'No response')})"))
            return

        # License file
        if not client.has_license_file():
            self.stdout.write(self.style.WARNING("  License: No license file found"))
            return

        # Verification
        self.stdout.write("Verifying license...")
        result = client.verify_license()
        if result.get("is_active"):
            self.stdout.write(self.style.SUCCESS(f"  License: Valid ({result.get('message', 'OK')})"))
        else:
            self.stderr.write(self.style.ERROR(f"  License: Invalid ({result.get('message', 'Unknown')})"))
