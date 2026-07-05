<?php

namespace Dominservice\FingerprintTracking\Tests;

class BladeDirectiveTest extends TestCase
{
    public function test_blade_directive_is_registered(): void
    {
        $compiled = $this->app['blade.compiler']->compileString('@fingerprintTracking');

        $this->assertStringContainsString("app('fingerprint-tracking')->script()", $compiled);
    }

    public function test_script_output_contains_endpoint_and_asset(): void
    {
        $html = app('fingerprint-tracking')->script();

        $this->assertStringContainsString('window.dominserviceFingerprintTrackingConfig', $html);
        $this->assertStringContainsString('/fingerprint-tracking/events', $html);
        $this->assertStringContainsString('/vendor/laravel-fingerprint-tracking/fingerprint-tracking.js', $html);
        $this->assertStringContainsString('csrf_token', $html);
    }

    public function test_script_output_is_empty_when_package_is_disabled(): void
    {
        config()->set('fingerprint-tracking.enabled', false);

        $this->assertSame('', app('fingerprint-tracking')->script());
    }
}
