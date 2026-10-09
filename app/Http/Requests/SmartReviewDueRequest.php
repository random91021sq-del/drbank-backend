<?php

namespace App\Http\Requests;

class SmartReviewDueRequest extends SmartReviewRequest
{
    public function rules(): array
    {
        return ['id_study_block' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }
}
