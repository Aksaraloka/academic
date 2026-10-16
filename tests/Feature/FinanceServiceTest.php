<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\FeeRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = makeTenant();
    $this->term = makeTerm($this->tenant);
    $this->program = makeProgram($this->tenant);
    $this->student = makeStudentUser($this->tenant)->student;
    $this->finance = makeStaffUser($this->tenant, Role::Finance);
    $this->service = app(FinanceService::class);
    $this->invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'academic_term_id' => $this->term->id,
        'total' => 1000000, 'va_number' => '8808999001',
    ]);
});

function addPayment(object $test, int $amount, PaymentStatus $status, ?Invoice $invoice = null): Payment
{
    $invoice ??= $test->invoice;

    return Payment::create([
        'tenant_id' => $test->tenant->id, 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id, 'amount' => $amount,
        'method' => PaymentMethod::BankTransfer, 'paid_on' => today(), 'status' => $status,
    ]);
}

describe('invoice status', function () {
    it('counts only confirmed payments', function () {
        addPayment($this, 400000, PaymentStatus::Confirmed);
        addPayment($this, 300000, PaymentStatus::Pending);
        addPayment($this, 200000, PaymentStatus::Rejected);

        $invoice = $this->service->refresh($this->invoice->refresh());

        expect($invoice->status)->toBe(InvoiceStatus::Partial)
            ->and($invoice->paidAmount())->toBe(400000)
            ->and($invoice->pendingAmount())->toBe(300000)
            ->and($invoice->outstanding())->toBe(600000)
            ->and($invoice->paid_at)->toBeNull();
    });

    it('turns paid once confirmed payments cover the total and stamps the paid date', function () {
        addPayment($this, 600000, PaymentStatus::Confirmed);
        addPayment($this, 400000, PaymentStatus::Confirmed);

        $invoice = $this->service->refresh($this->invoice->refresh());

        expect($invoice->status)->toBe(InvoiceStatus::Paid)->and($invoice->paid_at)->not->toBeNull();
    });

    it('stays unpaid when there are only pending or rejected payments', function () {
        addPayment($this, 1000000, PaymentStatus::Pending);
        addPayment($this, 1000000, PaymentStatus::Rejected);

        expect($this->service->refresh($this->invoice->refresh())->status)->toBe(InvoiceStatus::Unpaid);
    });

    it('never revives a cancelled invoice', function () {
        $this->invoice->update(['status' => InvoiceStatus::Cancelled]);
        addPayment($this, 1000000, PaymentStatus::Confirmed);

        expect($this->service->refresh($this->invoice->refresh())->status)->toBe(InvoiceStatus::Cancelled);
    });

    it('recalculates after a staff member records a payment', function () {
        $this->actingAs($this->finance);

        $this->service->recordPayment($this->invoice, $this->finance, 1000000, PaymentMethod::Cash, today(), null, null);

        expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('moves a student payment from pending to confirmed only after verification', function () {
        $student = $this->student->user;
        $this->actingAs($student);
        $payment = $this->service->submitPayment($this->invoice, $student, 500000, PaymentMethod::BankTransfer, today(), 'REF1', null, UploadedFile::fake()->image('bukti.jpg'));

        expect($payment->status)->toBe(PaymentStatus::Pending)->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid);

        $this->service->decidePayment($payment, $this->finance, true);

        expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed)->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Partial);
    });

    it('requires a reason to reject and does not change the invoice', function () {
        $payment = addPayment($this, 500000, PaymentStatus::Pending);

        expect(fn () => $this->service->decidePayment($payment, $this->finance, false))->toThrow(ValidationException::class, 'Alasan penolakan');

        $this->service->decidePayment($payment, $this->finance, false, 'Bukti buram');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid);
    });

    it('guards amounts and double decisions', function () {
        $payment = addPayment($this, 500000, PaymentStatus::Pending);

        expect(fn () => $this->service->submitPayment($this->invoice, $this->student->user, 600000, PaymentMethod::BankTransfer, today(), null, null, null))
            ->toThrow(ValidationException::class, 'Jumlah harus antara')
            ->and(fn () => $this->service->submitPayment($this->invoice, $this->student->user, 100, PaymentMethod::Cash, today(), null, null, null))
            ->toThrow(ValidationException::class, 'Metode pembayaran')
            ->and(fn () => $this->service->recordPayment($this->invoice, $this->finance, 0, PaymentMethod::Cash, today(), null, null))
            ->toThrow(ValidationException::class);

        $this->service->decidePayment($payment, $this->finance, true);

        expect(fn () => $this->service->decidePayment($payment, $this->finance, true))->toThrow(ValidationException::class, 'sudah diputuskan');
    });

    it('only lets admin and finance staff write payments', function () {
        $student = $this->student->user;

        expect(fn () => $this->service->recordPayment($this->invoice, $student, 1000, PaymentMethod::Cash, today(), null, null))->toThrow(HttpException::class)
            ->and(fn () => $this->service->decidePayment(addPayment($this, 1000, PaymentStatus::Pending), makeStaffUser($this->tenant, Role::Principal), true))->toThrow(HttpException::class)
            ->and(fn () => $this->service->cancel($this->invoice, $student, 'x'))->toThrow(HttpException::class)
            ->and(fn () => $this->service->setExempt($this->invoice, $student, true))->toThrow(HttpException::class);
    });

    it('cancels an unpaid invoice and rejects pending payments but refuses when paid', function () {
        $this->actingAs($this->finance);
        $pending = addPayment($this, 100000, PaymentStatus::Pending);

        $this->service->cancel($this->invoice, $this->finance, 'Salah tagih');

        expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Cancelled)->and($pending->refresh()->status)->toBe(PaymentStatus::Rejected);

        $paid = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'academic_term_id' => makeTerm($this->tenant)->id]);
        addPayment($this, 5000, PaymentStatus::Confirmed, $paid);

        expect(fn () => $this->service->cancel($paid, $this->finance, 'x'))->toThrow(ValidationException::class);
    });
});

