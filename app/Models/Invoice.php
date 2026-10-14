<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'student_id', 'academic_term_id', 'number', 'va_number', 'title', 'total', 'status', 'due_on', 'issued_at', 'paid_at', 'krs_exempt', 'notes', 'created_by'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'due_on' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'krs_exempt' => 'boolean',
            'total' => 'integer',
        ];
    }

    /**
     * Tagihan yang boleh dilihat: milik sendiri (mahasiswa), milik anak (orang tua), atau semua bagi admin, keuangan, dan pimpinan.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Admin, Role::Finance, Role::Principal)) {
            return;
        }

        if ($user->hasRole(Role::Student) && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($user->hasRole(Role::Guardian) && $user->guardian) {
            $query->whereIn('student_id', $user->guardian->students()->select('students.id'));
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Jumlah pembayaran yang sudah terkonfirmasi.
     */
    public function paidAmount(): int
    {
        return (int) $this->payments->where('status', PaymentStatus::Confirmed)->sum('amount');
    }

    /**
     * Pembayaran yang menunggu verifikasi dihitung terpisah agar mahasiswa tidak membayar dua kali.
     */
    public function pendingAmount(): int
    {
        return (int) $this->payments->where('status', PaymentStatus::Pending)->sum('amount');
    }

    public function outstanding(): int
    {
        return max(0, $this->total - $this->paidAmount());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true) && $this->due_on->isBefore(today());
    }
}
