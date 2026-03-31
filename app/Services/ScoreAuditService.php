<?php

namespace App\Services;

use App\Models\ScoreAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ScoreAuditService
{
    public static function log(
        int $matchId,
        Model $auditable,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?Request $request = null,
    ): ScoreAuditLog {
        $request ??= request();

        return ScoreAuditLog::create([
            'match_id' => $matchId,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $request->user()?->id,
            'device_id' => $request->header('X-Device-Id', $request->input('device_id')),
            'ip_address' => $request->ip(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    public static function logCreated(int $matchId, Model $auditable, ?Request $request = null): ScoreAuditLog
    {
        return static::log($matchId, $auditable, 'created', null, $auditable->toArray(), null, $request);
    }

    public static function logUpdated(int $matchId, Model $auditable, array $oldValues, ?string $reason = null, ?Request $request = null): ScoreAuditLog
    {
        $changed = [];
        foreach ($auditable->getChanges() as $key => $value) {
            if (in_array($key, ['updated_at', 'synced_at'])) continue;
            $changed[$key] = $value;
        }

        if (empty($changed)) {
            $changed = $auditable->toArray();
        }

        return static::log($matchId, $auditable, 'updated', $oldValues, $changed, $reason, $request);
    }

    public static function logDeleted(int $matchId, Model $auditable, ?string $reason = null, ?Request $request = null): ScoreAuditLog
    {
        return static::log($matchId, $auditable, 'deleted', $auditable->toArray(), null, $reason, $request);
    }

    public static function logReassigned(
        int $matchId,
        Model $auditable,
        int $oldShooterId,
        int $newShooterId,
        string $reason,
        ?Request $request = null,
    ): ScoreAuditLog {
        return static::log(
            $matchId,
            $auditable,
            'reassigned',
            ['shooter_id' => $oldShooterId],
            ['shooter_id' => $newShooterId],
            $reason,
            $request,
        );
    }

    public static function logReshoot(int $matchId, Model $auditable, string $reason, ?Request $request = null): ScoreAuditLog
    {
        return static::log($matchId, $auditable, 'reshoot', null, ['is_reshoot' => true], $reason, $request);
    }
}
