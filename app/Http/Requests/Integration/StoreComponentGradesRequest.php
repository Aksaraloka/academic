<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kiriman komponen nilai dari LMS. Hanya komponen yang dimiliki LMS; kehadiran selalu dihitung Academic dari presensi.
 */
class StoreComponentGradesRequest extends FormRequest
{
    /** @var array<int, string> */
    public const COMPONENTS = ['assignment', 'midterm', 'final'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'component' => ['required', 'string', Rule::in(self::COMPONENTS)],
            'submitted_by' => ['required', 'integer', 'min:1'],
            'scores' => ['required', 'array', 'min:1', 'max:500'],
            'scores.*' => ['required', 'array'],
            'scores.*.user_id' => ['required', 'integer', 'min:1'],
            'scores.*.score' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
