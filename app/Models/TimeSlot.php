<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'day', 'period_number', 'starts_at', 'ends_at', 'is_break'])]
class TimeSlot extends Model
{
    use BelongsToTenant;

    /**
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_break' => 'boolean'];
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day] ?? '-';
    }

    public function timeRange(): string
    {
        return substr($this->starts_at, 0, 5).'–'.substr($this->ends_at, 0, 5);
    }
}
