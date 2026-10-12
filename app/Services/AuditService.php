<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $actorId = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'actor_id' => $actorId ?? $request?->user()?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 512) : null,
        ]);
    }
}
