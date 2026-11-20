<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One region of a zone: type "country" ("NG"), "state" ("NG:LA") or "postcode" ("100*", "90210...99000"). */
class ShippingZoneLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'code'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
