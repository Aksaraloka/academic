<?php

namespace App\Http\Resources\Integration;

use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Program
 */
class ProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'degree' => $this->degree,
            'accreditation' => $this->accreditation,
            'fee_model' => $this->tenant->feeModel(),
            'faculty' => ['id' => $this->faculty->id, 'name' => $this->faculty->name],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
