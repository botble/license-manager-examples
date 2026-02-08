<?php

namespace App\Console\Commands;

use App\Services\LicenseManagerClient;
use Illuminate\Console\Command;

class LicenseDeactivateCommand extends Command
{
    protected $signature = 'license:deactivate';

    protected $description = 'Deactivate the current product license';

    public function handle(LicenseManagerClient $client): int
    {
        if (! $this->confirm('Are you sure you want to deactivate your license?')) {
            return self::SUCCESS;
        }

        $this->components->info('Deactivating license...');

        $result = $client->deactivateLicense();

        if (! empty($result['is_active'])) {
            $this->components->info($result['message'] ?? 'License deactivated.');

            return self::SUCCESS;
        }

        $this->components->error($result['message'] ?? 'Deactivation failed.');

        return self::FAILURE;
    }
}
