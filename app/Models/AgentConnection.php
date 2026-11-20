<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** This site's connection to the central update server (at most one row). */
class AgentConnection extends Model
{
    protected $table = 'update_agent';

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'pending' => 'array',
            'connected_at' => 'datetime',
            'last_contact_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        try {
            return static::query()->first();
        } catch (\Throwable) {
            return null; // table not migrated yet
        }
    }
}
