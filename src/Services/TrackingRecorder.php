<?php

namespace Dominservice\FingerprintTracking\Services;

use Dominservice\FingerprintTracking\Models\Fingerprint;
use Dominservice\FingerprintTracking\Models\TrackingEvent;
use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\Cookie;

class TrackingRecorder
{
    public function record(Request $request): array
    {
        $payload = $request->validate([
            'fingerprint' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable'],
            'browser' => ['nullable', 'string', 'max:255'],
            'browserVersion' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:255'],
            'osVersion' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:20'],
            'screenWidth' => ['nullable', 'string', 'max:20'],
            'screenHeight' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'string', 'max:2048'],
            'referer' => ['nullable', 'string', 'max:2048'],
            'routeName' => ['nullable', 'string', 'max:255'],
            'eventName' => ['nullable', 'string', 'max:64'],
            'trackableType' => ['nullable', 'string', 'max:255'],
            'trackableId' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
        ]);

        $agent = new Agent();
        $agent->setUserAgent((string) $request->userAgent());

        $fingerprint = Fingerprint::query()->firstOrCreate(
            ['value' => (string) $payload['fingerprint']],
            [
                'is_mobile' => (bool) Arr::get($payload, 'mobile', $agent->isMobile()),
                'browser' => $this->normalize(Arr::get($payload, 'browser')),
                'browser_version' => Arr::get($payload, 'browserVersion'),
                'os' => $this->normalize(Arr::get($payload, 'os')),
                'os_version' => Arr::get($payload, 'osVersion'),
                'language' => Arr::get($payload, 'language'),
                'screen_width' => Arr::get($payload, 'screenWidth'),
                'screen_height' => Arr::get($payload, 'screenHeight'),
            ]
        );

        $fingerprint->forceFill([
            'last_seen_at' => now(),
            'browser' => $this->normalize(Arr::get($payload, 'browser', $fingerprint->browser)),
            'browser_version' => Arr::get($payload, 'browserVersion', $fingerprint->browser_version),
            'os' => $this->normalize(Arr::get($payload, 'os', $fingerprint->os)),
            'os_version' => Arr::get($payload, 'osVersion', $fingerprint->os_version),
            'language' => Arr::get($payload, 'language', $fingerprint->language),
            'screen_width' => Arr::get($payload, 'screenWidth', $fingerprint->screen_width),
            'screen_height' => Arr::get($payload, 'screenHeight', $fingerprint->screen_height),
        ])->save();

        $geo = $this->resolveGeo($request);
        $user = Auth::user();

        $event = TrackingEvent::query()->create([
            'fingerprint_id' => $fingerprint->getKey(),
            'event_name' => Arr::get($payload, 'eventName', config('fingerprint-tracking.tracking.event_name', 'page_view')),
            'url' => Arr::get($payload, 'url', (string) $request->fullUrl()),
            'referer' => Arr::get($payload, 'referer', (string) $request->headers->get('referer')),
            'route_name' => Arr::get($payload, 'routeName', optional($request->route())->getName()),
            'method' => $request->method(),
            'platform' => $this->normalize($agent->platform()),
            'device' => $this->normalize($agent->deviceType()),
            'is_desktop' => $agent->isDesktop(),
            'is_phone' => $agent->isPhone(),
            'ip' => $request->ip(),
            'region_code' => Arr::get($geo, 'region_code'),
            'country_code' => Arr::get($geo, 'country_code'),
            'timezone' => Arr::get($geo, 'timezone'),
            'is_bogon' => (bool) Arr::get($geo, 'is_bogon', false),
            'is_cloud_provider' => (bool) Arr::get($geo, 'is_cloud_provider', false),
            'is_tor' => (bool) Arr::get($geo, 'is_tor', false),
            'is_tor_exit' => (bool) Arr::get($geo, 'is_tor_exit', false),
            'is_proxy' => (bool) Arr::get($geo, 'is_proxy', false),
            'is_anonymous' => (bool) Arr::get($geo, 'is_anonymous', false),
            'is_abuser' => (bool) Arr::get($geo, 'is_abuser', false),
            'is_attacker' => (bool) Arr::get($geo, 'is_attacker', false),
            'is_threat' => (bool) Arr::get($geo, 'is_threat', false),
            'trackable_type' => Arr::get($payload, 'trackableType'),
            'trackable_id' => Arr::get($payload, 'trackableId'),
            'request_count' => 1,
            'last_request_at' => Carbon::now(),
            'context' => Arr::get($payload, 'context', []),
            'occurred_at' => Carbon::now(),
        ]);

        if (config('fingerprint-tracking.auth.bind_authenticated_user', true) && $user instanceof Authenticatable) {
            $event->forceFill([
                'auth_subject_type' => $user::class,
                'auth_subject_id' => $user->getAuthIdentifier(),
            ])->save();
        }

        return compact('fingerprint', 'event');
    }

    public function makeFingerprintCookie(string $fingerprint): ?Cookie
    {
        if (!$fingerprint) {
            return null;
        }

        /** @var QueueingFactory $cookies */
        $cookies = app('cookie');

        return $cookies->make(
            config('fingerprint-tracking.cookie.name', 'fingerprint'),
            $fingerprint,
            (int) config('fingerprint-tracking.cookie.minutes', 525600),
            '/',
            null,
            (bool) config('fingerprint-tracking.cookie.secure', false),
            false,
            false,
            (string) config('fingerprint-tracking.cookie.same_site', 'lax')
        );
    }

    protected function normalize(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return preg_replace('#\s+#', '_', strtolower(trim($value)));
    }

    protected function resolveGeo(Request $request): array
    {
        if (!config('fingerprint-tracking.geo.enabled')) {
            return [];
        }

        $driver = (string) config('fingerprint-tracking.geo.driver', 'ipregistry');

        if ($driver !== 'ipregistry') {
            return [];
        }

        $key = (string) config('fingerprint-tracking.geo.ipregistry.key');
        if ($key === '' || !$request->ip()) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('fingerprint-tracking.geo.ipregistry.timeout', 3))
                ->get('https://api.ipregistry.co/' . $request->ip(), ['key' => $key])
                ->json();
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($response)) {
            return [];
        }

        return [
            'region_code' => Arr::get($response, 'location.region.code'),
            'country_code' => Arr::get($response, 'location.country.code'),
            'timezone' => Arr::get($response, 'time_zone.id'),
            'is_bogon' => Arr::get($response, 'security.is_bogon', false),
            'is_cloud_provider' => Arr::get($response, 'security.is_cloud_provider', false),
            'is_tor' => Arr::get($response, 'security.is_tor', false),
            'is_tor_exit' => Arr::get($response, 'security.is_tor_exit', false),
            'is_proxy' => Arr::get($response, 'security.is_proxy', false),
            'is_anonymous' => Arr::get($response, 'security.is_anonymous', false),
            'is_abuser' => Arr::get($response, 'security.is_abuser', false),
            'is_attacker' => Arr::get($response, 'security.is_attacker', false),
            'is_threat' => Arr::get($response, 'security.is_threat', false),
        ];
    }
}
