<?php

namespace App\Http\Requests;

class SmartReviewDueQuestionsRequest extends SmartReviewRequest
{
    public function rules(): array
    {
        return [
            'id_smart_review_assignment' => ['required', 'integer', 'min:1'],
            'id_student_theme_review' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
