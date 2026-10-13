<?php

namespace App\Http\Controllers;

use App\Models\GraduationPeriod;
use Illuminate\Contracts\View\View;

class GraduationMinutesPrintController extends Controller
{
    /**
     * Versi cetak berita acara; hanya tersedia setelah diterbitkan.
     */
    public function __invoke(GraduationPeriod $period): View
    {
        $minute = $period->minute;

        abort_unless($minute?->finalized_at, 404, 'Berita acara belum diterbitkan.');

        return view('wisuda.minutes-print', [
            'period' => $period,
            'minute' => $minute,
            'participants' => collect($minute->participants),
            'tenant' => auth()->user()->tenant,
        ]);
    }
}
