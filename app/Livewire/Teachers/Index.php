<?php

namespace App\Livewire\Teachers;

use App\Enums\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Guru')]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $email = '';

    public string $nip = '';

    public string $employment_status = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'nip' => ['nullable', 'string', 'max:30'],
            'employment_status' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nama', 'nip' => 'NIP', 'employment_status' => 'status kepegawaian'];
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => Role::Teacher,
                'password' => Hash::make('ganti-sandi-'.random_int(1000, 9999)),
            ]);

            Teacher::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'nip' => $data['nip'] ?: null,
                'employment_status' => $data['employment_status'] ?: null,
            ]);
        });

        $this->reset(['showForm', 'name', 'email', 'nip', 'employment_status']);
        $this->dispatch('toast', message: 'Guru ditambahkan. Atur ulang kata sandinya lewat tautan lupa sandi.', type: 'success');
    }

    public function render()
    {
        return view('livewire.teachers.index', [
            'teachers' => Teacher::query()->with('user')->orderBy('name')->get(),
        ]);
    }
}
