<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\StoreComponentGradesRequest;
use App\Models\User;
use App\Services\GradingService;
use App\Services\Integration\SyncReader;
use Illuminate\Http\JsonResponse;

class ClassGradeController extends Controller
{
    public function __construct(private readonly SyncReader $reader, private readonly GradingService $grading) {}

    /**
     * Terima satu komponen nilai (tugas, UTS, UAS) dari LMS atas nama dosen pengampu (atau admin kampus).
     * 403 bila pengirim bukan pengampu, 409 bila nilai terkunci atau lewat batas, 422 bila kiriman tidak valid.
     */
    public function store(StoreComponentGradesRequest $request, int $class): JsonResponse
    {
        $assignment = $this->reader->findClass($class);
        $submitter = User::query()->find($request->integer('submitted_by'));

        abort_unless($submitter !== null && $this->grading->canSubmitFromIntegration($assignment, $submitter), 403, 'Pengirim bukan dosen pengampu kelas ini.');

        $reason = $this->grading->lockBlockReason($assignment);

        if ($reason !== null) {
            return response()->json(['message' => $reason], 409);
        }

        $scores = collect($request->validated('scores'))->mapWithKeys(fn (array $row): array => [(int) $row['user_id'] => (float) $row['score']])->all();

        return response()->json($this->grading->saveComponentFromIntegration($assignment, $submitter, $request->validated('component'), $scores));
    }
}
