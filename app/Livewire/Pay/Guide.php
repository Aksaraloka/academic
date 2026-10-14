<?php

namespace App\Livewire\Pay;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Invoice;
use App\Support\PaymentGuide;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Panduan pembayaran')]
class Guide extends Component
{
    #[Url(as: 'method')]
    public string $method = 'va';

    #[Url(as: 'bank')]
    public string $bank = 'bca';

    public function mount(): void
    {
        $this->normalize();
    }

    public function updated(): void
    {
        $this->normalize();
    }

    private function normalize(): void
    {
        if (! in_array($this->method, ['va', 'transfer'], true)) {
            $this->method = 'va';
        }

        if (! array_key_exists($this->bank, PaymentGuide::banks())) {
            $this->bank = 'bca';
        }
    }

    public function render()
    {
        $user = auth()->user();
        $tenant = $user->tenant;

        $student = $user->hasRole(Role::Student) ? $user->student : ($user->hasRole(Role::Guardian) ? $user->guardian?->students()->first() : null);
        $invoice = $student
            ? Invoice::query()->where('student_id', $student->id)->where('status', '!=', InvoiceStatus::Cancelled)->orderByDesc('id')->first()
            : null;

        $vaNumber = $invoice?->va_number ?? ($tenant->setting('va_prefix') ?: '8808').'2410001';
        $account = [
            'bank' => $tenant->setting('payment_bank_name'),
            'number' => $tenant->setting('payment_account_number'),
            'holder' => $tenant->setting('payment_account_name'),
        ];

        return view('livewire.pay.guide', [
            'banks' => PaymentGuide::banks(),
            'info' => PaymentGuide::banks()[$this->bank],
            'steps' => $this->method === 'va'
                ? PaymentGuide::virtualAccountSteps($this->bank, $vaNumber)
                : PaymentGuide::transferSteps($this->bank, $account['bank'], $account['number'], $account['holder']),
            'vaNumber' => $vaNumber,
            'isExample' => $invoice?->va_number === null,
            'account' => $account,
            'invoice' => $invoice,
            'after' => PaymentGuide::afterPaying(),
        ]);
    }
}
