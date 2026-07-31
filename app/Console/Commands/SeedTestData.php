<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TestDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:seed-test-data {--reset : Recreate only the dedicated test dataset}')]
#[Description('Create the non-production API dataset used by the mobile application')]
final class SeedTestData extends Command
{
    public function handle(TestDataSeeder $seeder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Test data seeding is available only in local and testing environments.');

            return self::FAILURE;
        }

        try {
            if ($this->option('reset')) {
                $seeder->reset();
            }
            $result = $seeder->seed();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('API test data is ready.');
        $this->line('Login: '.TestDataSeeder::COUNTRY_CODE.' '.TestDataSeeder::PHONE_NUMBER);
        $this->line('Password: '.TestDataSeeder::PASSWORD);
        $this->line('Farm: '.TestDataSeeder::FARM_NAME);
        $this->line('Devices: '.\count($result['devices']));

        return self::SUCCESS;
    }
}
