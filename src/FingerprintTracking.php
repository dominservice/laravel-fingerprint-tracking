<?php

namespace Dominservice\FingerprintTracking;

class FingerprintTracking
{
    public function script(): string
    {
        if (!config('fingerprint-tracking.enabled')) {
            return '';
        }

        $config = [
            'endpoint' => url(trim(config('fingerprint-tracking.routes.prefix'), '/') . '/events'),
            'cookie_name' => config('fingerprint-tracking.cookie.name'),
            'auto_page_view' => (bool) config('fingerprint-tracking.tracking.auto_page_view'),
            'attach_hidden_fields' => (bool) config('fingerprint-tracking.tracking.attach_hidden_fields'),
            'form_selector' => (string) config('fingerprint-tracking.tracking.form_selector'),
            'event_name' => (string) config('fingerprint-tracking.tracking.event_name'),
            'csrf_token' => csrf_token(),
        ];

        return '<script>window.dominserviceFingerprintTrackingConfig = '
            . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ';</script>'
            . '<script defer src="' . asset('vendor/laravel-fingerprint-tracking/fingerprint-tracking.js') . '"></script>';
    }
}
