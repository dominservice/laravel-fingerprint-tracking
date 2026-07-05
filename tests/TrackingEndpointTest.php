<?php

namespace Dominservice\FingerprintTracking\Tests;

use Dominservice\FingerprintTracking\Models\Fingerprint;
use Dominservice\FingerprintTracking\Models\TrackingEvent;

class TrackingEndpointTest extends TestCase
{
    public function test_tracking_endpoint_creates_records_and_returns_public_ulids(): void
    {
        $response = $this->postJson('/fingerprint-tracking/events', [
            'fingerprint' => 'fp-test-123',
            'mobile' => false,
            'browser' => 'Firefox',
            'browserVersion' => '141',
            'os' => 'Linux',
            'osVersion' => '6.8',
            'language' => 'pl',
            'screenWidth' => '1920',
            'screenHeight' => '1080',
            'url' => 'https://example.test/offers/trip',
            'referer' => 'https://example.test/',
            'routeName' => 'offers.show',
            'eventName' => 'page_view',
            'trackableType' => 'trip',
            'trackableId' => '20',
            'context' => [
                'source' => 'public_offer_page',
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'fingerprint_ulid',
                'tracking_event_ulid',
            ])
            ->assertJsonMissingPath('fingerprint_id')
            ->assertJsonMissingPath('tracking_event_id');

        $this->assertSame('OK', $response->json('status'));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $response->json('fingerprint_ulid'));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $response->json('tracking_event_ulid'));

        $fingerprint = Fingerprint::query()->first();
        $event = TrackingEvent::query()->first();

        $this->assertNotNull($fingerprint);
        $this->assertNotNull($event);
        $this->assertSame('fp-test-123', $fingerprint->value);
        $this->assertSame('firefox', $fingerprint->browser);
        $this->assertSame('linux', $fingerprint->os);
        $this->assertSame($fingerprint->getKey(), $event->fingerprint_id);
        $this->assertSame('trip', $event->trackable_type);
        $this->assertSame('20', $event->trackable_id);
        $this->assertSame('public_offer_page', data_get($event->context, 'source'));
        $this->assertSame(1, $event->request_count);
    }

    public function test_tracking_endpoint_requires_fingerprint(): void
    {
        $response = $this->postJson('/fingerprint-tracking/events', [
            'eventName' => 'page_view',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fingerprint']);
    }

    public function test_existing_fingerprint_is_reused_and_last_seen_is_updated(): void
    {
        $firstResponse = $this->postJson('/fingerprint-tracking/events', [
            'fingerprint' => 'fp-reused-1',
            'eventName' => 'page_view',
        ]);

        $firstResponse->assertOk();

        $firstFingerprint = Fingerprint::query()->first();
        $firstSeen = $firstFingerprint?->last_seen_at;

        sleep(1);

        $secondResponse = $this->postJson('/fingerprint-tracking/events', [
            'fingerprint' => 'fp-reused-1',
            'eventName' => 'form_view',
        ]);

        $secondResponse->assertOk();

        $this->assertSame(1, Fingerprint::query()->count());
        $this->assertSame(2, TrackingEvent::query()->count());

        $fingerprint = Fingerprint::query()->first();
        $this->assertNotNull($fingerprint);
        $this->assertNotNull($fingerprint->last_seen_at);
        $this->assertTrue($fingerprint->last_seen_at->greaterThanOrEqualTo($firstSeen));
    }
}
