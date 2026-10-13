<?php

namespace App\Livewire;

use App\Models\TeacherCheckin;
use App\Services\TeacherCheckinService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Check-in')]
class Checkin extends Component
{
    public ?string $error = null;

    public function checkIn(float $lat, float $lng, ?float $accuracy, TeacherCheckinService $service): void
    {
        $this->run(fn () => $service->checkIn(auth()->user()->teacher, auth()->user()->tenant, $lat, $lng, $accuracy), 'Check-in berhasil. Selamat bertugas!');
    }

    public function checkOut(float $lat, float $lng, TeacherCheckinService $service): void
    {
        $this->run(fn () => $service->checkOut(auth()->user()->teacher, auth()->user()->tenant, $lat, $lng), 'Check-out berhasil. Sampai jumpa besok!');
    }

    public function locationFailed(string $reason): void
    {
        $this->error = $reason;
    }

    private function run(callable $action, string $success): void
    {
        abort_if(auth()->user()->teacher === null, 403);
        $this->error = null;

        try {
            $action();
            $this->dispatch('toast', message: $success, type: 'success');
        } catch (ValidationException $exception) {
            $this->error = $exception->validator->errors()->first();
        }
    }

    public function render()
    {
        $teacher = auth()->user()->teacher;
        $tenant = auth()->user()->tenant;

        return view('livewire.checkin', [
            'checkin' => $teacher ? TeacherCheckin::query()->where('teacher_id', $teacher->id)->whereDate('date', today())->first() : null,
            'tenant' => $tenant,
            'hasProfile' => $teacher !== null,
        ]);
    }
}
