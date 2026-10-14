<?php

namespace App\Livewire\Finance;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detail tagihan')]
class Show extends Component
{
    #[Locked]
    public int $invoiceId;

    public string $amount = '';

    public string $method = 'cash';

    public string $paid_on = '';

    public string $reference = '';

    public string $note = '';

    public string $cancelReason = '';

    public ?int $rejectingId = null;

    public string $rejectNote = '';

    public function mount(Invoice $invoice): void
    {
        $this->invoiceId = $invoice->id;
        $this->paid_on = today()->toDateString();
        $this->amount = (string) $invoice->outstanding();
    }

    private function invoice(): Invoice
    {
        return Invoice::query()->with(['student.program', 'academicTerm', 'items', 'payments.verifier'])->findOrFail($this->invoiceId);
    }

    public function record(FinanceService $finance): void
    {
        $this->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['amount' => 'jumlah', 'method' => 'metode', 'paid_on' => 'tanggal bayar', 'reference' => 'referensi', 'note' => 'catatan']);

        $invoice = $this->invoice();
        $finance->recordPayment($invoice, auth()->user(), (int) $this->amount, PaymentMethod::from($this->method), now()->parse($this->paid_on), $this->reference, $this->note);

        $this->reset(['reference', 'note']);
        $this->amount = (string) $invoice->refresh()->outstanding();
        $this->dispatch('toast', message: 'Pembayaran dicatat.', type: 'success');
    }

    public function approve(int $paymentId, FinanceService $finance): void
    {
        $finance->decidePayment(Payment::query()->where('invoice_id', $this->invoiceId)->findOrFail($paymentId), auth()->user(), true);

        $this->dispatch('toast', message: 'Pembayaran dikonfirmasi.', type: 'success');
    }

    public function startReject(int $paymentId): void
    {
        $this->rejectingId = $paymentId;
        $this->rejectNote = '';
    }

    public function reject(FinanceService $finance): void
    {
        $this->validate(['rejectNote' => ['required', 'string', 'min:5', 'max:300']], [], ['rejectNote' => 'alasan penolakan']);

        $finance->decidePayment(Payment::query()->where('invoice_id', $this->invoiceId)->findOrFail($this->rejectingId), auth()->user(), false, $this->rejectNote);

        $this->reset(['rejectingId', 'rejectNote']);
        $this->dispatch('toast', message: 'Pembayaran ditolak.', type: 'success');
    }

    public function toggleExempt(FinanceService $finance): void
    {
        $invoice = $this->invoice();
        $finance->setExempt($invoice, auth()->user(), ! $invoice->krs_exempt);

        $this->dispatch('toast', message: $invoice->refresh()->krs_exempt ? 'Dispensasi KRS diberikan.' : 'Dispensasi KRS dicabut.', type: 'success');
    }

    public function cancelInvoice(FinanceService $finance): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:300']], [], ['cancelReason' => 'alasan pembatalan']);

        $finance->cancel($this->invoice(), auth()->user(), $this->cancelReason);

        $this->reset('cancelReason');
        $this->dispatch('toast', message: 'Tagihan dibatalkan.', type: 'success');
    }

    public function render(FinanceService $finance)
    {
        return view('livewire.finance.show', [
            'invoice' => $this->invoice(),
            'canManage' => $finance->canManage(auth()->user()),
            'methods' => PaymentMethod::cases(),
        ]);
    }
}
