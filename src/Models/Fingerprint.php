<?php

namespace Dominservice\FingerprintTracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fingerprint extends Model
{
    protected $table = 'fingerprints';

    protected $fillable = [
        'public_ulid',
        'value',
        'is_mobile',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'language',
        'screen_width',
        'screen_height',
        'metadata',
        'last_seen_at',
    ];

    protected $casts = [
        'is_mobile' => 'boolean',
        'metadata' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (!$model->public_ulid) {
                $model->public_ulid = (string) Str::ulid();
            }
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class, 'fingerprint_id');
    }
}
