<?php

namespace App\Services;

use App\Enums\GraduationStatus;
use App\Enums\StudentStatus;
use App\Enums\ThesisStatus;
use App\Models\GraduationMinute;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Models\Student;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GraduationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AcademicRecord $records,
        private readonly YudisiumService $yudisium,
    ) {}

    /**
     * Kelayakan mendaftar wisuda berdasarkan transkrip otomatis: SKS lulus dan IPK dibandingkan syarat kampus.
     *
     * @return array{credits: int, gpa: ?float, min_credits: int, min_gpa: float, has_grades: bool, meets_credits: bool, meets_gpa: bool, eligible: bool}
     */
    public function eligibility(Student $student): array
    {
        $record = $this->records->eligibility($student);
        $minCredits = (int) $student->tenant->setting('graduation_min_credits');
        $minGpa = (float) $student->tenant->setting('graduation_min_gpa');
        $meetsCredits = $record['credits'] >= $minCredits;
        $meetsGpa = $record['gpa'] !== null && $record['gpa'] >= $minGpa;

        return $record + [
            'min_credits' => $minCredits,
            'min_gpa' => $minGpa,
            'meets_credits' => $meetsCredits,
            'meets_gpa' => $meetsGpa,
            'eligible' => $record['has_grades'] && $meetsCredits && $meetsGpa,
        ];
    }

    /**
     * Mahasiswa mendaftar wisuda. Pendaftaran yang ditolak boleh diajukan ulang.
     *
     * SKS lulus dan IPK tidak diisi manual; keduanya diambil dari transkrip saat mendaftar.
     *
     * @param  array{thesis_title: string, defense_date: string, gown_size?: ?string}  $data
     */
    public function register(Student $student, GraduationPeriod $period, array $data): GraduationRegistration
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['period' => 'Pendaftaran untuk periode ini sedang tidak dibuka.']);
        }

        if (! in_array($student->status, [StudentStatus::Active, StudentStatus::Graduated], true)) {
            throw ValidationException::withMessages(['period' => 'Hanya mahasiswa aktif atau yang sudah lulus yudisium yang bisa mendaftar wisuda.']);
        }

        if ($this->yudisium->decisionFor($student) === null) {
            throw ValidationException::withMessages(['eligibility' => 'Kamu belum dinyatakan lulus yudisium. Pendaftaran wisuda dibuka setelah penetapan kelulusan.']);
        }

        // Judul dan tanggal sidang diambil dari tugas akhir yang lulus sidang bila ada.
        $thesis = Thesis::query()->with('latestDefense')->where('student_id', $student->id)->where('status', ThesisStatus::Completed)->latest('id')->first();

        if ($thesis) {
            $data['thesis_title'] = $thesis->title;
            $data['defense_date'] = ($thesis->latestDefense?->scheduled_on ?? $thesis->completed_at)->toDateString();
        }

        $existing = GraduationRegistration::query()
            ->where('graduation_period_id', $period->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing && $existing->status !== GraduationStatus::Rejected) {
            throw ValidationException::withMessages(['period' => 'Kamu sudah terdaftar di periode ini.']);
        }

        if (! $existing && $period->isFull()) {
            throw ValidationException::withMessages(['period' => 'Kuota wisuda periode ini sudah penuh.']);
        }

        $eligibility = $this->eligibility($student);

        if (! $eligibility['has_grades']) {
            throw ValidationException::withMessages(['eligibility' => 'Belum ada nilai yang tercatat di transkripmu. Hubungi BAAK bila nilai awalmu belum diinput.']);
        }

        if (! $eligibility['meets_credits']) {
            throw ValidationException::withMessages(['eligibility' => "SKS lulusmu {$eligibility['credits']}, belum mencapai minimal {$eligibility['min_credits']} untuk mendaftar wisuda."]);
        }

        if (! $eligibility['meets_gpa']) {
            throw ValidationException::withMessages(['eligibility' => 'IPK '.number_format((float) $eligibility['gpa'], 2, ',', '.').' belum mencapai minimal '.number_format($eligibility['min_gpa'], 2, ',', '.').' untuk mendaftar wisuda.']);
        }

        $attributes = [
            'thesis_title' => $data['thesis_title'],
            'gpa' => round((float) $eligibility['gpa'], 2),
            'credits_earned' => $eligibility['credits'],
            'defense_date' => $data['defense_date'],
            'gown_size' => $data['gown_size'] ?? null,
            'status' => GraduationStatus::Submitted,
            'rejection_reason' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return GraduationRegistration::create($attributes + [
            'tenant_id' => $student->tenant_id,
            'graduation_period_id' => $period->id,
            'student_id' => $student->id,
        ]);
    }

    /**
     * Admin menyetujui atau menolak pendaftaran. Berita acara yang sudah terbit mengunci keputusan.
     */
    public function decide(GraduationRegistration $registration, User $verifier, bool $approve, ?string $reason = null): GraduationRegistration
    {
        if ($registration->period->isFinalized()) {
            throw ValidationException::withMessages(['decision' => 'Berita acara sudah diterbitkan, keputusan tidak bisa diubah.']);
        }

        if (! $approve && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi.']);
        }

        $before = $registration->status;

        $registration->update([
            'status' => $approve ? GraduationStatus::Approved : GraduationStatus::Rejected,
            'rejection_reason' => $approve ? null : $reason,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);

        $this->audit->log('graduation.verified', $registration, ['status' => $before->value], ['status' => $registration->status->value]);

        return $registration;
    }

    /**
     * Terbitkan berita acara: membekukan daftar peserta yang disetujui sebagai arsip resmi.
     *
     * @param  array{number: string, issued_on: string, chair_name: string, chair_title: string, approver_name: string, approver_title: string, notes?: ?string}  $data
     */
    public function finalizeMinutes(GraduationPeriod $period, User $actor, array $data): GraduationMinute
    {
        if ($period->isFinalized()) {
            throw ValidationException::withMessages(['number' => 'Berita acara periode ini sudah diterbitkan.']);
        }

        $pending = $period->registrations()->where('status', GraduationStatus::Submitted)->count();

        if ($pending > 0) {
            throw ValidationException::withMessages(['number' => "Masih ada {$pending} pendaftaran yang menunggu verifikasi."]);
        }

        $approved = $period->registrations()
            ->with('student.program')
            ->where('status', GraduationStatus::Approved)
            ->get()
            ->sortBy(fn (GraduationRegistration $registration): string => ($registration->student->program?->name ?? '').$registration->student->name)
            ->values();

        if ($approved->isEmpty()) {
            throw ValidationException::withMessages(['number' => 'Belum ada pendaftar yang disetujui.']);
        }

        $participants = $approved->map(fn (GraduationRegistration $registration): array => [
            'name' => $registration->student->name,
            'nim' => $registration->student->nim,
            'program' => $registration->student->program?->label() ?? '-',
            'gpa' => number_format((float) $registration->gpa, 2, ',', '.'),
            'credits' => $registration->credits_earned,
            'thesis_title' => $registration->thesis_title,
            'defense_date' => $registration->defense_date->toDateString(),
            'gown_size' => $registration->gown_size,
        ])->all();

        return DB::transaction(function () use ($period, $actor, $data, $participants): GraduationMinute {
            $minute = GraduationMinute::query()->updateOrCreate(
                ['graduation_period_id' => $period->id],
                $data + ['participants' => $participants, 'finalized_by' => $actor->id, 'finalized_at' => now()],
            );

            $this->audit->log('graduation.minutes_finalized', $minute, [], ['number' => $minute->number, 'participants' => count($participants)]);

            return $minute;
        });
    }

    /**
     * Usulan nomor berita acara, mis. BA/WSD/003/X/2026.
     */
    public function suggestNumber(GraduationPeriod $period): string
    {
        $roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][now()->month];

        return sprintf('BA/WSD/%03d/%s/%d', $period->id, $roman, now()->year);
    }
}
