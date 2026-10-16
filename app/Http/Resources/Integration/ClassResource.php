<?php

namespace App\Http\Resources\Integration;

use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu kuliah (`TeachingAssignment`) sebagai kelas LMS.
 *
 * @mixin TeachingAssignment
 */
class ClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $teacherUser = $this->teacher?->user;

        return [
            'id' => $this->id,
            'term_id' => $this->academic_term_id,
            'subject' => [
                'code' => $this->subject->code,
                'name' => $this->subject->name,
                'credits' => (int) $this->subject->credits,
            ],
            'section' => $this->section,
            'class_label' => $this->classLabel(),
            'capacity' => $this->capacity,
            'grades_locked' => $this->areGradesLocked(),
            'teachers' => $teacherUser === null ? [] : [[
                'user_id' => $teacherUser->id,
                'name' => $teacherUser->name,
                'email' => $teacherUser->email,
                'identity_number' => $teacherUser->identityNumber(),
            ]],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
