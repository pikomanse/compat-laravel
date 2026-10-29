<?php

namespace Pikoman\Compat;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Adds the `pikoman` broadcast driver, and a `pikoman` connection read from `.env` unless
 * `config/broadcasting.php` has one: `BROADCAST_CONNECTION=pikoman` is then all it takes.
 */
class PikomanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app['config']->has('broadcasting.connections.pikoman')) {
            $this->app['config']->set('broadcasting.connections.pikoman', [
                'driver' => 'pikoman',
                'app' => env('PIKOMAN_APP'),
                'api_key' => env('PIKOMAN_API_KEY'),
                'private_key' => env('PIKOMAN_PRIVATE_KEY'),
                'encryption_key' => env('PIKOMAN_ENCRYPTION_KEY'),
                'address' => env('PIKOMAN_API_ADDRESS', 'https://next-api.pikoman.se'),
            ]);
        }
    }

    public function boot(): void
    {
        $this->app->make(BroadcastManager::class)->extend('pikoman', function ($app, array $config) {
            // The keys are read when the first event is broadcast or the first channel authorized.
            return new PikomanBroadcaster(function () use ($config) {
                foreach (['app' => 'PIKOMAN_APP', 'api_key' => 'PIKOMAN_API_KEY', 'private_key' => 'PIKOMAN_PRIVATE_KEY'] as $field => $env) {
                    if (empty($config[$field])) {
                        throw new InvalidArgumentException("Pikoman: set {$env} in .env (from the dashboard at pikoman.se)");
                    }
                }

                return new Client(
                    $config['app'],
                    $config['api_key'],
                    $config['private_key'],
                    $config['address'] ?? 'https://next-api.pikoman.se',
                    $config['signing_key_id'] ?? null,
                    $config['encryption_key'] ?? null,
                );
            });
        });
    }
}
