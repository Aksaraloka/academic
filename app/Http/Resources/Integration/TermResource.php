<?php

namespace App\Http\Resources\Integration;

use App\Models\AcademicTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicTerm
 */
class TermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'semester' => $this->semester,
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at' => $this->ends_at->toDateString(),
            'is_active' => $this->is_active,
            'is_locked' => $this->is_locked,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
