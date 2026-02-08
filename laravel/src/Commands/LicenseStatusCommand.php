<?php

namespace App\Console\Commands;

use App\Services\LicenseManagerClient;
use Illuminate\Console\Command;

class LicenseStatusCommand extends Command
{
    protected $signature = 'license:status';

    protected $description = 'Show the current license status';

    public function handle(LicenseManagerClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('License Manager is not configured. Set credentials in .env.');

            return self::FAILURE;
        }

        $this->components->info('Checking license status...');

        $licenseFile = storage_path('app/.license');
        $hasLicense = file_exists($licenseFile);

        $this->components->twoColumnDetail('API URL', config('license-manager.api_url'));
        $this->components->twoColumnDetail('Product ID', config('license-manager.product_id'));
        $this->components->twoColumnDetail('License File', $hasLicense ? 'Found' : 'Not found');

        if (! $hasLicense) {
            $this->components->warn('No license activated. Run: php artisan license:activate <code> <name>');

            return self::SUCCESS;
        }

        $result = $client->verifyLicense();

        $this->components->twoColumnDetail(
            'Status',
            ! empty($result['is_active']) ? '<fg=green>Active</>' : '<fg=red>Invalid</>'
        );

        $this->components->twoColumnDetail('Message', $result['message'] ?? 'N/A');

        return self::SUCCESS;
    }
}
