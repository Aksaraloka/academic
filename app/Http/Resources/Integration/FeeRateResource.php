<?php

namespace App\Http\Resources\Integration;

use App\Models\FeeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeRate
 */
class FeeRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind,
            'ukt_group' => $this->ukt_group === null ? null : (int) $this->ukt_group,
            'amount' => (int) $this->amount,
        ];
    }
}
