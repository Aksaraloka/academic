<?php

namespace App\Http\Resources\Integration;

use App\Enums\StudentStatus;
use App\Models\User;
use App\Services\IntegrationApps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'identity_number' => $this->identityNumber(),
            'role' => $this->roleFor($request),
            'is_homeroom' => $this->isHomeroomTeacher(),
            'status' => $this->student?->status?->value ?? StudentStatus::Active->value,
            'program' => $this->when($this->student?->program !== null, fn (): array => [
                'code' => $this->student->program->code,
                'name' => $this->student->program->name,
            ]),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Peran yang dikirim: untuk LMS peran padanan di LMS (kontrak v1), untuk Admission peran Academic asli
     * (admin, principal, curriculum, finance).
     */
    private function roleFor(Request $request): string
    {
        $app = app(IntegrationApps::class)->fromRequest($request);

        return $app->key === IntegrationApps::LMS ? ($app->roleFor($this->resource) ?? $this->role->value) : $this->role->value;
    }
}
