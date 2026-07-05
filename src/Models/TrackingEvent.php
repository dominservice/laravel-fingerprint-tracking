<?php

namespace Dominservice\FingerprintTracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class TrackingEvent extends Model
{
    protected $table = 'tracking_events';

    protected $fillable = [
        'public_ulid',
        'fingerprint_id',
        'auth_subject_type',
        'auth_subject_id',
        'event_name',
        'url',
        'referer',
        'route_name',
        'method',
        'platform',
        'device',
        'is_desktop',
        'is_phone',
        'ip',
        'region_code',
        'country_code',
        'timezone',
        'is_bogon',
        'is_cloud_provider',
        'is_tor',
        'is_tor_exit',
        'is_proxy',
        'is_anonymous',
        'is_abuser',
        'is_attacker',
        'is_threat',
        'trackable_type',
        'trackable_id',
        'request_count',
        'invis_score',
        'invis_verified_at',
        'last_request_at',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'is_desktop' => 'boolean',
        'is_phone' => 'boolean',
        'is_bogon' => 'boolean',
        'is_cloud_provider' => 'boolean',
        'is_tor' => 'boolean',
        'is_tor_exit' => 'boolean',
        'is_proxy' => 'boolean',
        'is_anonymous' => 'boolean',
        'is_abuser' => 'boolean',
        'is_attacker' => 'boolean',
        'is_threat' => 'boolean',
        'request_count' => 'integer',
        'invis_score' => 'decimal:4',
        'invis_verified_at' => 'datetime',
        'last_request_at' => 'datetime',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (!$model->public_ulid) {
                $model->public_ulid = (string) Str::ulid();
            }
        });
    }

    public function fingerprint(): BelongsTo
    {
        return $this->belongsTo(Fingerprint::class, 'fingerprint_id');
    }

    public function authSubject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'auth_subject_type', 'auth_subject_id');
    }
}
