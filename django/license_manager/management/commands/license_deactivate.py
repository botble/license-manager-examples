"""Django management command: deactivate the current license."""

from django.core.management.base import BaseCommand

from license_manager.client import LicenseManagerClient


class Command(BaseCommand):
    help = "Deactivate the current license on the License Manager server"

    def handle(self, *args, **options):
        client = LicenseManagerClient()

        if not client.has_license_file():
            self.stderr.write(self.style.WARNING("No license file found. Nothing to deactivate."))
            return

        result = client.deactivate_license()

        if result.get("is_active"):
            self.stdout.write(self.style.SUCCESS(f"License deactivated: {result.get('message', 'OK')}"))
        else:
            self.stderr.write(self.style.ERROR(f"Deactivation failed: {result.get('message', 'Unknown error')}"))
