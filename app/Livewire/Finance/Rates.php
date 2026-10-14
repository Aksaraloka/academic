<?php

namespace App\Livewire\Finance;

use App\Models\FeeRate;
use App\Models\Program;
use App\Services\AuditLogger;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tarif keuangan')]
class Rates extends Component
{
    public string $programId = '';

    public string $kind = 'ukt';

    public string $ukt_group = '';

    public string $amount = '';

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate([
            'programId' => ['required', 'exists:programs,id'],
            'kind' => ['required', 'in:ukt,bpp'],
            'ukt_group' => ['nullable', 'integer', 'between:1,8'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ], [], ['programId' => 'program studi', 'kind' => 'jenis', 'ukt_group' => 'golongan', 'amount' => 'tarif']);

        $group = $data['kind'] === 'ukt' && $data['ukt_group'] !== '' ? (int) $data['ukt_group'] : null;

        $rate = FeeRate::query()->updateOrCreate(
            ['program_id' => $data['programId'], 'kind' => $data['kind'], 'ukt_group' => $group],
            ['tenant_id' => auth()->user()->tenant_id, 'amount' => (int) $data['amount']],
        );
        $audit->log('fee_rate.saved', $rate, [], $rate->only(['program_id', 'kind', 'ukt_group', 'amount']));

        $this->reset(['ukt_group', 'amount']);
        $this->dispatch('toast', message: 'Tarif disimpan.', type: 'success');
    }

    public function remove(int $rateId): void
    {
        FeeRate::query()->findOrFail($rateId)->delete();
    }

    public function render()
    {
        return view('livewire.finance.rates', [
            'programs' => Program::query()->with('feeRates')->orderBy('name')->get(),
        ]);
    }
}
