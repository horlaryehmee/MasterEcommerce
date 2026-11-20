<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** Local record of what the update agent did (kept even when the update server is unreachable). */
class AgentLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    public static function write(string $level, string $message, ?int $target = null, ?array $context = null): void
    {
        try {
            static::create(['level' => $level, 'message' => mb_substr($message, 0, 1000), 'target_id' => $target, 'context' => $context]);
        } catch (\Throwable) {
            // Database unavailable mid-update: fall back to the Laravel log.
        }
        Log::channel(config('logging.default'))->log($level === 'success' ? 'info' : $level, '[update-agent] '.$message, $context ?? []);
    }
}
