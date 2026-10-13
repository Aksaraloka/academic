<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['survey_response_id', 'survey_question_id', 'answer', 'choices'])]
class SurveyAnswer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['choices' => 'array'];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
