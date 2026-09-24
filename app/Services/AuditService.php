<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public static function log($action, $module, $recordId = null, $oldValue = null, $newValue = null, ?string $description = null)
    {
        $request = request();

        // Backwards compatible: if $description omitted, build one.
        if ($description === null && is_string($newValue)) {
            $description = $newValue;
            $newValue = null;
        }

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'module' => $module,
                'record_id' => $recordId !== null ? (string) $recordId : null,
                'description' => $description,
                'old_value' => is_array($oldValue) ? $oldValue : ($oldValue !== null ? ['value' => $oldValue] : null),
                'new_value' => is_array($newValue) ? $newValue : ($newValue !== null ? ['value' => $newValue] : null),
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the main transaction.
            \Log::warning('Audit log failed: '.$e->getMessage());
        }
    }
}