describe('KRS clearance', function () {
    it('is allowed with no invoice, a paid invoice, an exempted invoice, or when the rule is off', function (string $case) {
        match ($case) {
            'none' => $this->invoice->delete(),
            'paid' => $this->invoice->update(['status' => InvoiceStatus::Paid]),
            'exempt' => $this->invoice->update(['krs_exempt' => true]),
            'cancelled' => $this->invoice->update(['status' => InvoiceStatus::Cancelled]),
            'off' => $this->tenant->update(['settings' => ['krs_requires_payment' => false]]),
        };

        expect($this->service->krsClearance($this->student->refresh(), $this->term)['allowed'])->toBeTrue();
    })->with(['none', 'paid', 'exempt', 'cancelled', 'off']);

    it('is denied with the outstanding amount while the invoice is open', function () {
        addPayment($this, 250000, PaymentStatus::Confirmed);
        addPayment($this, 250000, PaymentStatus::Pending);

        $result = $this->service->krsClearance($this->student->refresh(), $this->term);

        expect($result['allowed'])->toBeFalse()->and($result['outstanding'])->toBe(750000);
    });

    it('looks only at the requested term', function () {
        $other = makeTerm($this->tenant, ['name' => '2027/2028']);

        expect($this->service->krsClearance($this->student->refresh(), $other)['allowed'])->toBeTrue();
    });
});

describe('invoice generation', function () {
    beforeEach(function () {
        $this->invoice->delete();
        $this->actingAs($this->finance);
    });

    it('issues one invoice per active student with the program rates and skips those that already have one', function () {
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => null, 'amount' => 3000000]);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'bpp', 'ukt_group' => null, 'amount' => 500000]);

        $first = $this->service->generate($this->term, $this->finance, today()->addDays(14));
        $second = $this->service->generate($this->term, $this->finance, today()->addDays(14));

        $invoice = Invoice::firstOrFail();

        expect($first)->toBe(['created' => 1, 'skipped' => 0, 'no_rate' => 0])
            ->and($second)->toBe(['created' => 0, 'skipped' => 1, 'no_rate' => 0])
            ->and($invoice->total)->toBe(3500000)
            ->and($invoice->items()->count())->toBe(2)
            ->and($invoice->va_number)->toStartWith('8808');
    });

    it('uses the UKT group rate and falls back to the general rate', function () {
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => null, 'amount' => 4000000]);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => 2, 'amount' => 2000000]);
        $this->student->update(['ukt_group' => 2]);

        expect($this->service->rateFor($this->student->refresh(), 'ukt'))->toBe(2000000);

        $this->student->update(['ukt_group' => 5]);

        expect($this->service->rateFor($this->student->refresh(), 'ukt'))->toBe(4000000);
    });

    it('counts students without a rate and ignores inactive students', function () {
        makeStudentUser($this->tenant, [], ['status' => 'cuti']);

        expect($this->service->generate($this->term, $this->finance, today()->addDays(14)))->toBe(['created' => 0, 'skipped' => 0, 'no_rate' => 1]);
    });

    it('is forbidden for roles that cannot manage finance', function () {
        expect(fn () => $this->service->generate($this->term, makeStaffUser($this->tenant, Role::Teacher), today()))->toThrow(HttpException::class);
    });
});

