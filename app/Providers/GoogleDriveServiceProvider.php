<?php

declare(strict_types=1);

namespace App\Providers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

/**
 * Registers the `google` disk driver used as the backup destination.
 *
 * The package ships the adapter but no Laravel driver, so without this the
 * `google` disk in config/filesystems.php resolves to nothing.
 */
class GoogleDriveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('google', function ($app, array $config): FilesystemAdapter {
            $client = new Client;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $adapter = new GoogleDriveAdapter(
                new Drive($client),
                $config['folder'] ?? '',
                ['usePermanentDelete' => true],
            );

            return new FilesystemAdapter(new Filesystem($adapter), $adapter);
        });
    }
}
