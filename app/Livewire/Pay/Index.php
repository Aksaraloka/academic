<?php

namespace App\Livewire\Pay;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\FinanceService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Aksaraloka Pay')]
class Index extends Component
{
    /** Orang tua memilih anak lewat ?child=ID. */
    #[Url(as: 'child')]
    public ?int $childId = null;

    private function student(): ?Student
    {
        $user = auth()->user();

        if ($user->hasRole(Role::Student)) {
            return $user->student;
        }

        $children = $user->guardian?->students()->get() ?? collect();

        return $children->firstWhere('id', $this->childId) ?? $children->first();
    }

    public function render(FinanceService $finance)
    {
        $user = auth()->user();
        $student = $this->student();
        $term = AcademicTerm::current();

        $invoices = $student
            ? Invoice::query()->with(['academicTerm', 'payments'])->where('student_id', $student->id)->latest('id')->get()
            : collect();

        $active = $invoices->where('status', '!=', InvoiceStatus::Cancelled);

        return view('livewire.pay.index', [
            'student' => $student,
            'children' => $user->hasRole(Role::Guardian) ? ($user->guardian?->students()->get() ?? collect()) : collect(),
            'invoices' => $invoices,
            'outstanding' => (int) $active->sum(fn (Invoice $invoice): int => $invoice->outstanding()),
            'clearance' => $student && $term ? $finance->krsClearance($student, $term) : null,
            'term' => $term,
            'requiresPayment' => (bool) $user->tenant->setting('krs_requires_payment'),
        ]);
    }
}
