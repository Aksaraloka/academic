<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\IntegrationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu event webhook ke LMS (outbox). `id` UUID dikirim sebagai `X-Aksaraloka-Event-Id` agar LMS bisa membuang duplikat.
 *
 * @property string $id
 * @property int $tenant_id
 * @property string $app
 * @property string $type
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property CarbonInterface|null $delivered_at
 * @property string|null $last_error
 * @property CarbonInterface $created_at
 */
#[Fillable(['tenant_id', 'app', 'type', 'payload', 'attempts', 'delivered_at', 'last_error'])]
class IntegrationEvent extends Model
{
    /** @use HasFactory<IntegrationEventFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }
}
