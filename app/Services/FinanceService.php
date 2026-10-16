<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\FeeRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AcademicNotice;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Biaya Pendidikan: tagihan UKT dan BPP per semester, pembayaran (verifikasi manual atau webhook virtual account),
 * dan status lunas sebagai syarat pengisian KRS.
 */
class FinanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function rupiah(int|float $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public function canManage(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Finance);
    }

    public function rateFor(Student $student, string $kind): ?int
    {
        $rates = FeeRate::query()->where('program_id', $student->program_id)->where('kind', $kind)->get();

        $match = $kind === 'ukt'
            ? ($rates->firstWhere('ukt_group', $student->ukt_group) ?? $rates->firstWhere('ukt_group', null))
            : $rates->first();

        return $match?->amount;
    }

    /**
     * Terbitkan tagihan semester untuk mahasiswa aktif yang belum punya tagihan semester itu.
     *
     * @return array{created: int, skipped: int, no_rate: int}
     */
    public function generate(AcademicTerm $term, User $actor, CarbonInterface $dueOn, ?int $programId = null): array
    {
        abort_unless($this->canManage($actor), 403);

        $students = Student::query()
            ->where('status', StudentStatus::Active)
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->get();

        $existing = Invoice::query()->where('academic_term_id', $term->id)->where('status', '!=', InvoiceStatus::Cancelled)->pluck('student_id')->flip();
        $counts = ['created' => 0, 'skipped' => 0, 'no_rate' => 0];

        DB::transaction(function () use ($students, $existing, $term, $actor, $dueOn, &$counts): void {
            foreach ($students as $student) {
                if ($existing->has($student->id)) {
                    $counts['skipped']++;

                    continue;
                }

                if ($this->issueInvoice($student, $term, $actor, $dueOn) === null) {
                    $counts['no_rate']++;

                    continue;
                }

                $counts['created']++;
            }
        });

        if ($counts['created'] > 0) {
            $this->audit->log('invoice.generated', $term, [], $counts + ['term' => $term->id]);
        }

        return $counts;
    }

    /**
     * Terbitkan tagihan satu mahasiswa untuk satu semester: UKT (golongan atau tarif umum) dan BPP dari `fee_rates`,
     * nomor tagihan, dan virtual account tetap per mahasiswa. Dipakai `generate()` (massal) dan serah terima dari
     * Admission (satu mahasiswa). Null bila prodi belum punya tarif sehingga tidak ada yang ditagihkan.
     * Pemanggil yang memastikan mahasiswa belum punya tagihan semester itu.
     */
    public function issueInvoice(Student $student, AcademicTerm $term, ?User $actor, CarbonInterface $dueOn, bool $notify = true): ?Invoice
    {
        $items = collect([['UKT', $this->rateFor($student, 'ukt')], ['BPP', $this->rateFor($student, 'bpp')]])
            ->filter(fn (array $row): bool => $row[1] !== null && $row[1] > 0);

        if ($items->isEmpty()) {
            return null;
        }

        $sequence = (int) Invoice::query()->withoutGlobalScopes()->max('id') + 1;
        $prefix = $student->tenant?->setting('va_prefix') ?? '8808';

        $invoice = Invoice::create([
            'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'academic_term_id' => $term->id,
            'number' => $this->number($term, $sequence), 'va_number' => $this->virtualAccount($prefix, $student),
            'title' => "Tagihan semester {$term->name} {$term->semester}", 'total' => (int) $items->sum(1),
            'status' => InvoiceStatus::Unpaid, 'due_on' => $dueOn, 'issued_at' => now(), 'created_by' => $actor?->id,
        ]);
        $invoice->items()->createMany($items->map(fn (array $row): array => ['description' => $row[0], 'amount' => $row[1]])->all());

        if ($notify) {
            $student->user?->notify(new AcademicNotice(
                'Tagihan baru terbit',
                "{$invoice->title} sebesar Rp ".number_format($invoice->total, 0, ',', '.').', jatuh tempo '.$dueOn->translatedFormat('j M Y').'.',
                'pay.show',
                ['invoice' => $invoice->id],
                icon: 'banknotes',
                tone: 'warning',
            ));
        }

        return $invoice;
    }

    /**
     * Catat pembayaran yang langsung terkonfirmasi (kasir atau transfer yang sudah dicek petugas).
     */
    public function recordPayment(Invoice $invoice, User $actor, int $amount, PaymentMethod $method, CarbonInterface $paidOn, ?string $reference, ?string $note): Payment
    {
        abort_unless($this->canManage($actor), 403);
        $this->guardPayable($invoice, $amount, includePending: false);

        $payment = DB::transaction(function () use ($invoice, $actor, $amount, $method, $paidOn, $reference, $note): Payment {
            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id,
                'amount' => $amount, 'method' => $method, 'reference' => $reference ?: null, 'paid_on' => $paidOn,
                'status' => PaymentStatus::Confirmed, 'note' => $note ?: null, 'recorded_by' => $actor->id,
                'verified_by' => $actor->id, 'verified_at' => now(),
            ]);
            $this->refresh($invoice);

            return $payment;
        });

        $this->audit->log('payment.recorded', $payment, [], ['invoice' => $invoice->number, 'amount' => $amount]);

        return $payment;
    }

    /**
     * Mahasiswa mengonfirmasi pembayarannya sendiri; menunggu verifikasi petugas keuangan.
     */
    public function submitPayment(Invoice $invoice, User $actor, int $amount, PaymentMethod $method, CarbonInterface $paidOn, ?string $reference, ?string $note, ?UploadedFile $proof): Payment
    {
        if (! in_array($method, PaymentMethod::forStudents(), true)) {
            throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak tersedia.']);
        }

        $this->guardPayable($invoice, $amount, includePending: true);

        $payment = Payment::create([
            'tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id,
            'amount' => $amount, 'method' => $method, 'reference' => $reference ?: null, 'paid_on' => $paidOn,
            'status' => PaymentStatus::Pending, 'note' => $note ?: null, 'recorded_by' => $actor->id,
            'proof_path' => $proof?->store('payment-proofs'),
        ]);

        $this->audit->log('payment.submitted', $payment, [], ['invoice' => $invoice->number, 'amount' => $amount]);

        return $payment;
    }

    public function decidePayment(Payment $payment, User $actor, bool $approve, ?string $note = null): void
    {
        abort_unless($this->canManage($actor), 403);

        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diputuskan.']);
        }

        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['payment' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($payment, $actor, $approve, $note): void {
            $invoice = $payment->invoice;

            if ($approve && $payment->amount > $invoice->outstanding()) {
                throw ValidationException::withMessages(['payment' => 'Jumlah melebihi sisa tagihan. Tolak atau minta mahasiswa mengajukan ulang.']);
            }

            $payment->update([
                'status' => $approve ? PaymentStatus::Confirmed : PaymentStatus::Rejected,
                'verified_by' => $actor->id, 'verified_at' => now(), 'note' => filled($note) ? trim((string) $note) : $payment->note,
            ]);
            $this->refresh($invoice->refresh());
        });

        $this->audit->log($approve ? 'payment.confirmed' : 'payment.rejected', $payment, [], ['note' => $note]);
        $payment->student?->user?->notify(new AcademicNotice(
            $approve ? 'Pembayaran dikonfirmasi' : 'Pembayaran ditolak',
            $approve ? "Pembayaran {$payment->invoice->number} telah diterima." : "Pembayaran {$payment->invoice->number} ditolak: ".trim((string) $note),
            'pay.show',
            ['invoice' => $payment->invoice_id],
            icon: $approve ? 'check-circle' : 'x-circle',
            tone: $approve ? 'success' : 'danger',
        ));
    }

    public function cancel(Invoice $invoice, User $actor, string $reason): void
    {
        abort_unless($this->canManage($actor), 403);

        if ($invoice->payments()->where('status', PaymentStatus::Confirmed)->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Tagihan yang sudah ada pembayarannya tidak bisa dibatalkan.']);
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled, 'notes' => trim($reason)]);
        $invoice->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Rejected, 'note' => 'Tagihan dibatalkan']);
        $this->audit->log('invoice.cancelled', $invoice, [], ['reason' => $reason]);
    }

    public function setExempt(Invoice $invoice, User $actor, bool $exempt): void
    {
        abort_unless($this->canManage($actor), 403);

        $invoice->update(['krs_exempt' => $exempt]);
        $this->audit->log($exempt ? 'invoice.krs_exempted' : 'invoice.krs_exemption_removed', $invoice);
    }

    /**
     * Hitung ulang status dan tanggal lunas dari pembayaran terkonfirmasi.
     */
    public function refresh(Invoice $invoice): Invoice
    {
        $invoice->load('payments');

        if ($invoice->status === InvoiceStatus::Cancelled) {
            return $invoice;
        }

        $paid = $invoice->paidAmount();
        $status = match (true) {
            $paid >= $invoice->total => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };

        $invoice->update(['status' => $status, 'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null]);

        return $invoice;
    }

    /**
     * Syarat KRS: bila kampus mewajibkan lunas, semua tagihan semester itu harus lunas atau diberi dispensasi.
     * Semester tanpa tagihan terbit tidak menghalangi KRS.
     *
     * @return array{allowed: bool, outstanding: int, invoices: Collection<int, Invoice>}
     */
    public function krsClearance(Student $student, AcademicTerm $term): array
    {
        $invoices = Invoice::query()->with('payments')
            ->where('student_id', $student->id)->where('academic_term_id', $term->id)->where('status', '!=', InvoiceStatus::Cancelled)->get();

        $required = (bool) $student->tenant?->setting('krs_requires_payment');
        $blocking = $invoices->filter(fn (Invoice $invoice): bool => $invoice->status !== InvoiceStatus::Paid && ! $invoice->krs_exempt);

        return [
            'allowed' => ! $required || $blocking->isEmpty(),
            'outstanding' => (int) $blocking->sum(fn (Invoice $invoice): int => $invoice->outstanding()),
            'invoices' => $invoices,
        ];
    }

    /**
     * Terima notifikasi pembayaran virtual account dari penyedia pembayaran. Idempoten menurut referensi.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, message: string}
     */
    public function handleWebhook(string $rawBody, ?string $signature, array $payload): array
    {
        $secret = (string) config('services.pay.webhook_secret');

        if ($secret === '' || $signature === null || ! hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            return ['status' => 'unauthorized', 'message' => 'Tanda tangan tidak valid.'];
        }

        $reference = (string) ($payload['reference'] ?? '');
        $amount = (int) ($payload['amount'] ?? 0);
        $vaNumber = (string) ($payload['va_number'] ?? '');

        // Nomor VA tetap per mahasiswa, jadi pembayaran dialokasikan ke tagihan terbuka yang paling lama jatuh temponya.
        $invoices = Invoice::query()->withoutGlobalScopes()->with('payments')->where('va_number', $vaNumber)->get();

        if ($vaNumber === '' || $invoices->isEmpty() || $reference === '' || $amount <= 0) {
            return ['status' => 'invalid', 'message' => 'Virtual account atau data pembayaran tidak valid.'];
        }

        if ($invoices->flatMap->payments->contains('reference', $reference)) {
            return ['status' => 'duplicate', 'message' => 'Pembayaran sudah dicatat.'];
        }

        $invoice = $invoices
            ->filter(fn (Invoice $candidate): bool => in_array($candidate->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true))
            ->sortBy([['due_on', 'asc'], ['id', 'asc']])
            ->first();

        if ($invoice === null || $amount > $invoice->outstanding()) {
            return ['status' => 'invalid', 'message' => 'Tidak ada tagihan terbuka yang cocok atau jumlah melebihi sisa tagihan.'];
        }

        DB::transaction(function () use ($invoice, $reference, $amount, $payload): void {
            Payment::create([
                'tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id, 'amount' => $amount,
                'method' => PaymentMethod::VirtualAccount, 'reference' => $reference,
                'paid_on' => isset($payload['paid_at']) ? now()->parse((string) $payload['paid_at'])->toDateString() : today()->toDateString(),
                'status' => PaymentStatus::Confirmed, 'note' => 'Notifikasi otomatis virtual account', 'verified_at' => now(),
            ]);
            $this->refresh($invoice);
        });

        return ['status' => 'ok', 'message' => 'Pembayaran dicatat.'];
    }

    private function guardPayable(Invoice $invoice, int $amount, bool $includePending): void
    {
        $invoice->loadMissing('payments');

        if ($invoice->status === InvoiceStatus::Cancelled || $invoice->status === InvoiceStatus::Paid) {
            throw ValidationException::withMessages(['amount' => 'Tagihan ini tidak menerima pembayaran lagi.']);
        }

        $room = $invoice->outstanding() - ($includePending ? $invoice->pendingAmount() : 0);

        if ($amount <= 0 || $amount > $room) {
            throw ValidationException::withMessages(['amount' => $room <= 0
                ? 'Seluruh sisa tagihan sudah dalam proses verifikasi.'
                : 'Jumlah harus antara Rp 1 dan sisa tagihan yang bisa dibayar ('.self::rupiah($room).').']);
        }
    }

    private function number(AcademicTerm $term, int $sequence): string
    {
        return sprintf('INV-%s%s-%06d', substr($term->name, 2, 2), $term->semester === 'Ganjil' ? '1' : '2', $sequence);
    }

    private function virtualAccount(string $prefix, Student $student): string
    {
        return substr($prefix.preg_replace('/\D+/', '', (string) $student->nim), 0, 16);
    }
}
