<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Integration\AttendanceRowResource;
use App\Http\Resources\Integration\RosterStudentResource;
use App\Services\Integration\SyncReader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassRosterController extends Controller
{
    public function __construct(private readonly SyncReader $reader) {}

    /**
     * Peserta kelas dari `ClassRoster`; mahasiswa tanpa akun dilewati.
     */
    public function roster(Request $request, int $class): AnonymousResourceCollection
    {
        $class = $this->reader->findClass($class);

        return RosterStudentResource::collection($this->reader->rosterStudents($class)->paginate($this->reader->perPage($request)));
    }

    /**
     * Rekap presensi resmi per mahasiswa pada kuliah ini.
     */
    public function attendance(Request $request, int $class): AnonymousResourceCollection
    {
        $class = $this->reader->findClass($class);

        $students = $this->reader->rosterStudents($class)->paginate($this->reader->perPage($request));
        $recaps = $this->reader->attendanceFor($class, $students);

        return AttendanceRowResource::collection($students->through(fn ($student): array => ['student' => $student, 'recap' => $recaps[$student->id]]));
    }
}
