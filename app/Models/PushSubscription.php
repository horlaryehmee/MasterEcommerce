<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A device (phone, laptop) where an admin turned on notifications. */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'device', 'last_used_at', 'disabled_at'];

    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'disabled_at' => 'datetime'];
    }

    /** Devices that get notifications (not switched off from the device list). */
    public function scopeActive($query)
    {
        return $query->whereNull('disabled_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
