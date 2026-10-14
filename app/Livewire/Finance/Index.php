<?php

namespace App\Livewire\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Services\FinanceService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Keuangan')]
class Index extends Component
{
    #[Url(as: 'term')]
    public string $termId = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'search')]
    public string $search = '';

    public string $dueOn = '';

    public string $programId = '';

    public ?int $rejectingId = null;

    public string $rejectNote = '';

    public function mount(): void
    {
        $this->dueOn = today()->addDays(30)->toDateString();
    }

    private function term(): ?AcademicTerm
    {
        return ($this->termId !== '' ? AcademicTerm::query()->find($this->termId) : null)
            ?? AcademicTerm::current()
            ?? AcademicTerm::query()->orderByDesc('starts_at')->first();
    }

    public function generate(FinanceService $finance): void
    {
        $term = $this->term();
        abort_if($term === null, 404);

        $this->validate([
            'dueOn' => ['required', 'date', 'after_or_equal:today'],
            'programId' => ['nullable', 'exists:programs,id'],
        ], [], ['dueOn' => 'jatuh tempo', 'programId' => 'program studi']);

        $result = $finance->generate($term, auth()->user(), now()->parse($this->dueOn), $this->programId !== '' ? (int) $this->programId : null);

        $message = "{$result['created']} tagihan diterbitkan";

        if ($result['skipped'] > 0 || $result['no_rate'] > 0) {
            $message .= " ({$result['skipped']} sudah ada, {$result['no_rate']} tanpa tarif)";
        }

        $this->dispatch('toast', message: $message.'.', type: $result['created'] > 0 ? 'success' : 'warning');
    }

    public function approve(int $paymentId, FinanceService $finance): void
    {
        $finance->decidePayment(Payment::query()->findOrFail($paymentId), auth()->user(), true);

        $this->dispatch('toast', message: 'Pembayaran dikonfirmasi.', type: 'success');
    }

    public function startReject(int $paymentId): void
    {
        $this->rejectingId = $paymentId;
        $this->rejectNote = '';
        $this->resetErrorBag();
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectingId', 'rejectNote']);
    }

    public function reject(FinanceService $finance): void
    {
        $this->validate(['rejectNote' => ['required', 'string', 'min:5', 'max:300']], [], ['rejectNote' => 'alasan penolakan']);

        $finance->decidePayment(Payment::query()->findOrFail($this->rejectingId), auth()->user(), false, $this->rejectNote);

        $this->cancelReject();
        $this->dispatch('toast', message: 'Pembayaran ditolak.', type: 'success');
    }

    public function render(FinanceService $finance)
    {
        $term = $this->term();

        if ($term === null) {
            return view('livewire.finance.index', ['term' => null, 'canManage' => $finance->canManage(auth()->user())]);
        }

        $keyword = trim($this->search);
        $base = Invoice::query()->where('academic_term_id', $term->id)->where('status', '!=', InvoiceStatus::Cancelled);

        $all = (clone $base)->with('payments')->get();

        $invoices = Invoice::query()->with(['student', 'payments'])
            ->where('academic_term_id', $term->id)
            ->when(InvoiceStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($keyword !== '', fn ($query) => $query->whereHas('student', fn ($inner) => $inner->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%")))
            ->latest('id')->limit(100)->get();

        return view('livewire.finance.index', [
            'term' => $term,
            'terms' => AcademicTerm::query()->orderByDesc('starts_at')->get(),
            'programs' => Program::query()->orderBy('name')->get(),
            'invoices' => $invoices,
            'stats' => [
                'billed' => (int) $all->sum('total'),
                'received' => (int) $all->sum(fn (Invoice $invoice): int => $invoice->paidAmount()),
                'outstanding' => (int) $all->sum(fn (Invoice $invoice): int => $invoice->outstanding()),
                'count' => $all->count(),
                'paid' => $all->where('status', InvoiceStatus::Paid)->count(),
                'overdue' => $all->filter(fn (Invoice $invoice): bool => $invoice->isOverdue())->count(),
            ],
            'pending' => Payment::query()->with(['invoice', 'student'])->where('status', PaymentStatus::Pending)->oldest()->limit(30)->get(),
            'statuses' => InvoiceStatus::cases(),
            'canManage' => $finance->canManage(auth()->user()),
        ]);
    }
}
