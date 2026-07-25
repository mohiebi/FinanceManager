<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportKeysExist();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function ensurePassportKeysExist(): void
    {
        $path = storage_path('passport');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $privateKey = $path.DIRECTORY_SEPARATOR.'oauth-private.key';
        $publicKey = $path.DIRECTORY_SEPARATOR.'oauth-public.key';

        if ($this->hasUsablePassportKey($privateKey) && $this->hasUsablePassportKey($publicKey)) {
            return;
        }

        Artisan::call('passport:keys', [
            '--force' => true,
            '--length' => 2048,
        ]);
    }

    private function hasUsablePassportKey(string $path): bool
    {
        return is_file($path)
            && is_readable($path)
            && str_contains((string) file_get_contents($path), 'BEGIN');
    }
}