describe('virtual account webhook', function () {
    beforeEach(fn () => config(['services.pay.webhook_secret' => 'pay-secret']));

    /**
     * @param  array<string, mixed>  $payload
     */
    function postPayWebhook(object $test, array $payload, ?string $secret = 'pay-secret', ?string $signature = null)
    {
        $body = json_encode($payload);

        return $test->call('POST', '/pay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    it('records a valid payment and marks the invoice paid', function () {
        postPayWebhook($this, ['reference' => 'TRX-1', 'amount' => 1000000, 'va_number' => '8808999001'])
            ->assertOk()->assertJson(['status' => 'ok']);

        $payment = Payment::firstOrFail();

        expect($payment->status)->toBe(PaymentStatus::Confirmed)
            ->and($payment->method)->toBe(PaymentMethod::VirtualAccount)
            ->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('rejects an invalid or missing signature without recording anything', function () {
        postPayWebhook($this, ['reference' => 'TRX-1', 'amount' => 1000, 'va_number' => '8808999001'], signature: 'salah')->assertUnauthorized();
        postPayWebhook($this, ['reference' => 'TRX-1', 'amount' => 1000, 'va_number' => '8808999001'], secret: 'rahasia-lain')->assertUnauthorized();

        expect(Payment::count())->toBe(0);
    });

    it('rejects every webhook when no secret is configured', function () {
        config(['services.pay.webhook_secret' => null]);

        postPayWebhook($this, ['reference' => 'TRX-1', 'amount' => 1000, 'va_number' => '8808999001'], secret: '')->assertUnauthorized();
    });

    it('treats a repeated reference as a duplicate and records it once', function () {
        $payload = ['reference' => 'TRX-DUP', 'amount' => 400000, 'va_number' => '8808999001'];

        postPayWebhook($this, $payload)->assertOk()->assertJson(['status' => 'ok']);
        postPayWebhook($this, $payload)->assertOk()->assertJson(['status' => 'duplicate']);

        expect(Payment::count())->toBe(1)->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Partial);
    });

    it('refuses an unknown VA, an amount above the outstanding balance, and incomplete data', function () {
        postPayWebhook($this, ['reference' => 'A', 'amount' => 1000, 'va_number' => '0000'])->assertStatus(422);
        postPayWebhook($this, ['reference' => 'B', 'amount' => 1000001, 'va_number' => '8808999001'])->assertStatus(422);
        postPayWebhook($this, ['reference' => '', 'amount' => 1000, 'va_number' => '8808999001'])->assertStatus(422);
        postPayWebhook($this, ['reference' => 'C', 'amount' => 0, 'va_number' => '8808999001'])->assertStatus(422);

        expect(Payment::count())->toBe(0);
    });

    it('allocates to the open invoice with the earliest due date and ignores paid ones', function () {
        $this->invoice->update(['due_on' => today()->addDays(30)]);
        $older = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'academic_term_id' => makeTerm($this->tenant, ['name' => '2025/2026'])->id,
            'va_number' => '8808999001', 'total' => 500000, 'due_on' => today()->addDays(5),
        ]);

        postPayWebhook($this, ['reference' => 'TRX-OLD', 'amount' => 500000, 'va_number' => '8808999001'])->assertOk();

        expect($older->refresh()->status)->toBe(InvoiceStatus::Paid)->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid);

        postPayWebhook($this, ['reference' => 'TRX-NEXT', 'amount' => 1000000, 'va_number' => '8808999001'])->assertOk();

        expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('does not need a CSRF token or a session', function () {
        postPayWebhook($this, ['reference' => 'TRX-NOSESSION', 'amount' => 1000, 'va_number' => '8808999001'])->assertOk();

        $this->assertGuest();
    });
});
