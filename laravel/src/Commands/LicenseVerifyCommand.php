<?php

namespace App\Console\Commands;

use App\Services\LicenseManagerClient;
use Illuminate\Console\Command;

class LicenseVerifyCommand extends Command
{
    protected $signature = 'license:verify';

    protected $description = 'Verify the current product license';

    public function handle(LicenseManagerClient $client): int
    {
        $this->components->info('Verifying license...');

        $result = $client->verifyLicense();

        if (! empty($result['is_active'])) {
            $this->components->info($result['message'] ?? 'License is valid!');

            return self::SUCCESS;
        }

        $this->components->error($result['message'] ?? 'License is invalid.');

        return self::FAILURE;
    }
}
