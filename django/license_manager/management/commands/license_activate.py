"""Django management command: activate a license."""

from django.core.management.base import BaseCommand

from license_manager.client import LicenseManagerClient


class Command(BaseCommand):
    help = "Activate a license on the License Manager server"

    def add_arguments(self, parser):
        parser.add_argument("license_code", type=str, help="License code to activate")
        parser.add_argument("client_name", type=str, help="Client/licensee name")
        parser.add_argument("--envato", action="store_true", help="Use Envato verification")

    def handle(self, *args, **options):
        client = LicenseManagerClient()

        if not client.is_configured():
            self.stderr.write(self.style.ERROR("License Manager is not configured. Check LICENSE_MANAGER in settings."))
            return

        verify_type = "envato" if options["envato"] else "non_envato"
        result = client.activate_license(options["license_code"], options["client_name"], verify_type)

        if result.get("is_active"):
            self.stdout.write(self.style.SUCCESS(f"License activated: {result.get('message', 'OK')}"))
            self.stdout.write(f"  License data saved to: {client.license_file}")
        else:
            self.stderr.write(self.style.ERROR(f"Activation failed: {result.get('message', 'Unknown error')}"))
