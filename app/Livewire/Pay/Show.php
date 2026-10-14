<?php

namespace App\Livewire\Pay;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Invoice;
use App\Services\FinanceService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Detail tagihan')]
class Show extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $invoiceId;

    public string $amount = '';

    public string $method = 'transfer';

    public string $paid_on = '';

    public string $reference = '';

    public string $note = '';

    public $proof;

    public function mount(Invoice $invoice): void
    {
        abort_unless(Invoice::query()->visibleTo(auth()->user())->whereKey($invoice->id)->exists(), 404);

        $this->invoiceId = $invoice->id;
        $this->paid_on = today()->toDateString();
        $this->amount = (string) max(0, $invoice->loadMissing('payments')->outstanding() - $invoice->pendingAmount());
    }

    private function invoice(): Invoice
    {
        return Invoice::query()->visibleTo(auth()->user())->with(['academicTerm', 'items', 'payments', 'student'])->findOrFail($this->invoiceId);
    }

    public function submit(FinanceService $finance): void
    {
        abort_unless(auth()->user()->hasRole(Role::Student), 403);

        $this->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:'.implode(',', array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::forStudents()))],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:300'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ], [], ['amount' => 'jumlah', 'method' => 'metode', 'paid_on' => 'tanggal bayar', 'reference' => 'referensi', 'note' => 'catatan', 'proof' => 'bukti pembayaran']);

        $finance->submitPayment($this->invoice(), auth()->user(), (int) $this->amount, PaymentMethod::from($this->method), now()->parse($this->paid_on), $this->reference, $this->note, $this->proof);

        $this->reset(['reference', 'note', 'proof']);
        $invoice = $this->invoice();
        $this->amount = (string) max(0, $invoice->outstanding() - $invoice->pendingAmount());
        $this->dispatch('toast', message: 'Pembayaran dikirim dan menunggu verifikasi petugas keuangan.', type: 'success');
    }

    public function render()
    {
        $invoice = $this->invoice();
        $tenant = auth()->user()->tenant;

        return view('livewire.pay.show', [
            'invoice' => $invoice,
            'canPay' => auth()->user()->hasRole(Role::Student) && in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true) && $invoice->outstanding() > $invoice->pendingAmount(),
            'methods' => PaymentMethod::forStudents(),
            'bank' => [
                'name' => $tenant->setting('payment_bank_name'),
                'number' => $tenant->setting('payment_account_number'),
                'holder' => $tenant->setting('payment_account_name'),
            ],
        ]);
    }
}
