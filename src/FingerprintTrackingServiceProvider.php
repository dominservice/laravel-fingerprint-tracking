<?php

namespace Dominservice\FingerprintTracking;

use Dominservice\FingerprintTracking\Services\TrackingRecorder;
use Dominservice\FingerprintTracking\Services\TrackingEventSynchronizer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class FingerprintTrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fingerprint-tracking.php', 'fingerprint-tracking');

        $this->app->singleton(TrackingRecorder::class, function ($app) {
            return new TrackingRecorder();
        });

        $this->app->singleton(TrackingEventSynchronizer::class, function ($app) {
            return new TrackingEventSynchronizer();
        });

        $this->app->singleton('fingerprint-tracking', function () {
            return new FingerprintTracking();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/fingerprint-tracking.php' => config_path('fingerprint-tracking.php'),
        ], 'fingerprint-tracking-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'fingerprint-tracking-migrations');

        $this->publishes([
            __DIR__ . '/../public/' => public_path('vendor/laravel-fingerprint-tracking'),
        ], 'fingerprint-tracking-assets');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        Blade::directive('fingerprintTracking', function () {
            return "<?php echo app('fingerprint-tracking')->script(); ?>";
        });
    }
}
