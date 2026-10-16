<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Catat perubahan data induk atau koreksi presensi (A-05, P-04).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  User|null  $actor  pelaku bila tidak ada sesi login (mis. permintaan API dari LMS atas nama dosen)
     */
    public function log(string $action, Model $subject, array $old = [], array $new = [], ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'tenant_id' => $actor?->tenant_id ?? auth()->user()?->tenant_id,
            'user_id' => $actor?->id ?? auth()->id(),
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
