<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;

/**
 * Menempatkan mahasiswa baru ke kelas angkatan pada semester masuknya. Kelas dicari menurut prodi, semester, dan
 * tingkat pertama (`grade_levels` dengan `position` terkecil); bila ada beberapa dipilih yang anggota aktifnya paling
 * sedikit. Bila belum ada, dibuat kelas `{KODE_PRODI} {angkatan} A` (huruf naik bila nama sudah dipakai) tanpa dosen
 * wali. Pemanggil berada di dalam transaksi serah terima, yang sudah menahan kunci baris prodi lewat `NimGenerator`,
 * sehingga dua serah terima bersamaan ke prodi yang sama tidak membuat kelas ganda.
 */
class StudyGroupPlacement
{
    public const FIRST_LEVEL_NAME = 'Tingkat 1';

    public function place(Student $student, Program $program, AcademicTerm $term, int $batchYear): StudyGroup
    {
        $level = $this->firstLevel($student->tenant_id);

        $group = StudyGroup::query()->withoutGlobalScopes()
            ->where('tenant_id', $student->tenant_id)->where('program_id', $program->id)
            ->where('academic_term_id', $term->id)->where('grade_level_id', $level->id)
            ->withCount(['members as active_members_count' => fn ($query) => $query->whereNull('left_at')])
            ->orderBy('active_members_count')->orderBy('id')
            ->first();

        $group ??= $this->createGroup($student->tenant_id, $program, $term, $level, $batchYear);

        StudyGroupMember::create(['study_group_id' => $group->id, 'student_id' => $student->id, 'joined_at' => today()]);

        return $group;
    }

    /**
     * Tingkat pertama kampus; dibuat bila kampus belum punya satu pun.
     */
    private function firstLevel(int $tenantId): GradeLevel
    {
        return GradeLevel::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('position')->orderBy('id')->first()
            ?? GradeLevel::create(['tenant_id' => $tenantId, 'name' => self::FIRST_LEVEL_NAME, 'position' => 1]);
    }

    private function createGroup(int $tenantId, Program $program, AcademicTerm $term, GradeLevel $level, int $batchYear): StudyGroup
    {
        $code = trim((string) $program->code) !== '' ? trim((string) $program->code) : 'P'.$program->id;
        $suffix = 'A';

        while (StudyGroup::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('academic_term_id', $term->id)
            ->where('name', "{$code} {$batchYear} {$suffix}")->exists()) {
            $suffix++;
        }

        return StudyGroup::create([
            'tenant_id' => $tenantId, 'academic_term_id' => $term->id, 'grade_level_id' => $level->id, 'program_id' => $program->id,
            'homeroom_teacher_id' => null, 'name' => "{$code} {$batchYear} {$suffix}",
        ]);
    }
}
