<?php

namespace Dominservice\FingerprintTracking\Services;

use Dominservice\FingerprintTracking\Models\TrackingEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use RuntimeException;

class TrackingEventSynchronizer
{
    public function synchronizeProtectedRequest(Request $request, array $payload): ?TrackingEvent
    {
        $trackingEventUlid = (string) Arr::get($payload, 'tracking_event_ulid', '');

        if ($trackingEventUlid === '') {
            return null;
        }

        /** @var TrackingEvent|null $event */
        $event = TrackingEvent::query()
            ->with('fingerprint')
            ->where('public_ulid', $trackingEventUlid)
            ->first();

        if (!$event) {
            throw new RuntimeException('Tracking event not found.');
        }

        $jwtFingerprint = $this->nullableString(Arr::get($payload, 'fingerprint'));
        $requestFingerprint = $this->nullableString($request->input('fingerprint'));
        $eventFingerprint = $this->nullableString($event->fingerprint?->value);

        if ($jwtFingerprint && $eventFingerprint && $jwtFingerprint !== $eventFingerprint) {
            throw new RuntimeException('Tracking fingerprint mismatch.');
        }

        if ($requestFingerprint && $jwtFingerprint && $requestFingerprint !== $jwtFingerprint) {
            throw new RuntimeException('Request fingerprint mismatch.');
        }

        if ($requestFingerprint && $eventFingerprint && $requestFingerprint !== $eventFingerprint) {
            throw new RuntimeException('Stored fingerprint mismatch.');
        }

        $context = $event->context ?? [];
        $context['invis'] = [
            'score' => Arr::get($payload, 'score'),
            'verified_at' => now()->toIso8601String(),
            'verified_ip' => $request->ip(),
            'verified_route' => optional($request->route())->getName(),
            'verified_method' => $request->method(),
        ];

        $event->forceFill([
            'invis_score' => Arr::get($payload, 'score'),
            'invis_verified_at' => Carbon::now(),
            'last_request_at' => Carbon::now(),
            'request_count' => ((int) $event->request_count) + 1,
            'context' => $context,
        ]);

        if (config('fingerprint-tracking.auth.bind_authenticated_user', true) && $request->user()) {
            $this->fillAuthenticatedSubject($event, $request->user());
        }

        $event->save();

        $request->attributes->set(
            (string) config('fingerprint-tracking.tracking.request_attribute', 'fingerprint_tracking_event'),
            $event
        );

        $request->attributes->set(
            (string) config('fingerprint-tracking.tracking.payload_attribute', 'fingerprint_tracking_payload'),
            $payload
        );

        return $event;
    }

    public function fillAuthenticatedSubject(TrackingEvent $event, Authenticatable $user): void
    {
        $event->setAttribute('auth_subject_type', $user::class);
        $event->setAttribute('auth_subject_id', $user->getAuthIdentifier());
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
