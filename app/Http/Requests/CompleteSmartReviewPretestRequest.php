<?php

namespace App\Http\Requests;

use App\Custom\CustomResponse;

class CompleteSmartReviewPretestRequest extends SmartReviewRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'score_percentage' => ['required', 'numeric'],
            'time_spent' => ['required', 'integer', 'min:0'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at'],
            'answers' => ['required', 'array', 'size:50'],
            'answers.*.id_question' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['required', 'string'],
            'answers.*.difficulty' => ['required', 'string', 'in:hard,regular,easy'],
        ];
    }

    public function messages(): array
    {
        return array_merge($this->commonMessages(), [
            'answers.size' => CustomResponse::responseValidation('pretestAnswersSize', $this->language()),
            'answers.*.id_question.distinct' => CustomResponse::responseValidation('duplicateQuestion', $this->language()),
            'answers.*.difficulty.in' => CustomResponse::responseValidation('invalidDifficulty', $this->language()),
        ]);
    }
}
