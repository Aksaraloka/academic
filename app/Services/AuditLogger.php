<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Catat perubahan data induk atau koreksi presensi (A-05, P-04).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(string $action, Model $subject, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'tenant_id' => auth()->user()?->tenant_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
        ]);
    }

    /**
     * Catat selisih atribut model yang baru saja berubah.
     */
    public function logChanges(string $action, Model $subject): ?AuditLog
    {
        $changes = collect($subject->getChanges())->except('updated_at')->all();

        if ($changes === []) {
            return null;
        }

        $old = collect($changes)->map(fn ($value, string $key) => $subject->getRawOriginal($key))->all();

        return $this->log($action, $subject, $old, $changes);
    }
}
