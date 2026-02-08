<?php

namespace App\Console\Commands;

use App\Services\LicenseManagerClient;
use Illuminate\Console\Command;

class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate
        {license_code : The license code (e.g., XXXX-XXXX-XXXX-XXXX)}
        {client_name : The buyer/customer name}
        {--verify-type=non_envato : Verification type (non_envato or envato)}';

    protected $description = 'Activate a product license';

    public function handle(LicenseManagerClient $client): int
    {
        $licenseCode = $this->argument('license_code');
        $clientName = $this->argument('client_name');
        $verifyType = $this->option('verify-type');

        $this->components->info('Activating license...');

        $result = $client->activateLicense($licenseCode, $clientName, $verifyType);

        if (! empty($result['is_active'])) {
            $this->components->info($result['message'] ?? 'License activated successfully!');
            $this->components->bulletList([
                'License data saved to: storage/app/.license',
            ]);

            return self::SUCCESS;
        }

        $this->components->error($result['message'] ?? 'Activation failed.');

        return self::FAILURE;
    }
}
