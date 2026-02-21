"""Django management command: verify the current license."""

from django.core.management.base import BaseCommand

from license_manager.client import LicenseManagerClient


class Command(BaseCommand):
    help = "Verify the current license with the License Manager server"

    def handle(self, *args, **options):
        client = LicenseManagerClient()

        if not client.has_license_file():
            self.stderr.write(self.style.WARNING("No license file found. Run license_activate first."))
            return

        result = client.verify_license()

        if result.get("is_active"):
            self.stdout.write(self.style.SUCCESS(f"License is valid: {result.get('message', 'OK')}"))
        else:
            self.stderr.write(self.style.ERROR(f"License invalid: {result.get('message', 'Unknown error')}"))
