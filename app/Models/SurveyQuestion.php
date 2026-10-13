<?php

namespace App\Models;

use App\Enums\SurveyQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['survey_id', 'position', 'type', 'text', 'options', 'is_required'])]
class SurveyQuestion extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SurveyQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
