<?php

namespace App\Models;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'student_id', 'type', 'status', 'purpose', 'details', 'snapshot', 'number', 'number_year', 'number_seq', 'verification_code', 'decision_note', 'decided_by', 'decided_at', 'issued_at'])]
class LetterRequest extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LetterType::class,
            'status' => LetterStatus::class,
            'details' => 'array',
            'snapshot' => 'array',
            'decided_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isIssued(): bool
    {
        return $this->status === LetterStatus::Issued;
    }

    /**
     * Tautan publik yang dikodekan pada QR surat.
     */
    public function verificationUrl(): ?string
    {
        return $this->verification_code ? route('letters.verify', $this->verification_code) : null;
    }
}
