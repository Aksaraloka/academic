<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AcademicTerm;
use App\Models\FeeRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FinanceService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Aksaraloka Pay: tarif UKT dan BPP, golongan UKT mahasiswa, tagihan semester berjalan dan semester lalu
 * dengan pembayaran lunas, sebagian, tertunda, dan belum dibayar. Dipanggil dari DatabaseSeeder.
 */
class FinanceSeeder extends Seeder
{
    public function seed(Tenant $tenant, User $actor, FinanceService $finance): void
    {
        $tenant->update(['settings' => array_merge($tenant->settings ?? [], [
            'krs_requires_payment' => true, 'va_prefix' => '8808',
            'payment_bank_name' => 'Bank Mandiri', 'payment_account_number' => '1310012345678', 'payment_account_name' => 'Universitas Aksara Nusantara',
        ])]);

        $this->rates($tenant);

        Student::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('ukt_group')->get()->each(
            fn (Student $student) => $student->update(['ukt_group' => $this->group()]),
        );

        auth()->setUser($actor);

        try {
            $previous = AcademicTerm::query()->where('is_active', false)->orderByDesc('starts_at')->first();
            $current = AcademicTerm::current();

            if ($previous) {
                $finance->generate($previous, $actor, $previous->starts_at->copy()->addDays(21));
                Invoice::query()->where('academic_term_id', $previous->id)->with('payments')->get()->each(function (Invoice $invoice) use ($finance, $actor, $previous): void {
                    $finance->recordPayment($invoice, $actor, $invoice->total, PaymentMethod::BankTransfer, $previous->starts_at->copy()->addDays(random_int(1, 14)), 'TRF'.random_int(100000, 999999), null);
                });
            }

            if ($current) {
                $finance->generate($current, $actor, today()->addDays(21));
                $this->currentTerm($current, $finance, $actor);
            }
        } finally {
            auth()->logout();
        }
    }

    private function group(): int
    {
        return [1, 2, 2, 3, 3, 3, 4, 4, 5][array_rand([0, 1, 2, 3, 4, 5, 6, 7, 8])];
    }

    private function rates(Tenant $tenant): void
    {
        $base = ['TI' => [2500000, 1500000], 'SI' => [2300000, 1400000], 'MJ' => [2000000, 1200000]];

        foreach (Program::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->get() as $program) {
            [$ukt, $bpp] = $base[$program->code] ?? [2000000, 1000000];

            foreach (range(1, 5) as $group) {
                FeeRate::create(['tenant_id' => $tenant->id, 'program_id' => $program->id, 'kind' => 'ukt', 'ukt_group' => $group, 'amount' => $ukt + ($group - 1) * 1000000]);
            }

            FeeRate::create(['tenant_id' => $tenant->id, 'program_id' => $program->id, 'kind' => 'bpp', 'ukt_group' => null, 'amount' => $bpp]);
        }
    }

    /**
     * Sebaran status tagihan semester berjalan. Mahasiswa contoh (NIM pertama) lunas agar bisa mengisi KRS.
     */
    private function currentTerm(AcademicTerm $term, FinanceService $finance, User $actor): void
    {
        $demoId = Student::query()->orderBy('id')->value('id');
        $exempted = 0;

        foreach (Invoice::query()->where('academic_term_id', $term->id)->with('payments')->get() as $invoice) {
            $roll = $invoice->student_id === $demoId ? 1 : random_int(1, 100);
            $paidOn = today()->subDays(random_int(1, 25));

            match (true) {
                $roll <= 62 => $finance->recordPayment($invoice, $actor, $invoice->total, PaymentMethod::VirtualAccount, $paidOn, 'VA'.random_int(1000000, 9999999), null),
                $roll <= 74 => $finance->recordPayment($invoice, $actor, (int) (intdiv($invoice->total, 2000000) * 1000000) ?: intdiv($invoice->total, 2), PaymentMethod::BankTransfer, $paidOn, 'TRF'.random_int(100000, 999999), 'Cicilan pertama'),
                $roll <= 84 => $this->pending($invoice, $paidOn),
                default => null,
            };

            if ($roll > 92 && $roll <= 96) {
                $invoice->update(['due_on' => today()->subDays(random_int(3, 20))]);
            }

            if ($roll > 96 && $exempted < 3) {
                $finance->setExempt($invoice, $actor, true);
                $exempted++;
            }
        }
    }

    private function pending(Invoice $invoice, CarbonInterface $paidOn): void
    {
        Payment::create([
            'tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id, 'amount' => $invoice->total,
            'method' => PaymentMethod::BankTransfer, 'reference' => 'TRF'.random_int(100000, 999999), 'paid_on' => $paidOn,
            'status' => PaymentStatus::Pending, 'note' => 'Transfer dari rekening orang tua', 'recorded_by' => $invoice->student->user_id,
        ]);
    }
}
