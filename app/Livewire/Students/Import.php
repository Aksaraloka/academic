<?php

namespace App\Livewire\Students;

use App\Services\StudentImporter;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Impor mahasiswa')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    /** @var array{valid: array<int, array<string, mixed>>, errors: array<int, array{row: int, message: string}>}|null */
    public ?array $preview = null;

    public ?int $imported = null;

    public function updatedFile(StudentImporter $importer): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [], ['file' => 'berkas']);

        $this->imported = null;
        $this->preview = $importer->preview($this->file->getRealPath());
    }

    public function confirm(StudentImporter $importer): void
    {
        abort_if($this->preview === null || $this->preview['valid'] === [], 422);

        $this->imported = $importer->commit($this->preview['valid']);
        $this->preview = null;
        $this->file = null;

        $this->dispatch('toast', message: "{$this->imported} mahasiswa berhasil diimpor.", type: 'success');
    }

    public function render()
    {
        return view('livewire.students.import');
    }
}
