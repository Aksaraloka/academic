<?php

namespace App\Http\Resources\Integration;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class RosterStudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'identity_number' => $this->nim,
            'status' => $this->status->value,
        ];
    }
}
