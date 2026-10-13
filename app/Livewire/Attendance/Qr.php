<?php

namespace App\Livewire\Attendance;

use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use App\Services\QrTokenService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('QR presensi')]
class Qr extends Component
{
    public AttendanceSession $session;

    public string $payload = '';

    public function mount(AttendanceSession $session, AttendanceService $service, QrTokenService $qr): void
    {
        abort_unless($service->canEdit($session, auth()->user()), 403);

        $this->session = $session->load('studyGroup');
        $this->refreshToken($qr);
    }

    /**
     * Dipanggil berkala oleh wire:poll agar kode selalu mengikuti jendela 30 detik.
     */
    public function refreshToken(QrTokenService $qr): void
    {
        $this->payload = $this->session->id.'.'.$qr->tokenFor($this->session);
    }

    public function render(QrTokenService $qr)
    {
        return view('livewire.attendance.qr', [
            'seconds' => $qr->secondsRemaining(),
            'presentCount' => $this->session->records()->where('method', 'qr')->count(),
            'total' => $this->session->records()->count(),
        ]);
    }
}
