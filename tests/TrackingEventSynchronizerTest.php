<?php

namespace Dominservice\FingerprintTracking\Tests;

use Dominservice\FingerprintTracking\Models\Fingerprint;
use Dominservice\FingerprintTracking\Models\TrackingEvent;
use Dominservice\FingerprintTracking\Services\TrackingEventSynchronizer;
use Dominservice\FingerprintTracking\Tests\Support\TestUser;
use Illuminate\Http\Request;

class TrackingEventSynchronizerTest extends TestCase
{
    public function test_synchronize_protected_request_updates_tracking_event_and_binds_authenticated_user(): void
    {
        $user = TestUser::query()->create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);

        $fingerprint = Fingerprint::query()->create([
            'value' => 'fp-synced-1',
        ]);

        $event = TrackingEvent::query()->create([
            'fingerprint_id' => $fingerprint->getKey(),
            'event_name' => 'page_view',
            'request_count' => 1,
            'occurred_at' => now(),
        ]);

        $request = Request::create('/checkout', 'POST', [
            'fingerprint' => 'fp-synced-1',
        ]);
        $request->setUserResolver(fn () => $user);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $synchronizer = app(TrackingEventSynchronizer::class);
        $syncedEvent = $synchronizer->synchronizeProtectedRequest($request, [
            'tracking_event_ulid' => $event->public_ulid,
            'fingerprint' => 'fp-synced-1',
            'score' => 0.91,
        ]);

        $this->assertNotNull($syncedEvent);
        $this->assertSame($event->getKey(), $syncedEvent->getKey());
        $this->assertSame(2, $syncedEvent->fresh()->request_count);
        $this->assertSame('0.9100', $syncedEvent->fresh()->invis_score);
        $this->assertNotNull($syncedEvent->fresh()->invis_verified_at);
        $this->assertSame(TestUser::class, $syncedEvent->fresh()->auth_subject_type);
        $this->assertSame((string) $user->getKey(), (string) $syncedEvent->fresh()->auth_subject_id);
        $this->assertSame($event->getKey(), $request->attributes->get('fingerprint_tracking_event')->getKey());
        $this->assertSame('fp-synced-1', $request->attributes->get('fingerprint_tracking_payload')['fingerprint']);
    }

    public function test_synchronize_protected_request_rejects_fingerprint_mismatch(): void
    {
        $fingerprint = Fingerprint::query()->create([
            'value' => 'fp-synced-2',
        ]);

        $event = TrackingEvent::query()->create([
            'fingerprint_id' => $fingerprint->getKey(),
            'event_name' => 'page_view',
            'request_count' => 1,
            'occurred_at' => now(),
        ]);

        $request = Request::create('/checkout', 'POST', [
            'fingerprint' => 'another-fingerprint',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request fingerprint mismatch.');

        app(TrackingEventSynchronizer::class)->synchronizeProtectedRequest($request, [
            'tracking_event_ulid' => $event->public_ulid,
            'fingerprint' => 'fp-synced-2',
            'score' => 0.91,
        ]);
    }
}
