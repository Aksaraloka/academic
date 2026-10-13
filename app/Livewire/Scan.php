<?php

namespace App\Livewire;

use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pindai QR')]
class Scan extends Component
{
    public string $code = '';

    public ?string $message = null;

    public bool $success = false;

    public function submit(AttendanceService $service): void
    {
        $this->validate(['code' => ['required', 'string', 'max:100']], [], ['code' => 'kode']);
        $this->message = null;
        $this->success = false;

        [$sessionId, $token] = array_pad(explode('.', trim($this->code), 2), 2, '');

        try {
            $session = AttendanceSession::query()->find((int) $sessionId);

            if ($session === null) {
                throw ValidationException::withMessages(['code' => 'Kode QR tidak dikenali.']);
            }

            $service->scanQr($session, auth()->user(), $token);
            $this->success = true;
            $this->message = 'Presensi tercatat. Terima kasih!';
            $this->reset('code');
        } catch (ValidationException $exception) {
            $this->message = collect($exception->errors())->flatten()->first();
        }
    }

    public function render()
    {
        return view('livewire.scan');
    }
}
