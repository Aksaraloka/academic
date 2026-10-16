<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Exceptions\AdmissionHandoffException;
use App\Jobs\FetchAdmissionPhoto;
use App\Jobs\SendPasswordSetupLink;
use App\Models\AcademicTerm;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Serah terima calon mahasiswa dari Admission: membuat akun (kata sandi acak), data mahasiswa berstatus aktif, NIM dari
 * pola kampus, tagihan semester pertama, dan pembayaran UKT pertama yang sudah terkonfirmasi, semuanya dalam satu
 * transaksi. Idempoten lewat `admission_ref`. Buku besar keuangan tetap satu, di Academic (lewat `FinanceService`).
 */
class AdmissionHandoffService
{
    /** @var array<string, string> relasi di kontrak Admission => nilai `guardians.relation` yang dipakai Academic */
    public const GUARDIAN_RELATIONS = ['father' => 'Ayah', 'mother' => 'Ibu', 'guardian' => 'Wali'];

    public function __construct(
        private readonly FinanceService $finance,
        private readonly NimGenerator $nim,
        private readonly StudyGroupPlacement $placement,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  isi `POST /admissions/students` yang sudah tervalidasi
     * @return array{created: bool, data: array{user_id: int, student_id: int, nim: string, study_group: array{id: int, name: string}|null, photo_status: string, invoice: array{number: string, status: string, total: int, paid: int}|null}}
     *
     * @throws AdmissionHandoffException
     */
    public function handle(int $tenantId, array $data): array
    {
        $existing = $this->findByRef($tenantId, $data['admission_ref']);

        if ($existing !== null) {
            return ['created' => false, 'data' => $this->response($existing, $this->queuePhoto($existing, $data))];
        }

        $program = Program::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($data['program_id']);
        $term = AcademicTerm::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($data['term_id']);

        $this->guardUniqueness($tenantId, $data);
        $this->guardFeeModel($tenantId, $data);

        try {
            $student = DB::transaction(fn (): Student => $this->create($tenantId, $program, $term, $data));
        } catch (UniqueConstraintViolationException $e) {
            // Dua permintaan dengan ref yang sama berpacu: yang kalah membaca hasil pemenang.
            $existing = $this->findByRef($tenantId, $data['admission_ref']);

            if ($existing !== null) {
                return ['created' => false, 'data' => $this->response($existing, $this->queuePhoto($existing, $data))];
            }

            $this->guardUniqueness($tenantId, $data);

            throw $e;
        }

        SendPasswordSetupLink::dispatch($student->user_id);

        return ['created' => true, 'data' => $this->response($student, $this->queuePhoto($student, $data))];
    }

    /**
     * Antrekan unduhan pas foto bila payload membawanya dan mahasiswa belum punya foto. Panggilan ulang dengan URL
     * baru dengan sengaja boleh mengulang unduhan yang sebelumnya gagal (mis. URL kedaluwarsa).
     *
     * @param  array<string, mixed>  $data
     */
    private function queuePhoto(Student $student, array $data): string
    {
        $photo = $data['photo'] ?? null;

        if (! is_array($photo) || $student->photo_path !== null) {
            return 'none';
        }

        FetchAdmissionPhoto::dispatch($student->id, $photo['url'], strtolower($photo['sha256']), $photo['mime']);

        return 'queued';
    }

    /**
     * Model UKT butuh golongan agar tarifnya pasti; model flat mengabaikannya.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AdmissionHandoffException
     */
    private function guardFeeModel(int $tenantId, array $data): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        if ($tenant->feeModel() === 'ukt' && ($data['ukt_group'] ?? null) === null) {
            throw new AdmissionHandoffException('ukt_group_required', 'Kampus memakai model UKT bertingkat, sehingga ukt_group wajib diisi.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AdmissionHandoffException
     */
    private function guardUniqueness(int $tenantId, array $data): void
    {
        $email = Str::lower(trim($data['student']['email']));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new AdmissionHandoffException('email_taken', 'Email sudah dipakai pengguna lain.', 409);
        }

        $nik = trim((string) ($data['student']['nik'] ?? ''));

        if ($nik !== '' && Student::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('nik', $nik)->exists()) {
            throw new AdmissionHandoffException('nik_taken', 'NIK sudah terdaftar sebagai mahasiswa.', 409);
        }

        $reference = $data['initial_payment']['reference'];

        if (Payment::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('reference', $reference)->exists()) {
            throw new AdmissionHandoffException('payment_reference_used', 'Referensi pembayaran sudah tercatat untuk tagihan lain.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(int $tenantId, Program $program, AcademicTerm $term, array $data): Student
    {
        $profile = $data['student'];
        $batchYear = (int) $data['batch_year'];

        $user = new User([
            'tenant_id' => $tenantId, 'name' => trim($profile['name']), 'email' => Str::lower(trim($profile['email'])),
            'phone' => $profile['phone'] ?? null, 'role' => Role::Student, 'password' => Str::random(64),
        ]);
        $user->save();

        $student = Student::create([
            'tenant_id' => $tenantId, 'user_id' => $user->id, 'program_id' => $program->id, 'name' => $user->name,
            'nim' => $this->nim->next($program, $batchYear), 'batch_year' => $batchYear, 'status' => StudentStatus::Active,
            'entry_date' => today(), 'admission_path' => $data['admission_path'], 'ukt_group' => $data['ukt_group'] ?? null,
            'admission_ref' => $data['admission_ref'],
            'phone' => $profile['phone'] ?? null, 'nik' => $profile['nik'] ?? null, 'birth_place' => $profile['birth_place'] ?? null,
            'birth_date' => $profile['birth_date'] ?? null, 'gender' => $profile['gender'] ?? null, 'religion' => $profile['religion'] ?? null,
            'nationality' => $profile['nationality'] ?? null, 'address' => $profile['address'] ?? null, 'city' => $profile['city'] ?? null,
            'province' => $profile['province'] ?? null, 'postal_code' => $profile['postal_code'] ?? null, 'previous_school' => $profile['previous_school'] ?? null,
        ]);

        $group = $this->placement->place($student, $program, $term, $batchYear);
        $this->linkGuardians($student, $data['guardians'] ?? []);

        $invoice = $this->finance->issueInvoice($student, $term, null, today(), notify: false);

        if ($invoice === null) {
            $missing = $student->tenant?->feeModel() === 'flat' ? 'tarif BPP' : 'tarif UKT atau BPP';

            throw new AdmissionHandoffException('fee_rate_missing', "Prodi {$program->name} belum punya {$missing}, sehingga tagihan tidak dapat dibuat.");
        }

        $payment = $data['initial_payment'];

        if ((int) $payment['amount'] > $invoice->total) {
            throw new AdmissionHandoffException('payment_exceeds_invoice', "Pembayaran awal melebihi tagihan semester pertama (Rp {$invoice->total}).");
        }

        Payment::create([
            'tenant_id' => $tenantId, 'invoice_id' => $invoice->id, 'student_id' => $student->id, 'amount' => (int) $payment['amount'],
            'method' => PaymentMethod::from($payment['method']), 'reference' => $payment['reference'], 'paid_on' => $payment['paid_on'],
            'status' => PaymentStatus::Confirmed, 'note' => "UKT pertama dibayar di Admission ({$data['admission_ref']})", 'verified_at' => now(),
        ]);
        $this->finance->refresh($invoice);

        $this->audit->log('admission.handoff', $student, [], [
            'admission_ref' => $data['admission_ref'], 'nim' => $student->nim, 'program' => $program->name, 'term' => $term->id,
            'study_group' => $group->name, 'invoice' => $invoice->number, 'paid' => (int) $payment['amount'], 'payment_reference' => $payment['reference'],
        ], null, $tenantId);

        return $student;
    }

    /**
     * Orang tua/wali tanpa akun login. Wali yang sama (kampus, hubungan, nama, dan telepon) dipakai bersama saudara
     * kandung; tanpa telepon tidak ada pencocokan dan dibuat baru. Data wali yang sudah ada tidak ditimpa.
     *
     * @param  array<int, array<string, mixed>>  $guardians
     */
    private function linkGuardians(Student $student, array $guardians): void
    {
        foreach ($guardians as $row) {
            $attributes = [
                'name' => trim($row['name']), 'relation' => self::GUARDIAN_RELATIONS[$row['relation']],
                'phone' => $this->nullable($row['phone'] ?? null), 'occupation' => $this->nullable($row['occupation'] ?? null),
                'education' => $this->nullable($row['education'] ?? null), 'address' => $this->nullable($row['address'] ?? null),
            ];

            $guardian = $attributes['phone'] === null ? null : Guardian::query()->withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)->where('relation', $attributes['relation'])->where('phone', $attributes['phone'])
                ->whereRaw('lower(name) = ?', [Str::lower($attributes['name'])])->first();

            if ($guardian === null) {
                $guardian = Guardian::create(['tenant_id' => $student->tenant_id] + $attributes);
            } else {
                $guardian->fill(array_filter($attributes, fn (?string $value, string $key): bool => $value !== null && $guardian->getAttribute($key) === null, ARRAY_FILTER_USE_BOTH))->save();
            }

            $student->guardians()->syncWithoutDetaching([$guardian->id]);
        }
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function findByRef(int $tenantId, string $ref): ?Student
    {
        return Student::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('admission_ref', $ref)->first();
    }

    /**
     * @return array{user_id: int, student_id: int, nim: string, study_group: array{id: int, name: string}|null, photo_status: string, invoice: array{number: string, status: string, total: int, paid: int}|null}
     */
    private function response(Student $student, string $photoStatus): array
    {
        $invoice = Invoice::query()->withoutGlobalScopes()->with('payments')->where('student_id', $student->id)->orderBy('id')->first();
        $memberships = StudyGroupMember::query()->where('student_id', $student->id)->orderByDesc('id');
        $groupId = (clone $memberships)->whereNull('left_at')->value('study_group_id') ?? $memberships->value('study_group_id');
        $group = $groupId === null ? null : StudyGroup::query()->withoutGlobalScopes()->find($groupId);

        return [
            'user_id' => $student->user_id,
            'student_id' => $student->id,
            'nim' => $student->nim,
            'study_group' => $group === null ? null : ['id' => $group->id, 'name' => $group->name],
            'photo_status' => $photoStatus,
            'invoice' => $invoice === null ? null : [
                'number' => $invoice->number, 'status' => $invoice->status->value, 'total' => $invoice->total, 'paid' => $invoice->paidAmount(),
            ],
        ];
    }
}
