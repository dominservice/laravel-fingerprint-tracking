<?php

namespace Dominservice\FingerprintTracking\Tests;

use Dominservice\FingerprintTracking\Services\TrackingRecorder;
use Illuminate\Support\Facades\Route;

class ServiceProviderTest extends TestCase
{
    public function test_tracking_service_is_registered(): void
    {
        $service = $this->app->make(TrackingRecorder::class);

        $this->assertInstanceOf(TrackingRecorder::class, $service);
    }

    public function test_tracking_route_is_registered(): void
    {
        $route = Route::getRoutes()->getByName('fingerprint-tracking.events.store');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame('fingerprint-tracking/events', ltrim($route->uri(), '/'));
    }
}
