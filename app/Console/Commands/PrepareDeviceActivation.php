<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeviceActivationService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('device:prepare-activation
    {serial : Provisioned device serial number}
    {customer_login : Registered customer email or phone}
    {--operator= : Seller operator identifier for the audit trail}')]
#[Description('Bind an available device to a customer and create its one-time activation QR')]
final class PrepareDeviceActivation extends Command
{
    public function handle(DeviceActivationService $activations): int
    {
        $operator = trim((string) $this->option('operator'));
        $operator = $operator !== '' ? $operator : 'console:'.get_current_user();

        try {
            $prepared = $activations->prepare(
                (string) $this->argument('serial'),
                (string) $this->argument('customer_login'),
                $operator,
            );
            $activation = $prepared['activation'];
            $directory = storage_path('app/activations');
            File::ensureDirectoryExists($directory);
            $path = $directory.'/'.$activation->public_reference.'.svg';
            $options = new QROptions([
                'scale' => 8,
                'outputBase64' => false,
                'addQuietzone' => true,
            ]);
            (new QRCode($options))->render($prepared['payload'], $path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Device activation prepared.');
        $this->line("QR image: {$path}");
        $this->line("Expires at: {$activation->expires_at->toIso8601String()}");

        return self::SUCCESS;
    }
}
