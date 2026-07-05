<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprints', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_ulid')->unique();
            $table->string('value')->unique();
            $table->boolean('is_mobile')->default(false);
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('os')->nullable();
            $table->string('os_version')->nullable();
            $table->string('language', 20)->nullable();
            $table->string('screen_width', 20)->nullable();
            $table->string('screen_height', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_ulid')->unique();
            $table->foreignId('fingerprint_id')->nullable()->constrained('fingerprints')->nullOnDelete();
            $this->addNullableMorphColumns(
                $table,
                (string) config('fingerprint-tracking.auth.morph_name', 'auth_subject'),
                (string) config('fingerprint-tracking.auth.key_type', 'id')
            );
            $table->string('event_name', 64)->default('page_view')->index();
            $table->string('url', 2048)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->string('route_name')->nullable();
            $table->string('method', 16)->nullable();
            $table->string('platform')->nullable();
            $table->string('device')->nullable();
            $table->boolean('is_desktop')->default(false);
            $table->boolean('is_phone')->default(false);
            $table->string('ip', 64)->nullable();
            $table->string('region_code', 20)->nullable();
            $table->string('country_code', 20)->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_bogon')->default(false);
            $table->boolean('is_cloud_provider')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_tor_exit')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_abuser')->default(false);
            $table->boolean('is_attacker')->default(false);
            $table->boolean('is_threat')->default(false);
            $table->string('trackable_type')->nullable();
            $table->string('trackable_id')->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->decimal('invis_score', 6, 4)->nullable();
            $table->timestamp('invis_verified_at')->nullable();
            $table->timestamp('last_request_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index([
                (string) config('fingerprint-tracking.auth.morph_name', 'auth_subject') . '_type',
                (string) config('fingerprint-tracking.auth.morph_name', 'auth_subject') . '_id',
            ], 'tracking_events_auth_subject_index');
            $table->index(['trackable_type', 'trackable_id']);
        });
    }

    protected function addNullableMorphColumns(Blueprint $table, string $name, string $keyType): void
    {
        $keyType = strtolower(trim($keyType));

        if ($keyType === 'uuid') {
            $table->nullableUuidMorphs($name);
            return;
        }

        if ($keyType === 'ulid') {
            $table->nullableUlidMorphs($name);
            return;
        }

        if ($keyType === 'string') {
            $table->string($name . '_type')->nullable();
            $table->string($name . '_id')->nullable();
            return;
        }

        $table->nullableMorphs($name);
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('fingerprints');
    }
};
